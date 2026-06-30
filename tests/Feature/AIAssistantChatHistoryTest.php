<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProjectChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIAssistantChatHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access chat history endpoints.
     */
    public function test_guest_cannot_access_chat_endpoints(): void
    {
        // history list
        $response = $this->getJson('/ai/history');
        $response->assertStatus(401);

        // history messages
        $response2 = $this->getJson('/ai/chat/history?conversation_id=some-id');
        $response2->assertStatus(401);
    }

    /**
     * Test user can send chat messages and verify persistence and session_id generation.
     */
    public function test_user_can_send_chat_message_and_persist(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/ai/chat', [
                'message' => 'What is LUME sovereign forensics?',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'reply',
            'conversation_id',
        ]);

        $conversationId = $response->json('conversation_id');
        $this->assertNotEmpty($conversationId);

        // Verify two records (user query + AI reply) are saved under this conversation_id
        $this->assertDatabaseHas('project_chats', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'message' => 'What is LUME sovereign forensics?',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('project_chats', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'role' => 'ai',
        ]);
    }

    /**
     * Test user can load messages from a specific conversation session.
     */
    public function test_user_can_load_conversation_message_history(): void
    {
        $user = User::factory()->create();
        $conversationId = 'test-conversation-uuid-123';

        // Pre-create some messages
        ProjectChat::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'message' => 'Hello AI',
            'role' => 'user',
        ]);

        ProjectChat::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'message' => 'Hello User',
            'role' => 'ai',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/ai/chat/history?conversation_id={$conversationId}");

        $response->assertOk();
        $response->assertJsonCount(2, 'messages');
        $response->assertJsonPath('messages.0.role', 'user');
        $response->assertJsonPath('messages.0.content', 'Hello AI');
        $response->assertJsonPath('messages.1.role', 'ai');
        $response->assertJsonPath('messages.1.content', 'Hello User');
    }

    /**
     * Test user can get conversation sessions list.
     */
    public function test_user_can_retrieve_conversation_sessions(): void
    {
        $user = User::factory()->create();

        // Conversation 1 (older)
        $chat1 = new ProjectChat([
            'user_id' => $user->id,
            'conversation_id' => 'conv-1',
            'message' => 'Query 1',
            'role' => 'user',
        ]);
        $chat1->created_at = now()->subMinutes(10);
        $chat1->save();

        $chat2 = new ProjectChat([
            'user_id' => $user->id,
            'conversation_id' => 'conv-1',
            'message' => 'Reply 1',
            'role' => 'ai',
        ]);
        $chat2->created_at = now()->subMinutes(9);
        $chat2->save();

        // Conversation 2 (newer)
        $chat3 = new ProjectChat([
            'user_id' => $user->id,
            'conversation_id' => 'conv-2',
            'message' => 'Query 2',
            'role' => 'user',
        ]);
        $chat3->created_at = now()->subMinutes(5);
        $chat3->save();

        $response = $this
            ->actingAs($user)
            ->getJson('/ai/history');

        $response->assertOk();
        $response->assertJsonCount(2, 'conversations');

        // Check structure
        $response->assertJsonPath('conversations.0.conversation_id', 'conv-2');
        $response->assertJsonPath('conversations.0.title', 'Query 2');
        $response->assertJsonPath('conversations.1.conversation_id', 'conv-1');
        $response->assertJsonPath('conversations.1.title', 'Query 1');
    }
}
