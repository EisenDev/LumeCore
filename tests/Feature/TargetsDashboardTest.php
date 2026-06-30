<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultAsset;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class TargetsDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/targets');

        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access targets dashboard with correct stats structure.
     */
    public function test_authenticated_user_can_access_targets_dashboard_empty(): void
    {
        $user = User::factory()->create();

        // Ensure wallet exists
        $user->wallet->update([
            'credits' => 50,
        ]);

        $response = $this->actingAs($user)->get('/targets');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Targets')
            ->where('stats.total', 0)
            ->where('stats.synced', 0)
            ->where('stats.websites', 0)
            ->where('stats.repos', 0)
            ->where('stats.apis', 0)
            ->where('stats.domains', 0)
            ->where('stats.growth.total', 0)
            ->where('stats.growth.synced', 0)
            ->where('stats.growth.websites', 0)
            ->where('stats.growth.repos', 0)
            ->where('stats.growth.apis', 0)
            ->where('stats.growth.domains', 0)
        );
    }

    /**
     * Test targets metrics and growth calculation based on vault assets creation date.
     */
    public function test_targets_dashboard_metrics_and_growth(): void
    {
        $user = User::factory()->create();

        // Create 2 websites in recent period (last 30 days)
        $recent1 = new VaultAsset([
            'user_id' => $user->id,
            'file_name' => 'https://recent-site1.com',
            'score' => 90,
            'status' => 'ready',
        ]);
        $recent1->timestamps = false;
        $recent1->created_at = now()->subDays(5);
        $recent1->updated_at = now()->subDays(5);
        $recent1->save();

        $recent2 = new VaultAsset([
            'user_id' => $user->id,
            'file_name' => 'https://recent-site2.com',
            'score' => 80,
            'status' => 'ready',
        ]);
        $recent2->timestamps = false;
        $recent2->created_at = now()->subDays(10);
        $recent2->updated_at = now()->subDays(10);
        $recent2->save();

        // Create 1 website in prior period (older than 30 days, newer than 60 days)
        $prior = new VaultAsset([
            'user_id' => $user->id,
            'file_name' => 'https://prior-site.com',
            'score' => 70,
            'status' => 'ready',
        ]);
        $prior->timestamps = false;
        $prior->created_at = now()->subDays(45);
        $prior->updated_at = now()->subDays(45);
        $prior->save();

        $response = $this->actingAs($user)->get('/targets');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Targets')
            // Total should be 3 (2 recent + 1 prior)
            ->where('stats.total', 3)
            // Websites should be 3
            ->where('stats.websites', 3)
            // Growth = ((3 - 1) / 1) * 100 = 200%
            ->where('stats.growth.websites', 200)
            ->where('stats.growth.total', 200)
        );
    }
}
