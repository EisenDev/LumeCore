<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantService
{
    /**
     * Ask the LUME Knowledge Architect.
     * Uses Gemini 1.5 Flash for stability and speed.
     *
     * @param string $question The user's question
     * @param string $userName The user's name for personalization
     * @param string|null $assetId Context asset ID
     * @return string The AI response
     */
    public function askArchitect(string $question, string $userName, ?string $assetId = null): string
    {
        $contextSection = "";
        
        if ($assetId) {
            $asset = \App\Models\VaultAsset::find($assetId);
            if ($asset) {
                $contextSection = "\n=== ACTIVE CONTEXT ===\n";
                $contextSection .= "Asset: {$asset->file_name}\n";
                $contextSection .= "Type: " . ($asset->metadata['audit_type'] ?? 'Unknown') . "\n";
                
                // Add Audit Results if available
                if (!empty($asset->metadata['summary'])) {
                     $contextSection .= "Summary: {$asset->metadata['summary']}\n";
                }

                // Document-specific: inject full text and analysis for accurate answers
                if (($asset->metadata['audit_type'] ?? '') === 'document') {
                    $contextSection .= "Document Type: " . ($asset->metadata['document_type'] ?? 'Unknown') . "\n";
                    $contextSection .= "Score: " . ($asset->metadata['score'] ?? 'N/A') . "/100\n";
                    $contextSection .= "Verdict: " . ($asset->metadata['verdict'] ?? 'N/A') . "\n";

                    // Inject key insights
                    if (!empty($asset->metadata['key_insights'])) {
                        $contextSection .= "\nKEY INSIGHTS:\n";
                        foreach ($asset->metadata['key_insights'] as $insight) {
                            $contextSection .= "- {$insight}\n";
                        }
                    }

                    // Inject forensic highlights
                    if (!empty($asset->metadata['forensic_highlights'])) {
                        $contextSection .= "\nFORENSIC HIGHLIGHTS:\n";
                        foreach (array_slice($asset->metadata['forensic_highlights'], 0, 20) as $h) {
                            $contextSection .= "- [{$h['type']}] p.{$h['page']}: {$h['text']} — {$h['detail']}\n";
                        }
                    }

                    // Inject content sections
                    if (!empty($asset->metadata['content_sections'])) {
                        $contextSection .= "\nDOCUMENT SECTIONS:\n";
                        foreach ($asset->metadata['content_sections'] as $section) {
                            $contextSection .= "- [{$section['heading']}] (p.{$section['page']}): {$section['summary']}\n";
                        }
                    }

                    // Inject full extracted text (truncated) for deep Q&A
                    if (!empty($asset->metadata['full_text'])) {
                        $fullText = mb_substr($asset->metadata['full_text'], 0, 20000);
                        $contextSection .= "\n=== FULL DOCUMENT TEXT (truncated) ===\n{$fullText}\n=== END DOCUMENT TEXT ===\n";
                    }
                }
                
                // Add Technical Insight if available (for projects)
                if (!empty($asset->metadata['tech_assessment'])) {
                     $contextSection .= "Tech Stack: " . json_encode($asset->metadata['tech_assessment']) . "\n";
                }
                
                // Add Warning Flags
                if (!empty($asset->metadata['warning_flags'])) {
                     $contextSection .= "Warnings: " . json_encode($asset->metadata['warning_flags']) . "\n";
                }
                
                // Add Full Audit Data if Project (safely check)
                try {
                    if (method_exists($asset, 'project') && $asset->project && !empty($asset->project->audit_data)) {
                        $contextSection .= "\nDETAILED AUDIT DATA:\n" . json_encode($asset->project->audit_data) . "\n";
                    }
                } catch (\Exception $e) {
                    // Relationship may not exist for document assets
                }
            }
        }

        $prompt = <<<PROMPT
You are the LUME Knowledge Architect. You have complete knowledge of the 6 Pillars of LUME.

CORE OBJECTIVE:
- Use the provided ACTIVE CONTEXT to answer the user's question about the specific asset/website.
- If the user asks about the audit results, vulnerabilities, or tech stack, use the DETAILED AUDIT DATA.
- Be precise and technical if the context suggests a developer focus.

GUIDELINES:
1. If discussing the asset, refer to it directly (e.g., "This project uses Next.js...").
2. If no context is relevant to the question, fall back to general LUME assistance.
3. Keep answers concise (under 3 paragraphs) unless asked for deep detail.
4. If the user asks about non-LUME/non-Asset topics, refuse politely.

{$contextSection}

User ({$userName}): {$question}
PROMPT;

        return $this->callGemini($prompt);
    }

    /**
     * Call the Gemini API.
     * Uses same model as DocumentAuditor for consistency.
     */
    protected function callGemini(string $prompt): string
    {
        $apiKey = config('services.gemini.key');
        // Use the same model as DocumentAuditor from config
        $model = config('services.gemini.model', 'gemini-3-flash-preview');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        if (empty($apiKey)) {
            Log::error('Gemini API key missing in AssistantService');
            return "I apologize, but my connection to the matrix is currently offline. Please contact support.";
        }

        try {
            $response = Http::retry(3, 2000)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('LUME Architect Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return "I am currently experiencing high traffic levels. Please try asking me again in a moment.";
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? "I couldn't process that request properly.";

        } catch (\Exception $e) {
            Log::error('AssistantService Exception', ['error' => $e->getMessage()]);
            return "An internal system error occurred. Please try again later.";
        }
    }
}
