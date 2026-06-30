<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    /**
     * The catalogue of all supported integrations.
     * These are the "available" platforms; connected ones are loaded from the DB.
     */
    private const PLATFORM_CATALOGUE = [
        'github'     => ['name' => 'GitHub',           'description' => 'Import repositories, monitor commits, issues and security alerts.',               'icon' => 'github'],
        'gitlab'     => ['name' => 'GitLab',            'description' => 'Import projects and scan pipelines for security misconfigurations.',              'icon' => 'gitlab'],
        'slack'      => ['name' => 'Slack',             'description' => 'Get real-time alerts and scan notifications in your Slack channels.',             'icon' => 'slack'],
        'jira'       => ['name' => 'Jira',              'description' => 'Create and sync security tickets automatically.',                                  'icon' => 'jira'],
        'google'     => ['name' => 'Google Workspace',  'description' => 'Use Google accounts for authentication and team management.',                      'icon' => 'google'],
        'azure'      => ['name' => 'Azure DevOps',      'description' => 'Import repositories, pipelines and work items.',                                  'icon' => 'azure'],
        'bitbucket'  => ['name' => 'Bitbucket',         'description' => 'Import repositories and monitor pull requests.',                                   'icon' => 'bitbucket'],
        'discord'    => ['name' => 'Discord',           'description' => 'Receive alerts and scan summaries in Discord.',                                    'icon' => 'discord'],
        'pagerduty'  => ['name' => 'PagerDuty',         'description' => 'Integrate alerts with incident management.',                                       'icon' => 'pagerduty'],
        'snyk'       => ['name' => 'Snyk',              'description' => 'Import vulnerability data and scan results.',                                      'icon' => 'snyk'],
        'webhook'    => ['name' => 'Custom Webhook',    'description' => 'Send scan results to your own endpoint.',                                          'icon' => 'webhook'],
    ];

    /**
     * Render the Integrations page with real database-driven data.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Fetch all of this user's integrations with their recent logs
        $userIntegrations = Integration::where('user_id', $user->id)
            ->with(['logs' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(20);
            }])
            ->orderBy('connected_at', 'desc')
            ->get();

        // 2. Build connected integrations list for the frontend
        $connectedIntegrations = $userIntegrations
            ->where('status', 'connected')
            ->map(fn (Integration $i) => [
                'id'        => $i->platform,
                'name'      => $i->name,
                'status'    => 'Connected',
                'desc'      => $i->description,
                'addedBy'   => $i->added_by ?? $user->name,
                'addedDate' => $i->connected_at?->format('M d, Y') ?? 'N/A',
                'lastSync'  => $i->lastSyncForHumans(),
                'icon'      => $i->platform,
            ])
            ->values()
            ->toArray();

        // 3. Build health statuses for sidebar — all user integrations including non-connected
        $healthStatuses = $userIntegrations->map(fn (Integration $i) => [
            'name'   => $i->name,
            'status' => ucfirst($i->status),
            'color'  => $i->statusColor(),
        ])->values()->toArray();

        // 4. Build activity log from integration logs
        $activityLog = IntegrationLog::where('user_id', $user->id)
            ->with('integration')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn (IntegrationLog $log) => [
                'title' => $log->integration?->name ?? 'Integration',
                'desc'  => $log->description,
                'time'  => $log->created_at->diffForHumans(),
                'error' => $log->status === 'error',
            ])
            ->toArray();

        // 5. Build available integrations (catalogue entries not yet connected by this user)
        $connectedPlatforms = $userIntegrations->pluck('platform')->toArray();
        $availableIntegrations = collect(self::PLATFORM_CATALOGUE)
            ->filter(fn ($_, $slug) => ! in_array($slug, $connectedPlatforms))
            ->map(fn ($data, $slug) => [
                'id'   => $slug,
                'name' => $data['name'],
                'desc' => $data['description'],
                'icon' => $data['icon'],
            ])
            ->values()
            ->toArray();

        // 6. Compute metric stats
        $connectedCount = $userIntegrations->where('status', 'connected')->count();
        $availableCount = count(self::PLATFORM_CATALOGUE) - $connectedCount;

        // System health percentage: connected integrations that are healthy vs total connected
        $healthyCount = $userIntegrations->where('status', 'connected')->count();
        $nonHealthyCount = $userIntegrations->whereIn('status', ['error', 'warning'])->count();
        $totalTracked = max($healthyCount + $nonHealthyCount, 1);
        $systemHealthPct = (int) round(($healthyCount / $totalTracked) * 100);

        // Events synced: count of log entries in the last 7 days
        $eventsSynced = IntegrationLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $stats = [
            'connected'      => $connectedCount,
            'available'      => $availableCount,
            'systemHealth'   => $systemHealthPct . '%',
            'allHealthy'     => $nonHealthyCount === 0,
            'eventsSynced'   => $eventsSynced,
        ];

        return Inertia::render('Integrations', [
            'connectedIntegrations' => $connectedIntegrations,
            'availableIntegrations' => $availableIntegrations,
            'healthStatuses'        => $healthStatuses,
            'activityLog'           => $activityLog,
            'stats'                 => $stats,
        ]);
    }

    /**
     * Toggle the connection state of an integration.
     * If the integration is currently connected, disconnect it.
     * If not connected (or not yet in the DB), create/update it as connected.
     */
    public function toggle(Request $request, string $platform): RedirectResponse
    {
        $request->validate([
            'credentials' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        // Validate platform exists in catalogue
        if (! array_key_exists($platform, self::PLATFORM_CATALOGUE)) {
            return back()->withErrors(['platform' => 'Unknown integration platform.']);
        }

        $catalogueEntry = self::PLATFORM_CATALOGUE[$platform];

        // Find or create the integration record
        $integration = Integration::firstOrNew([
            'user_id'  => $user->id,
            'platform' => $platform,
        ]);

        if ($integration->status === 'connected') {
            // Disconnect
            $integration->status = 'disconnected';
            $integration->save();

            // Log the disconnection
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'user_id'        => $user->id,
                'event'          => 'disconnect',
                'description'    => $catalogueEntry['name'] . ' was disconnected.',
                'status'         => 'success',
            ]);
        } else {
            // Connect — fill in all required fields
            $integration->fill([
                'name'         => $catalogueEntry['name'],
                'description'  => $catalogueEntry['description'],
                'status'       => 'connected',
                'added_by'     => $user->name,
                'connected_at' => now(),
                'last_synced_at' => now(),
                // Credentials are encrypted automatically by the model cast
                'credentials'  => $request->input('credentials'),
            ]);
            $integration->save();

            // Log the connection
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'user_id'        => $user->id,
                'event'          => 'connect',
                'description'    => $catalogueEntry['name'] . ' was connected successfully.',
                'status'         => 'success',
            ]);
        }

        return back()->with('success', 'Integration updated successfully.');
    }
}
