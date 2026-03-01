<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetEmbedding;
use App\Models\ProjectChat;
use App\Models\VaultAsset;
use App\Services\AI\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGuideController extends Controller
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Chat with the LUME Project Analyst.
     * Supports RAG-powered responses when asset_id is provided.
     */
    public function chat(Request $request) 
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'errorMessage' => 'nullable|string|max:500', 
            'currentInput' => 'nullable|string|max:500',
            'asset_id' => 'nullable|exists:vault_assets,id',
        ]);

        $userPrompt = $request->input('prompt');
        $errorMessage = $request->input('errorMessage');
        $currentInput = $request->input('currentInput');
        $assetId = $request->input('asset_id');
        
        $knowledgeBase = '';
        if (file_exists(storage_path('app/lume_knowledge.md'))) {
            $knowledgeBase = file_get_contents(storage_path('app/lume_knowledge.md'));
        }

        // === RAG: SEMANTIC SEARCH ===
        $ragContext = "";
        $assetName = "this project";
        $asset = null;
        
        if ($assetId) {
            $asset = VaultAsset::with('project')->find($assetId);
            if ($asset) {
                $assetName = $asset->file_name;
                
                // 1. Save User Message to DB
                ProjectChat::create([
                    'user_id' => auth()->id() ?? 1,
                    'vault_asset_id' => $asset->id,
                    'message' => $userPrompt,
                    'role' => 'user'
                ]);

                // 2. RAG: Embed the question and search for relevant data
                $ragContext = $this->performSemanticSearch($assetId, $userPrompt);
            }
        }

        // === BUILD RAG-ENHANCED PROMPT ===
        $systemInstruction = $this->buildRAGPrompt($asset, $ragContext, $knowledgeBase);

        $fullPrompt = "User Question: " . $userPrompt;
        if ($errorMessage) {
            $fullPrompt .= "\n\nCONTEXT - ERROR MESSAGE: " . $errorMessage;
        }
        if ($currentInput) {
            $fullPrompt .= "\nCONTEXT - USER INPUT: " . $currentInput;
        }

        try {
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . config('services.gemini.key'), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemInstruction . "\n\n" . $fullPrompt]
                        ]
                    ]
                ]
            ]);

            $responseData = $response->json();
            $candidates = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$candidates) {
                 return response()->json(['error' => 'Empty response from AI Provider.'], 500);
            }
            
            // Flexible JSON parsing
            $jsonStr = $candidates;
            if (str_contains($candidates, '```json')) {
                $jsonStr = str_replace(['```json', '```'], '', $candidates);
            }
            
            $aiResponse = json_decode($jsonStr, true);
            $finalAnswer = $aiResponse['answer'] ?? $candidates;

            // SAVE AI RESPONSE TO DB
            if ($assetId && $finalAnswer) {
                ProjectChat::create([
                    'user_id' => auth()->id() ?? 1,
                    'vault_asset_id' => $assetId,
                    'message' => $finalAnswer,
                    'role' => 'ai'
                ]);
            }

            return response()->json([
                'reply' => $finalAnswer,
                'suggestions' => $aiResponse['suggestions'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('AIGuide Controller Exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Internal System Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Perform semantic search using pgvector.
     * Embeds the question and finds the most relevant data from previous scans.
     */
    protected function performSemanticSearch(string $assetId, string $question): string
    {
        try {
            // 1. Embed the question
            $questionEmbedding = $this->embeddingService->embed($question);
            
            if (!$questionEmbedding) {
                Log::warning("AIGuideController: Failed to embed question");
                return "";
            }
            
            // 2. Format embedding for pgvector query
            $embeddingStr = '[' . implode(',', $questionEmbedding) . ']';
            
            // 3. Search for similar embeddings using cosine distance
            // Lower distance = more similar
            $results = DB::select("
                SELECT 
                    content,
                    1 - (embedding <=> ?) as similarity
                FROM asset_embeddings 
                WHERE asset_id = ?
                ORDER BY embedding <=> ?
                LIMIT 5
            ", [$embeddingStr, $assetId, $embeddingStr]);
            
            if (empty($results)) {
                Log::info("AIGuideController: No embeddings found for asset {$assetId}");
                return "";
            }
            
            // 4. Build context from results
            $context = "=== VERIFIED PROJECT DATA (RAG) ===\n";
            $context .= "The following data is from the actual forensic scan of this project:\n\n";
            
            foreach ($results as $i => $row) {
                $similarity = round($row->similarity * 100, 1);
                $context .= "--- DATA CHUNK " . ($i + 1) . " (Relevance: {$similarity}%) ---\n";
                $context .= $row->content . "\n\n";
            }
            
            $context .= "=== END VERIFIED DATA ===\n";
            
            Log::info("AIGuideController: Found " . count($results) . " relevant chunks for question");
            
            return $context;
            
        } catch (\Throwable $e) {
            Log::error("AIGuideController: Semantic search failed", ['error' => $e->getMessage()]);
            return "";
        }
    }

    /**
     * Build the RAG-enhanced system prompt.
     */
    protected function buildRAGPrompt(?VaultAsset $asset, string $ragContext, string $knowledgeBase): string
    {
        $assetName = $asset?->file_name ?? 'Unknown Project';
        $status = $asset?->status ?? 'N/A';
        $score = $asset?->metadata['confidence_score'] ?? 'N/A';
        
        // === RAG PROMPT ===
        $prompt = <<<PROMPT
You are the LUME Project Analyst. You provide PRECISE, EVIDENCE-BASED answers.

=== CRITICAL RULES ===
1. You MUST use the "VERIFIED PROJECT DATA" provided below to answer questions.
2. Do NOT guess or make assumptions. If the data doesn't contain the answer, say "This information was not captured in the forensic scan."
3. Always cite specific findings from the verified data.
4. Be executive, direct, and action-oriented.
5. Use bullet points and bold text for key metrics.

=== PROJECT CONTEXT ===
Project: **{$assetName}**
Status: {$status}
Score: {$score}/100

{$ragContext}

SYSTEM KNOWLEDGE BASE:
{$knowledgeBase}

=== RESPONSE FORMAT ===
Your response must be a valid JSON object:
{
    "answer": "Your detailed response here (Markdown supported). Always reference the verified data.",
    "suggestions": ["Relevant follow-up question 1", "Relevant follow-up question 2", "Relevant follow-up question 3"]
}

Remember: You are the source of TECHNICAL TRUTH. Only state what is verified in the data.
PROMPT;

        return $prompt;
    }
}
