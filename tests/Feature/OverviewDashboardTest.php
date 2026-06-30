<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultAsset;
use App\Models\ScanActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class OverviewDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/overview');

        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access overview dashboard with empty state.
     */
    public function test_authenticated_user_can_access_overview_dashboard_empty(): void
    {
        $user = User::factory()->create();

        // Update the auto-created wallet credits
        $user->wallet->update([
            'credits' => 50,
        ]);

        $response = $this->actingAs($user)->get('/overview');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->has('initialAssets', 0)
            ->has('recentActivities', 0)
            ->where('wallet.credits', '50.00')
        );
    }

    /**
     * Test authenticated user can see real counts and records on overview dashboard.
     */
    public function test_authenticated_user_sees_populated_dashboard_records(): void
    {
        $user = User::factory()->create();

        $user->wallet->update([
            'credits' => 100,
        ]);

        // Create 2 vault assets
        $asset1 = VaultAsset::create([
            'user_id' => $user->id,
            'file_name' => 'https://test-site-1.com',
            'score' => 45,
            'status' => 'ready',
        ]);

        $asset2 = VaultAsset::create([
            'user_id' => $user->id,
            'file_name' => 'https://test-site-2.com',
            'score' => 85,
            'status' => 'ready',
        ]);

        // Create 1 scan activity
        ScanActivity::create([
            'user_id' => $user->id,
            'primary_asset_id' => $asset1->id,
            'type' => 'website',
            'individual_score' => 45,
            'urls_and_sync' => 'https://test-site-1.com',
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/overview');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->has('initialAssets', 2)
            ->has('recentActivities', 1)
            ->where('wallet.credits', '100.00')
        );
    }
}
