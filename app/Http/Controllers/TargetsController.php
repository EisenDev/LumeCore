<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use App\Models\ScanActivity;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class TargetsController extends Controller
{
    /**
     * Display the Targets page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Fetch user wallet (for credits display)
        $wallet = Wallet::where('user_id', $user->id)->first();

        // Fetch ALL user's vault assets (scans)
        $assets = VaultAsset::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (VaultAsset $asset) {
                return [
                    'id'                  => $asset->id,
                    'hash'                => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($asset->id)),
                    'user_id'             => $asset->user_id,
                    'file_name'           => $asset->file_name,
                    'file_path'           => $asset->file_path,
                    'file_size'           => $asset->file_size,
                    'mime_type'           => $asset->mime_type,
                    'status'              => $asset->status,
                    'metadata'            => $asset->metadata,
                    'synced_metadata'     => $asset->synced_metadata,
                    'website_metadata'    => $asset->website_metadata,
                    'repository_metadata' => $asset->repository_metadata,
                    'sync_score'          => $asset->sync_score,
                    'score'               => $asset->score,
                    'is_for_sale'         => (bool) $asset->is_for_sale,
                    'price'               => $asset->price,
                    'radar_data'          => $asset->radar_data,
                    'batch_id'            => $asset->batch_id,
                    'created_at'          => $asset->created_at->toISOString(),
                    'updated_at'          => $asset->updated_at->toISOString(),
                ];
            });

        // Group Synced Targets (assets with a non-null batch_id)
        $syncedGroups = $assets->whereNotNull('batch_id')->groupBy('batch_id');
        $syncedTargets = [];

        foreach ($syncedGroups as $batchId => $groupAssets) {
            $webAsset = $groupAssets->first(fn($a) => 
                (isset($a['metadata']['audit_type']) && $a['metadata']['audit_type'] === 'website_scan') ||
                Str::contains($a['file_name'], 'http') ||
                !Str::contains($a['file_name'], 'github.com')
            );
            
            $repoAsset = $groupAssets->first(fn($a) => 
                (isset($a['metadata']['audit_type']) && $a['metadata']['audit_type'] === 'repository_scan') ||
                Str::contains($a['file_name'], 'github.com') ||
                Str::contains($a['file_name'], 'gitlab.com')
            );

            // If we only have one type of asset in the batch, fallback gracefully
            if (!$webAsset) $webAsset = $groupAssets->first();
            if (!$repoAsset) $repoAsset = $groupAssets->last();

            // Make sure they are not the same actual asset unless there is only 1
            if ($webAsset['id'] === $repoAsset['id'] && $groupAssets->count() > 1) {
                $repoAsset = $groupAssets->first(fn($a) => $a['id'] !== $webAsset['id']);
            }

            // Derive score and correlation
            $score = $webAsset['sync_score'] ?? $webAsset['score'] ?? $repoAsset['score'] ?? null;
            $correlation = $webAsset['sync_score'] ?? $webAsset['metadata']['sync_confidence'] ?? $webAsset['synced_metadata']['sync_score'] ?? 85;

            // Health vs Risk conversion
            $riskScore = null;
            if ($score !== null) {
                $riskScore = 100 - $score;
            } else {
                // Mock score based on status if null
                $riskScore = 24; // default medium
            }

            $syncedTargets[] = [
                'batch_id' => $batchId,
                'web_asset' => $webAsset,
                'repo_asset' => $repoAsset,
                'name' => $webAsset['file_name'] ?? 'Synced Target',
                'status' => $webAsset['status'] ?? 'active',
                'risk_score' => $riskScore,
                'correlation' => $correlation,
                'last_scan' => $webAsset['updated_at'] ?? $webAsset['created_at'],
            ];
        }

        // Filter Other Targets (individual assets with batch_id = null)
        $otherTargetsRaw = $assets->whereNull('batch_id');
        $otherTargets = [];

        foreach ($otherTargetsRaw as $asset) {
            $auditStr = Str::lower(isset($asset['metadata']['audit_type']) ? $asset['metadata']['audit_type'] : '');
            $name = $asset['file_name'];
            $isRepo = in_array($auditStr, ['repository', 'repository_scan', 'github']) || Str::contains($name, 'github.com') || Str::contains($name, 'gitlab.com');
            
            // Determine type
            $type = 'website';
            if ($isRepo) {
                $type = 'repository';
            } elseif (Str::contains(Str::lower($name), 'api') || Str::contains(Str::lower($name), 'graphql') || (isset($asset['metadata']['is_api']) && $asset['metadata']['is_api'])) {
                $type = 'api';
            } elseif (count(explode('.', $name)) === 2 && !Str::contains($name, '/')) {
                $type = 'domain';
            }

            $score = $asset['score'] ?? null;
            $riskScore = $score !== null ? (100 - $score) : 18;

            $otherTargets[] = [
                'id' => $asset['id'],
                'asset' => $asset,
                'name' => $name,
                'type' => $type,
                'status' => $asset['status'] ?? 'active',
                'risk_score' => $riskScore,
                'last_scan' => $asset['updated_at'] ?? $asset['created_at'],
            ];
        }

        // Fetch recent activities
        $activities = ScanActivity::where('user_id', $user->id)
            ->with(['primaryAsset', 'secondaryAsset'])
            ->orderBy('scanned_at', 'desc')
            ->get();

        // Calculate dynamic stats matching the screenshot
        $totalSynced = count($syncedTargets);
        $totalWebsites = $assets->filter(fn($a) => 
            !Str::contains($a['file_name'], 'github.com') && 
            !Str::contains($a['file_name'], 'gitlab.com') &&
            !Str::contains(Str::lower($a['file_name']), 'api.')
        )->count();
        $totalRepos = $assets->filter(fn($a) => 
            Str::contains($a['file_name'], 'github.com') || 
            Str::contains($a['file_name'], 'gitlab.com')
        )->count();
        $totalApis = $assets->filter(fn($a) => Str::contains(Str::lower($a['file_name']), 'api.'))->count();
        $totalDomains = $assets->filter(fn($a) => 
            !Str::contains($a['file_name'], '/') && 
            count(explode('.', $a['file_name'])) === 2
        )->count();

        $stats = [
            'total'      => count($syncedTargets) + count($otherTargets),
            'synced'     => $totalSynced,
            'websites'   => $totalWebsites,
            'repos'      => $totalRepos,
            'apis'       => $totalApis,
            'domains'    => $totalDomains,
        ];

        // Growth delta calculations (30d comparison)
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
        $sixtyDaysAgo = \Carbon\Carbon::now()->subDays(60);

        $recentAssets = $assets->filter(function ($a) use ($thirtyDaysAgo) {
            return \Carbon\Carbon::parse($a['created_at'])->gt($thirtyDaysAgo);
        });

        $priorAssets = $assets->filter(function ($a) use ($thirtyDaysAgo, $sixtyDaysAgo) {
            $date = \Carbon\Carbon::parse($a['created_at']);
            return $date->lte($thirtyDaysAgo) && $date->gt($sixtyDaysAgo);
        });

        $getCounts = function ($collection) {
            $syncedGroups = $collection->whereNotNull('batch_id')->groupBy('batch_id');
            $syncedCount = $syncedGroups->count();

            $websitesCount = $collection->filter(fn($a) => 
                !Str::contains($a['file_name'], 'github.com') && 
                !Str::contains($a['file_name'], 'gitlab.com') &&
                !Str::contains(Str::lower($a['file_name']), 'api.')
            )->count();

            $reposCount = $collection->filter(fn($a) => 
                Str::contains($a['file_name'], 'github.com') || 
                Str::contains($a['file_name'], 'gitlab.com')
            )->count();

            $apisCount = $collection->filter(fn($a) => Str::contains(Str::lower($a['file_name']), 'api.'))->count();

            $domainsCount = $collection->filter(fn($a) => 
                !Str::contains($a['file_name'], '/') && 
                count(explode('.', $a['file_name'])) === 2
            )->count();

            $otherTargetsCount = $collection->whereNull('batch_id')->count();
            
            return [
                'total' => $syncedCount + $otherTargetsCount,
                'synced' => $syncedCount,
                'websites' => $websitesCount,
                'repos' => $reposCount,
                'apis' => $apisCount,
                'domains' => $domainsCount,
            ];
        };

        $recentCounts = $getCounts($recentAssets);
        $priorCounts = $getCounts($priorAssets);

        $calculateGrowth = function ($current, $prior) {
            if ($prior == 0) {
                return $current > 0 ? 100 : 0;
            }
            return (int) round((($current - $prior) / $prior) * 100);
        };

        $stats['growth'] = [
            'total'    => $calculateGrowth($stats['total'], $priorCounts['total']),
            'synced'   => $calculateGrowth($stats['synced'], $priorCounts['synced']),
            'websites' => $calculateGrowth($stats['websites'], $priorCounts['websites']),
            'repos'    => $calculateGrowth($stats['repos'], $priorCounts['repos']),
            'apis'     => $calculateGrowth($stats['apis'], $priorCounts['apis']),
            'domains'  => $calculateGrowth($stats['domains'], $priorCounts['domains']),
        ];

        return Inertia::render('Targets', [
            'syncedTargets'    => $syncedTargets,
            'otherTargets'     => $otherTargets,
            'allAssets'        => $assets, // For new target dropdown selection
            'recentActivities' => $activities,
            'stats'            => $stats,
            'wallet'           => $wallet ? [
                'id'       => $wallet->id,
                'balance'  => $wallet->balance,
                'currency' => $wallet->currency ?? 'USD',
                'credits'  => $wallet->credits ?? 0,
            ] : null,
        ]);
    }
}
