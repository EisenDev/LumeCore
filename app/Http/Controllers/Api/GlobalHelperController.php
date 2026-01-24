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
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'asset_id' => 'nullable|exists:vault_assets,id',
        ]);

        $user = Auth::user();
        $userName = $user ? $user->name : 'Traveler';
        $assetId = $request->input('asset_id');

        // Save user message if we have an asset context (gracefully handle missing table)
        if ($user && $assetId) {
            try {
                ProjectChat::create([
                    'user_id' => $user->id,
                    'vault_asset_id' => $assetId,
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

        // Save AI response if we have an asset context (gracefully handle missing table)
        if ($user && $assetId) {
            try {
                ProjectChat::create([
                    'user_id' => $user->id,
                    'vault_asset_id' => $assetId,
                    'message' => $response,
                    'role' => 'ai',
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Could not save AI chat response: ' . $e->getMessage());
            }
        }

        return response()->json([
            'reply' => $response
        ]);
    }

    /**
     * Get chat history for a specific asset.
     * Returns last 10 messages for preserved context.
     */
    public function history(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:vault_assets,id',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['messages' => []]);
        }

        try {
            $messages = ProjectChat::where('user_id', $user->id)
                ->where('vault_asset_id', $request->input('asset_id'))
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->reverse() // Oldest first
                ->values()
                ->map(fn ($msg) => [
                    'role' => $msg->role,
                    'content' => $msg->message,
                ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not fetch chat history: ' . $e->getMessage());
            $messages = collect([]);
        }

        return response()->json([
            'messages' => $messages
        ]);
    }
}

