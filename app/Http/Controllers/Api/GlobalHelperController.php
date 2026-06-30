<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectChat;
use App\Services\AI\AssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalHelperController extends Controller
{
    public function __construct(protected AssistantService $assistant) {}

    /**
     * Handle chat requests for the LUME Architect.
     * Saves both user message and AI response to project_chats.
     */
    /**
     * Handle chat requests for the LUME Architect.
     * Saves both user message and AI response to project_chats.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'asset_id' => 'nullable|exists:vault_assets,id',
            'conversation_id' => 'nullable|string',
        ]);

        $user = Auth::user();
        $userName = $user ? $user->name : 'Traveler';
        $assetId = $request->input('asset_id');
        $conversationId = $request->input('conversation_id');

        if (!$conversationId) {
            $conversationId = (string) \Illuminate\Support\Str::uuid();
        }

        // Save user message (gracefully handle missing table)
        if ($user) {
            try {
                ProjectChat::create([
                    'user_id' => $user->id,
                    'vault_asset_id' => $assetId,
                    'conversation_id' => $conversationId,
                    'message' => $request->input('message'),
                    'role' => 'user',
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Could not save user chat message: ' . $e->getMessage());
            }
        }

        // Get AI response
        $response = $this->assistant->askArchitect(
            $request->input('message'),
            $userName,
            $assetId
        );

        // Save AI response (gracefully handle missing table)
        if ($user) {
            try {
                ProjectChat::create([
                    'user_id' => $user->id,
                    'vault_asset_id' => $assetId,
                    'conversation_id' => $conversationId,
                    'message' => $response,
                    'role' => 'ai',
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Could not save AI chat response: ' . $e->getMessage());
            }
        }

        return response()->json([
            'reply' => $response,
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * Get chat history for a specific asset or conversation.
     */
    public function history(Request $request)
    {
        $request->validate([
            'asset_id' => 'nullable|exists:vault_assets,id',
            'conversation_id' => 'nullable|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['messages' => []]);
        }

        try {
            $messages = ProjectChat::where('user_id', $user->id)
                ->when($request->filled('conversation_id'), function ($query) use ($request) {
                    return $query->where('conversation_id', $request->input('conversation_id'));
                })
                ->when($request->filled('asset_id') && !$request->filled('conversation_id'), function ($query) use ($request) {
                    return $query->where('vault_asset_id', $request->input('asset_id'));
                })
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'role' => $msg->role,
                    'content' => $msg->message,
                    'timestamp' => $msg->created_at->toISOString(),
                ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not fetch chat history: ' . $e->getMessage());
            $messages = collect([]);
        }

        return response()->json([
            'messages' => $messages
        ]);
    }

    /**
     * Get list of unique conversation sessions for the authenticated user.
     */
    public function sessions(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['conversations' => []]);
        }

        try {
            $chats = ProjectChat::where('user_id', $user->id)
                ->whereNotNull('conversation_id')
                ->orderBy('created_at', 'desc')
                ->get();

            $sessions = $chats->groupBy('conversation_id')->map(function ($group) {
                $latest = $group->first();
                $userMessage = $group->where('role', 'user')->last();
                $title = $userMessage ? \Illuminate\Support\Str::limit($userMessage->message, 40) : 'Conversation';

                return [
                    'conversation_id' => $latest->conversation_id,
                    'title' => $title,
                    'latest_message' => \Illuminate\Support\Str::limit($latest->message, 60),
                    'timestamp' => $latest->created_at->toISOString(),
                    'time' => $latest->created_at->diffForHumans(),
                ];
            })->values();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not fetch conversation sessions: ' . $e->getMessage());
            $sessions = collect([]);
        }

        return response()->json([
            'conversations' => $sessions
        ]);
    }
    /**
     * Validate if a URL is accessible (HEAD request).
     */
    public function validateUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->head($url);
            
            return response()->json([
                'valid' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 400
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * On-demand AI Generation Detection for a document asset.
     */
    public function aiDetection(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:vault_assets,id',
        ]);

        $asset = \App\Models\VaultAsset::find($request->input('asset_id'));
        if (!$asset) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        $fullText = $asset->metadata['full_text'] ?? '';
        if (empty($fullText)) {
            return response()->json(['error' => 'No text content available for analysis'], 422);
        }

        try {
            $auditor = app(\App\Services\AI\DocumentAuditor::class);
            $result = $auditor->analyzeAiDetection($fullText);

            // PERSISTENCE: Save to asset metadata
            $meta = $asset->metadata ?? [];
            $meta['ai_analysis'] = $result;
            $asset->metadata = $meta;
            $asset->save();

            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI Detection failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'AI Detection analysis failed. Please try again.',
                'ai_probability' => 50,
                'human_probability' => 50,
                'verdict' => 'Mixed Content',
                'confidence' => 'Low',
                'overall_assessment' => 'Analysis could not be completed.',
                'reasoning' => [],
                'flagged_passages' => [],
            ], 500);
        }
    }
}

