<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest user cannot mark notifications as read.
     */
    public function test_guest_user_cannot_access_notification_endpoints(): void
    {
        $response = $this->patch('/notifications/some-id/read');
        $response->assertRedirect('/login');

        $response2 = $this->post('/notifications/read-all');
        $response2->assertRedirect('/login');
    }

    /**
     * Test notification data is shared globally via Inertia props.
     */
    public function test_notifications_are_shared_globally_via_inertia(): void
    {
        $user = User::factory()->create();

        // Create a test database notification
        $user->notifications()->create([
            'id' => '078a2cbf-1c8c-4c6e-8588-a97e192d888c',
            'type' => 'App\Notifications\TestNotification',
            'data' => [
                'title' => 'Test Notification',
                'message' => 'This is a test notification.',
                'action_url' => '/dashboard',
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/overview');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('auth.unread_notifications')
            ->where('auth.unread_notifications_count', 1)
            ->where('auth.unread_notifications.0.data.title', 'Test Notification')
        );
    }

    /**
     * Test marking a single notification as read.
     */
    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = $user->notifications()->create([
            'id' => '078a2cbf-1c8c-4c6e-8588-a97e192d888d',
            'type' => 'App\Notifications\TestNotification',
            'data' => [
                'title' => 'Test Notification',
                'message' => 'This is a test.',
            ],
        ]);

        $this->assertEquals(1, $user->unreadNotifications()->count());

        $response = $this
            ->actingAs($user)
            ->patch("/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'unread_notifications_count' => 0,
        ]);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    /**
     * Test marking all notifications as read.
     */
    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $user->notifications()->createMany([
            [
                'id' => '078a2cbf-1c8c-4c6e-8588-a97e192d888e',
                'type' => 'App\Notifications\TestNotification',
                'data' => ['title' => 'Notif 1', 'message' => 'Message 1'],
            ],
            [
                'id' => '078a2cbf-1c8c-4c6e-8588-a97e192d888f',
                'type' => 'App\Notifications\TestNotification',
                'data' => ['title' => 'Notif 2', 'message' => 'Message 2'],
            ],
        ]);

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $response = $this
            ->actingAs($user)
            ->post('/notifications/read-all');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'unread_notifications_count' => 0,
        ]);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }
}
