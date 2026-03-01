<?php

namespace App\Jobs;

use App\Models\ProjectAsset;
use App\Services\HealthCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HealthCheckListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * Execute the job.
     * Runs every 60 minutes to check all listed project URLs.
     */
    public function handle(HealthCheckService $healthService): void
    {
        Log::info('HealthCheckListingsJob: Starting health check sweep');

        // Get all listed projects with website URLs
        $listedProjects = ProjectAsset::where('status', 'listed')
            ->whereNotNull('website_url')
            ->get();

        $problemsFound = 0;
        $delistedCount = 0;

        foreach ($listedProjects as $project) {
            try {
                $this->checkProject($project, $healthService, $problemsFound, $delistedCount);
            } catch (\Exception $e) {
                Log::error("HealthCheckListingsJob: Failed to check project {$project->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("HealthCheckListingsJob: Completed. Checked {$listedProjects->count()} projects. Problems: {$problemsFound}. Delisted: {$delistedCount}");
    }

    /**
     * Check a single project's health.
     */
    protected function checkProject(
        ProjectAsset $project, 
        HealthCheckService $healthService,
        int &$problemsFound,
        int &$delistedCount
    ): void {
        $issues = [];

        // Check website health
        if ($project->website_url) {
            $websiteCheck = $healthService->checkWebsite($project->website_url);
            
            // Check for 5xx errors
            if (!$websiteCheck['alive']) {
                $issues[] = [
                    'type' => 'website_down',
                    'message' => 'Website is not responding',
                    'details' => $websiteCheck['error'] ?? 'Connection failed',
                ];
            } elseif ($websiteCheck['status_code'] && $websiteCheck['status_code'] >= 500) {
                $issues[] = [
                    'type' => 'server_error',
                    'message' => "Website returning {$websiteCheck['status_code']} error",
                    'details' => "HTTP status code: {$websiteCheck['status_code']}",
                ];
            }

            // Check SSL validity
            if ($websiteCheck['ssl_valid'] === false) {
                $issues[] = [
                    'type' => 'ssl_invalid',
                    'message' => 'SSL certificate is invalid or expired',
                    'details' => 'HTTPS connection failed verification',
                ];
            }

            // Check domain expiration via WHOIS
            $domainCheck = $healthService->checkDomainWhois($project->website_url);
            
            if ($domainCheck['days_until_expiry'] !== null) {
                if ($domainCheck['days_until_expiry'] <= 0) {
                    $issues[] = [
                        'type' => 'domain_expired',
                        'message' => 'Domain has expired',
                        'details' => "Expiration date: {$domainCheck['expiration_date']}",
                    ];
                } elseif ($domainCheck['days_until_expiry'] <= 7) {
                    $issues[] = [
                        'type' => 'domain_expiring_soon',
                        'message' => "Domain expires in {$domainCheck['days_until_expiry']} days",
                        'details' => "Expiration date: {$domainCheck['expiration_date']}",
                    ];
                }
            }
        }

        // If critical issues found, delist the project
        if (!empty($issues)) {
            $problemsFound++;
            
            $criticalIssues = array_filter($issues, fn($i) => 
                in_array($i['type'], ['website_down', 'server_error', 'domain_expired'])
            );

            if (!empty($criticalIssues)) {
                // Delist the project
                $project->update([
                    'status' => 'verified', // Back to verified but not listed
                    'health_data' => array_merge($project->health_data ?? [], [
                        'last_check' => now()->toIso8601String(),
                        'issues' => $issues,
                        'auto_delisted' => true,
                        'delisted_at' => now()->toIso8601String(),
                    ]),
                ]);

                $delistedCount++;

                // Send email notification to seller
                $this->notifySeller($project, $issues);

                Log::warning("HealthCheckListingsJob: Delisted project {$project->id} due to issues", [
                    'project_name' => $project->name,
                    'issues' => $issues,
                ]);
            } else {
                // Just update health data with warnings
                $project->update([
                    'health_data' => array_merge($project->health_data ?? [], [
                        'last_check' => now()->toIso8601String(),
                        'warnings' => $issues,
                    ]),
                ]);
            }
        } else {
            // All good - update last check time
            $project->update([
                'health_data' => array_merge($project->health_data ?? [], [
                    'last_check' => now()->toIso8601String(),
                    'status' => 'healthy',
                    'issues' => [],
                ]),
            ]);
        }
    }

    /**
     * Send notification email to the seller about listing issues.
     */
    protected function notifySeller(ProjectAsset $project, array $issues): void
    {
        try {
            $user = $project->user;
            if (!$user || !$user->email) {
                return;
            }

            $issueList = implode("\n", array_map(fn($i) => "- {$i['message']}: {$i['details']}", $issues));

            // Send notification (using simple mail for now)
            Mail::raw(
                "Dear {$user->name},\n\n" .
                "Your LUME listing \"{$project->name}\" has been automatically delisted due to the following issues:\n\n" .
                "{$issueList}\n\n" .
                "Please resolve these issues and re-list your project from your dashboard.\n\n" .
                "Website URL: {$project->website_url}\n\n" .
                "This is an automated message from the LUME Health Bot.\n\n" .
                "Best regards,\nThe LUME Team",
                function ($message) use ($user, $project) {
                    $message->to($user->email)
                        ->subject("LUME Alert: Your listing \"{$project->name}\" has been delisted");
                }
            );

            Log::info("HealthCheckListingsJob: Notified seller about delisting", [
                'user_id' => $user->id,
                'project_id' => $project->id,
            ]);

        } catch (\Exception $e) {
            Log::error("HealthCheckListingsJob: Failed to notify seller", [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);
        }
    }
}
