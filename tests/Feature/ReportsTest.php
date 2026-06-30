<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScanActivity;
use App\Models\VaultAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest user is redirected to login.
     */
    public function test_guest_user_is_redirected_to_login(): void
    {
        $response = $this->get('/reports');
        $response->assertRedirect('/login');
    }

    /**
     * Test reports dashboard renders with correct Inertia structure and dynamic props.
     */
    public function test_reports_dashboard_renders_with_correct_props(): void
    {
        $user = User::factory()->create();

        // Create some mock scan activities and vault assets
        $asset = VaultAsset::create([
            'user_id' => $user->id,
            'file_name' => 'test-site.com',
            'status' => 'ready',
            'score' => 85,
        ]);

        $activity = ScanActivity::create([
            'user_id' => $user->id,
            'type' => 'website',
            'urls_and_sync' => 'https://test-site.com',
            'primary_asset_id' => $asset->id,
            'scanned_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/reports');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports')
            ->has('reports')
            ->has('stats')
            ->has('breakdown')
            ->has('recentActivities')
            ->where('stats.total', 2) // website yields 2 reports (Executive + Technical)
            ->where('breakdown.executive', 1)
            ->where('breakdown.technical', 1)
        );
    }
}
