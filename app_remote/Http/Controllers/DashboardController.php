<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with user's vault assets and wallet.
     */
    /**
     * Helper to get dashboard data (Shared between Index and Refresh)
     */
    private function getDashboardData($user)
    {
        // Fetch user's wallet
        $wallet = Wallet::where('user_id', $user->id)->first();

        // Fetch ALL user's vault assets (pending, uploaded, verified, flagged, etc.)
        $assets = VaultAsset::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (VaultAsset $asset) {
                return [
                    'id' => $asset->id,
                    'user_id' => $asset->user_id,
                    'file_name' => $asset->file_name,
                    'file_path' => $asset->file_path,
                    'file_size' => $asset->file_size,
                    'mime_type' => $asset->mime_type,
                    'status' => $asset->status,
                    'metadata' => $asset->metadata,
                    'synced_metadata' => $asset->synced_metadata,
                    'website_metadata' => $asset->website_metadata,
                    'repository_metadata' => $asset->repository_metadata,
                    'sync_score' => $asset->sync_score,
                    'score' => $asset->score,
                    'is_for_sale' => (bool)$asset->is_for_sale,
                    'price' => $asset->price,
                    'radar_data' => $asset->radar_data,
                    'created_at' => $asset->created_at->toISOString(),
                    'updated_at' => $asset->updated_at->toISOString(),
                ];
            });

        // Fetch Scan Activities (Latest per Unique URL/Project)
        // Replaced Postgres 'DISTINCT ON' with universal Subquery approach
        $activities = \App\Models\ScanActivity::whereIn('id', function ($query) use ($user) {
            $query->selectRaw('MAX(id)')
                ->from('scan_activities')
                ->where('user_id', $user->id)
                ->groupBy('urls_and_sync', 'batch_id'); // TITAN V2: Distinct by Batch too
        })
        ->with(['primaryAsset', 'secondaryAsset'])
        ->orderBy('scanned_at', 'desc')
        ->limit(50) // Reasonable limit
        ->get();

        return [
            'initialAssets' => $assets,
            'recentActivities' => $activities,
            'wallet' => $wallet ? [
                'id' => $wallet->id,
                'balance' => (float) $wallet->balance,
                'currency' => $wallet->currency,
                'credits' => $wallet->credits,
            ] : null,
            'organizations' => $user->organizations()->get()->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'role' => $org->pivot->role,
                ];
            }),
            'activeOrganization' => $user->activeOrganization ? [
                'id' => $user->activeOrganization->id,
                'name' => $user->activeOrganization->name,
                'role' => $user->organizations()->where('organization_id', $user->active_organization_id)->first()?->pivot->role,
            ] : null,
        ];
    }

    /**
     * Display the dashboard with user's vault assets and wallet.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->getDashboardData($request->user()));
    }

    /**
     * TITAN V8.5: API Endpoint for background refresh (No Page Reload)
     */
    public function refresh(Request $request)
    {
        return response()->json($this->getDashboardData($request->user()));
    }

    /**
     * Delete a scan activity record.
     */
    public function destroyActivity(\App\Models\ScanActivity $activity)
    {
        // specific user check policy or simple owner check
        if ($activity->user_id !== request()->user()->id) {
            abort(403);
        }

        $activity->delete();

        return back()->with('success', 'Activity record removed.');
    }
}
