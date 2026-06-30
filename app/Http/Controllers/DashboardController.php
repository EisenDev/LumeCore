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
                    'hash' => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($asset->id)),
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
        return Inertia::render('Overview', $this->getDashboardData($request->user()));
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

    /**
     * Display the full scanned website results page.
     */
    public function showWebsiteResults(Request $request, string $hash): Response
    {
        $user = $request->user();
        
        // Rebuild base64 and decode
        $base64 = str_replace(['-', '_'], ['+', '/'], $hash);
        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $uuid = base64_decode($base64);

        if (!$uuid) {
            abort(404);
        }

        // Find the vault asset owned by the user
        $asset = VaultAsset::where('user_id', $user->id)
            ->where('id', $uuid)
            ->firstOrFail();

        // Fetch latest scan activity for website results snapshot
        $activity = \App\Models\ScanActivity::where('user_id', $user->id)
            ->where('primary_asset_id', $asset->id)
            ->orderBy('scanned_at', 'desc')
            ->first();

        // Fetch historical scan history
        $history = $asset->auditHistory()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($h) {
                return [
                    'id' => $h->id,
                    'score' => $h->score,
                    'status' => $h->status,
                    'created_at' => $h->created_at->toISOString(),
                ];
            });

        // Overlay scan activity data if available (identical to frontend createSnapshotAsset helper)
        $snapshot = $this->createSnapshotAsset($asset, $activity);

        return Inertia::render('WebsiteResults', [
            'asset' => $snapshot,
            'hash' => $hash,
            'history' => $history,
        ]);
    }

    private function createSnapshotAsset(VaultAsset $baseAsset, $activityRecord)
    {
        // Clone mapping array
        $snapshot = [
            'id' => $baseAsset->id,
            'user_id' => $baseAsset->user_id,
            'file_name' => $baseAsset->file_name,
            'file_path' => $baseAsset->file_path,
            'file_size' => $baseAsset->file_size,
            'mime_type' => $baseAsset->mime_type,
            'status' => $baseAsset->status,
            'metadata' => $baseAsset->metadata ?? [],
            'synced_metadata' => $baseAsset->synced_metadata,
            'website_metadata' => $baseAsset->website_metadata,
            'repository_metadata' => $baseAsset->repository_metadata,
            'sync_score' => $baseAsset->sync_score,
            'score' => $baseAsset->score,
            'is_for_sale' => (bool)$baseAsset->is_for_sale,
            'price' => $baseAsset->price,
            'radar_data' => $baseAsset->radar_data,
            'created_at' => $baseAsset->created_at->toISOString(),
            'updated_at' => $baseAsset->updated_at->toISOString(),
        ];

        if (!$activityRecord) {
            // Apply live columns to metadata if no activity
            if ($baseAsset->website_metadata) {
                $snapshot['metadata'] = array_merge($snapshot['metadata'], $baseAsset->website_metadata);
            }
            return $snapshot;
        }

        // Extract details
        $details = $activityRecord->details ?? [];
        if (is_string($details)) {
            try { $details = json_decode($details, true) ?? []; } catch (\Exception $e) { $details = []; }
        }

        // Extract vectors
        $vectors = $activityRecord->vectors ?? [];
        if (is_string($vectors)) {
            try { $vectors = json_decode($vectors, true) ?? []; } catch (\Exception $e) { $vectors = []; }
        }

        $deepMerge = array_merge($details, $vectors);

        if ($baseAsset->website_metadata) {
            $snapshot['metadata'] = array_merge($snapshot['metadata'], $baseAsset->website_metadata);
        }
        if ($baseAsset->repository_metadata) {
            $snapshot['metadata'] = array_merge($snapshot['metadata'], $baseAsset->repository_metadata);
        }
        if ($baseAsset->radar_data) {
            $snapshot['metadata']['radar_data'] = $baseAsset->radar_data;
        }

        if ($activityRecord->vectors) {
            $snapshot['metadata']['hexagon_vectors'] = $vectors;
            $snapshot['metadata']['radar_data'] = $vectors;
        }

        if (isset($deepMerge['security_audit'])) {
            $snapshot['metadata'] = array_merge($snapshot['metadata'], $deepMerge['security_audit']);
        }
        $snapshot['metadata'] = array_merge($snapshot['metadata'], $deepMerge);

        if (isset($snapshot['metadata']['security_audit'])) {
            $snapshot['metadata'] = array_merge($snapshot['metadata'], $snapshot['metadata']['security_audit']);
        }

        if (isset($baseAsset->metadata['tech_stack']) && is_array($baseAsset->metadata['tech_stack'])) {
            $snapshot['metadata']['tech_stack'] = $baseAsset->metadata['tech_stack'];
        }
        if (isset($baseAsset->metadata['tech_assessment'])) {
            $snapshot['metadata']['tech_assessment'] = array_merge($snapshot['metadata']['tech_assessment'] ?? [], $baseAsset->metadata['tech_assessment']);
        }

        $snapshot['metadata'] = array_merge($snapshot['metadata'], [
            'is_historical_snapshot' => true,
            'snapshot_date' => $activityRecord->created_at->toISOString(),
        ]);

        $newScore = $activityRecord->individual_score;
        if ($newScore !== null) {
            $snapshot['score'] = $newScore;
            $snapshot['metadata']['confidence_score'] = $newScore;
        }

        if (isset($deepMerge['topology'])) {
            $snapshot['metadata']['topology'] = $deepMerge['topology'];
        }
        if (isset($baseAsset->website_metadata['topology'])) {
            $snapshot['metadata']['topology'] = $baseAsset->website_metadata['topology'];
        }

        // Force type to website
        $snapshot['metadata']['audit_type'] = 'website';

        return $snapshot;
    }
}
