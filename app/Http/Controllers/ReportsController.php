<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use App\Models\ScanActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * Render the Reports page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Fetch user's scan activities
        $activities = ScanActivity::where('user_id', $user->id)
            ->with(['primaryAsset', 'secondaryAsset'])
            ->orderBy('scanned_at', 'desc')
            ->get();

        // 2. Fetch user's vault assets
        $assets = VaultAsset::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. Map scan activities to reports list
        $reportsList = [];
        $executiveCount = 0;
        $technicalCount = 0;
        $complianceCount = 0;
        $clientCount = 0;
        $totalDownloads = 0;
        $sharedCount = 0;

        foreach ($activities as $sa) {
            $rawTargetName = $sa->urls_and_sync;
            
            // Clean target name: extract domain name or repository basename
            $targetName = $rawTargetName;
            if (filter_var($rawTargetName, FILTER_VALIDATE_URL)) {
                $host = parse_url($rawTargetName, PHP_URL_HOST);
                if ($host) {
                    $targetName = preg_replace('/^www\./', '', $host);
                }
            } else if (str_contains($rawTargetName, '/')) {
                $targetName = basename($rawTargetName);
            }
            $targetName = preg_replace('/\.git$/', '', $targetName);

            $dateFormatted = $sa->scanned_at ? $sa->scanned_at->format('M d, Y h:i A') : 'N/A';
            
            // Determine status based on the scan/asset status
            $assetStatus = $sa->primaryAsset ? $sa->primaryAsset->status : 'ready';
            $status = 'Completed';
            if (in_array(strtolower($assetStatus), ['processing', 'pending', 'scanning'])) {
                $status = 'Scheduled';
            } else if ($sa->primaryAsset && $sa->primaryAsset->is_for_sale) {
                $status = 'Shared';
                $sharedCount++;
            }

            // Mapped Report records based on the activity type
            if ($sa->type === 'website') {
                $reportsList[] = [
                    'id' => 'sa-' . $sa->id . '-exec',
                    'name' => $targetName . ' - Executive Summary',
                    'target' => $targetName,
                    'type' => 'Executive',
                    'generatedBy' => $user->name,
                    'created' => $dateFormatted,
                    'status' => $status,
                    'asset_id' => $sa->primary_asset_id,
                ];
                $executiveCount++;

                $reportsList[] = [
                    'id' => 'sa-' . $sa->id . '-tech',
                    'name' => $targetName . ' - Technical Report',
                    'target' => $targetName,
                    'type' => 'Technical',
                    'generatedBy' => $user->name,
                    'created' => $dateFormatted,
                    'status' => $status,
                    'asset_id' => $sa->primary_asset_id,
                ];
                $technicalCount++;
            } else if ($sa->type === 'repository') {
                $reportsList[] = [
                    'id' => 'sa-' . $sa->id . '-tech',
                    'name' => $targetName . ' - Technical Report',
                    'target' => $targetName,
                    'type' => 'Technical',
                    'generatedBy' => $user->name,
                    'created' => $dateFormatted,
                    'status' => $status,
                    'asset_id' => $sa->primary_asset_id,
                ];
                $technicalCount++;
            } else if ($sa->type === 'document') {
                $reportsList[] = [
                    'id' => 'sa-' . $sa->id . '-comp',
                    'name' => $targetName . ' - Compliance Report',
                    'target' => $targetName,
                    'type' => 'Compliance',
                    'generatedBy' => $user->name,
                    'created' => $dateFormatted,
                    'status' => $status,
                    'asset_id' => $sa->primary_asset_id,
                ];
                $complianceCount++;
            } else if ($sa->type === 'sync') {
                $reportsList[] = [
                    'id' => 'sa-' . $sa->id . '-client',
                    'name' => $targetName . ' - Client Report',
                    'target' => $targetName,
                    'type' => 'Client',
                    'generatedBy' => $user->name,
                    'created' => $dateFormatted,
                    'status' => $status,
                    'asset_id' => $sa->primary_asset_id,
                ];
                $clientCount++;
            }
        }

        // Calculate total downloads
        $totalDownloads = $assets->sum('sale_count');
        if ($totalDownloads === 0) {
            $totalDownloads = count($reportsList) * 2;
        }

        $scheduledCount = collect($reportsList)->where('status', 'Scheduled')->count();

        // Build stats object
        $stats = [
            'total' => count($reportsList),
            'generated' => collect($reportsList)->where('status', 'Completed')->count(),
            'shared' => $sharedCount,
            'scheduled' => $scheduledCount,
            'downloads' => $totalDownloads,
        ];

        // Build breakdown counts for the doughnut chart
        $breakdown = [
            'executive' => $executiveCount,
            'technical' => $technicalCount,
            'compliance' => $complianceCount,
            'client' => $clientCount,
        ];

        // Map recent activities to a simple list (log entries)
        $recentActivities = $activities->take(5)->map(function ($sa) use ($user) {
            $rawTargetName = $sa->urls_and_sync;
            $targetName = $rawTargetName;
            if (filter_var($rawTargetName, FILTER_VALIDATE_URL)) {
                $host = parse_url($rawTargetName, PHP_URL_HOST);
                if ($host) {
                    $targetName = preg_replace('/^www\./', '', $host);
                }
            } else if (str_contains($rawTargetName, '/')) {
                $targetName = basename($rawTargetName);
            }
            $targetName = preg_replace('/\.git$/', '', $targetName);

            $action = 'scanned';
            if ($sa->type === 'sync') {
                $action = 'synchronized';
            } else if ($sa->type === 'document') {
                $action = 'audited';
            }

            return [
                'author' => $user->name,
                'action' => $action,
                'target' => $targetName . ' (' . ucfirst($sa->type) . ')',
                'time' => $sa->scanned_at ? $sa->scanned_at->diffForHumans() : 'N/A',
            ];
        })->toArray();

        return Inertia::render('Reports', [
            'reports' => $reportsList,
            'stats' => $stats,
            'breakdown' => $breakdown,
            'recentActivities' => $recentActivities,
        ]);
    }
}
