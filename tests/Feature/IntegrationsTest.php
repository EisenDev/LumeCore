<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Authentication Gate ──────────────────────────────────────────────────

    /**
     * Unauthenticated requests to /integrations should redirect to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/integrations')->assertRedirect('/login');
    }

    /**
     * Unauthenticated toggle requests should redirect to login.
     */
    public function test_guest_cannot_toggle_integration(): void
    {
        $this->post('/integrations/github/toggle')->assertRedirect('/login');
    }

    // ─── Index Page Rendering ─────────────────────────────────────────────────

    /**
     * The page renders with all required Inertia props when there are no integrations.
     */
    public function test_integrations_page_renders_with_empty_props(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Integrations')
                ->has('connectedIntegrations')
                ->has('availableIntegrations')
                ->has('healthStatuses')
                ->has('activityLog')
                ->has('stats')
                ->where('stats.connected', 0)
                ->where('stats.eventsSynced', 0)
                ->where('stats.allHealthy', true)
            );
    }

    /**
     * Connected integrations appear in props when the user has them in the DB.
     */
    public function test_connected_integrations_appear_in_props(): void
    {
        $user = User::factory()->create();

        Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'github',
            'name'         => 'GitHub',
            'description'  => 'Import repositories.',
            'status'       => 'connected',
            'added_by'     => $user->name,
            'connected_at' => now(),
            'last_synced_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($user)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Integrations')
                ->where('stats.connected', 1)
                ->has('connectedIntegrations', 1)
                ->where('connectedIntegrations.0.id', 'github')
                ->where('connectedIntegrations.0.status', 'Connected')
            );
    }

    /**
     * Platform that is connected should NOT appear in the available list.
     */
    public function test_connected_platform_removed_from_available_list(): void
    {
        $user = User::factory()->create();

        Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'slack',
            'name'         => 'Slack',
            'status'       => 'connected',
            'connected_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get('/integrations')
            ->assertOk();

        // Verify slack is NOT in available integrations
        $available = $response->original->getData()['page']['props']['availableIntegrations'] ?? [];
        $availablePlatforms = array_column($available, 'id');
        $this->assertNotContains('slack', $availablePlatforms);
    }

    /**
     * Activity log is populated from the integration_logs table.
     */
    public function test_activity_log_populated_from_database(): void
    {
        $user = User::factory()->create();

        $integration = Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'jira',
            'name'         => 'Jira',
            'status'       => 'connected',
            'connected_at' => now(),
        ]);

        IntegrationLog::create([
            'integration_id' => $integration->id,
            'user_id'        => $user->id,
            'event'          => 'sync',
            'description'    => 'Ticket LUME-999 created',
            'status'         => 'success',
        ]);

        $this->actingAs($user)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Integrations')
                ->has('activityLog', 1)
                ->where('activityLog.0.title', 'Jira')
                ->where('activityLog.0.desc', 'Ticket LUME-999 created')
                ->where('activityLog.0.error', false)
            );
    }

    /**
     * Health statuses are computed from the user's integration records.
     */
    public function test_health_statuses_computed_from_integrations(): void
    {
        $user = User::factory()->create();

        Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'github',
            'name'         => 'GitHub',
            'status'       => 'connected',
            'connected_at' => now(),
        ]);

        Integration::create([
            'user_id'  => $user->id,
            'platform' => 'webhook',
            'name'     => 'Custom Webhook',
            'status'   => 'error',
        ]);

        $this->actingAs($user)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Integrations')
                ->has('healthStatuses', 2)
                ->where('stats.allHealthy', false)
            );
    }

    // ─── Toggle Endpoint ──────────────────────────────────────────────────────

    /**
     * A user can connect a platform (POST toggle → creates Integration record).
     */
    public function test_user_can_connect_a_platform(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/integrations/github/toggle')
            ->assertRedirect();

        $this->assertDatabaseHas('integrations', [
            'user_id'  => $user->id,
            'platform' => 'github',
            'status'   => 'connected',
        ]);

        // Should also create a connect log
        $integration = Integration::where('user_id', $user->id)->where('platform', 'github')->first();
        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'event'          => 'connect',
            'status'         => 'success',
        ]);
    }

    /**
     * A user can disconnect an already-connected integration.
     */
    public function test_user_can_disconnect_a_platform(): void
    {
        $user = User::factory()->create();

        $integration = Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'slack',
            'name'         => 'Slack',
            'status'       => 'connected',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/integrations/slack/toggle')
            ->assertRedirect();

        $this->assertDatabaseHas('integrations', [
            'user_id'  => $user->id,
            'platform' => 'slack',
            'status'   => 'disconnected',
        ]);

        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $integration->id,
            'event'          => 'disconnect',
        ]);
    }

    /**
     * Toggle with an unknown platform should fail validation gracefully.
     */
    public function test_toggle_with_unknown_platform_fails(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/integrations/unknown-platform/toggle')
            ->assertSessionHasErrors(['platform']);
    }

    /**
     * Credentials are not exposed in the Inertia page props.
     */
    public function test_credentials_are_not_exposed_in_frontend_props(): void
    {
        $user = User::factory()->create();

        // Connect with credentials
        $this->actingAs($user)->post('/integrations/github/toggle');

        // Update with fake credentials
        Integration::where('user_id', $user->id)->update(['credentials' => 'secret-token-123']);

        $response = $this->actingAs($user)->get('/integrations');
        $responseContent = json_encode($response->original->getData());

        // Ensure the raw credential value is never present in the serialized output
        $this->assertStringNotContainsString('secret-token-123', $responseContent);
    }

    /**
     * Events synced stat counts log entries from the last 7 days only.
     */
    public function test_events_synced_counts_last_7_days_only(): void
    {
        $user = User::factory()->create();

        $integration = Integration::create([
            'user_id'      => $user->id,
            'platform'     => 'github',
            'name'         => 'GitHub',
            'status'       => 'connected',
            'connected_at' => now()->subDays(30),
        ]);

        // Log from 30 days ago — should NOT be counted (bypass Eloquent timestamps via direct insert)
        \Illuminate\Support\Facades\DB::table('integration_logs')->insert([
            'integration_id' => $integration->id,
            'user_id'        => $user->id,
            'event'          => 'sync',
            'description'    => 'Old sync',
            'status'         => 'success',
            'created_at'     => now()->subDays(30)->toDateTimeString(),
            'updated_at'     => now()->subDays(30)->toDateTimeString(),
        ]);

        // Log from yesterday — SHOULD be counted
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'user_id'        => $user->id,
            'event'          => 'sync',
            'description'    => 'Recent sync',
            'status'         => 'success',
        ]);

        $this->actingAs($user)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.eventsSynced', 1)
            );
    }
}
