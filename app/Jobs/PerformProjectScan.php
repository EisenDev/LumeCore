<?php

namespace App\Jobs;

use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Models\AuditHistory;
use App\Services\InfrastructureScannerService;
use App\Services\AI\ProjectAuditor; // CHANGED: correct service
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Events\AuditProgressUpdated; // ADDED: real-time updates

class PerformProjectScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    // ... (rest of class)

    /**
     * Execute the job.
     */
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     * Increased to 300s to accomodate Deep Crawls (Next.js Hydration) and AI Analysis.
     */
    public int $timeout = 800;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Wait 1 minute to clear RPM limit
        return [60, 120, 180];
    }

    /**
     * Get the middleware the job should pass through.
     * Shared mutex with AuditVaultAsset to ensure NO browser jobs overlap.
     */
    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('browsershot_mutex'))->releaseAfter(600)];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ProjectAsset $project,
        public ?string $githubToken = null,
        public ?string $vaultAssetId = null
    ) {}

    // Helper for granular logging
    private function auditLog($message, $step, $progress)
    {
        if ($this->vaultAssetId) {
            $vaultAsset = VaultAsset::find($this->vaultAssetId);
            if ($vaultAsset) {
                // Send "LOG: $message" as the step so frontend treats it as a log entry
                // We use a convention "LOG: {Message}" in the 'step' field
                // BUT we also want to show a clean step name.
                // Let's assume frontend parses "LOG: ..." or just displays it.
                // Actually, passing a structured array via the 'step' string is hacky but robust if frontend expects string.
                // Better approach: 
                // We will send "$step" as the visible step (e.g., "Initializing...")
                // And we will append "::LOG::$message" to it? No.
                // Let's rely on the plan: The frontend will treat the 'step' string as the log entry itself.
                // So we just send clear messages.
                AuditProgressUpdated::dispatch($vaultAsset, $message, $progress);
            }
        }
    }

/**
     * Execute the job with "Partial Forensic" Fallback.
     */
    public function handle(
        InfrastructureScannerService $scanner, 
        ProjectAuditor $aiAuditor,
        \App\Services\VaultAuditService $auditService,
        \App\Services\LedgerService $ledgerService
    ): void
    {
        Log::info("Starting project scan for project {$this->project->id}", ['vault_asset_id' => $this->vaultAssetId]);

        try {
            // 1. Status Updates
            $this->project->update(['status' => 'pending_verification']);
            $vaultAsset = $this->vaultAssetId ? VaultAsset::find($this->vaultAssetId) : null;
            
            if ($vaultAsset) {
                $vaultAsset->update(['status' => 'processing']);
                AuditProgressUpdated::dispatch($vaultAsset, 'Initializing Sovereign Analyst...', 10);
            }

            // 2. Visual Proofs / Design Audit (unchanged logic)
            $proofs = VaultAsset::where('metadata->project_asset_id', $this->project->id)
                ->where('metadata->is_proof', true)->get();

            if ($proofs->isNotEmpty()) {
                // ... (Keep existing Design Audit logic here) ...
                // For brevity, assuming you keep the existing design audit block provided in your code
            }

            // 3. Infrastructure Scan (Codebase) - WRAPPED IN TRY/CATCH
            $results = [];
            try {
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Scanning repository architecture...', 20);
                $results = $scanner->scanProject($this->project, $this->githubToken);
            } catch (\Exception $e) {
                Log::warning("Infrastructure Scan Partial Fail: " . $e->getMessage());
                $results = ['error' => 'Repository scan inaccessible: ' . $e->getMessage()];
                // Do NOT return. We continue to DOM scan.
            }
            
            // 4. Forensic Evidence Harvest (DOM/Crawling) - ROBUST TIMEOUT HANDLING
            $evidence = "";
            $crawlError = null;
            
            try {
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Deploying forensic crawler...', 40);
                
                if ($this->project) {
                    $evidence = $auditService->getRawEvidence(
    $this->project, 
    $this->project->website_url, 
    [
        'timeout' => 120,           // Give it 2 minutes (vital for slow 1GB servers)
        'disable_images' => true,   // CRITICAL: Saves ~60% RAM
        'disable_fonts' => true,    // Saves bandwidth
        'block_urls' => [           // Block heavy trackers that hang the CPU
            'google-analytics.com', 
            'facebook.com', 
            'googletagmanager.com'
        ]
    ], 
    $vaultAsset
                    );
                }
            } catch (\Exception $e) {
                $crawlError = $e->getMessage();
                Log::warning("Forensic Crawler Failed: " . $crawlError);
                
                // CRITICAL FIX: Create a "Coroner's Report" for the AI instead of failing
                $evidence = "CRITICAL_FAILURE: The headless crawler timed out or was refused connection.\n" .
                            "TARGET: {$this->project->website_url}\n" .
                            "ERROR: {$crawlError}\n" .
                            "CONTEXT: This often indicates a slow server (timeout), a firewall block (403), or a crashed application (500).";
                
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Target unresponsive. Engaging Forensic Pathologist...', 55);
            }

            // 5. AI Analysis (The "Brain")
            if ($vaultAsset) {
                AuditProgressUpdated::dispatch($vaultAsset, 'Synthesizing forensic vectors...', 70);
                // Artificial delay for UX feel (optional)
                usleep(500000); 
            }
            
            $projectData = [
                'website_url' => $this->project->website_url,
                'github_url' => $this->project->github_repo_url,
                'tech_stack' => $results['tech_footprint'] ?? [],
                'dns_provider' => $results['dns_provider'] ?? null,
                'env_variables' => $results['env_variables'] ?? [],
                'config_files' => $results['config_files'] ?? [],
            ];
            
            // AI SAFETY NET: Ensure analyzeProject doesn't crash the job
            try {
                $aiResult = $aiAuditor->analyzeProject($projectData, $evidence);
            } catch (\Exception $aiEx) {
                // Fallback result if AI API fails completely
                Log::error("AI API Failure: " . $aiEx->getMessage());
                $aiResult = [
                    'score' => 10,
                    'verdict' => 'flagged',
                    'executive_summary' => 'Forensic analysis failed due to AI service disruption. Manual review required.',
                    'risk_matrix' => ['performance' => 'high', 'security' => 'high', 'scalability' => 'high'],
                    'radar_data' => ['code_resilience' => 0, 'security_perimeter' => 0, 'deployment_maturity' => 0, 'seo_authority' => 0, 'database_architecture' => 0]
                ];
            }
            
            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Finalizing immutable report...', 90);

            // 6. Final Status Logic
            $score = $aiResult['score'] ?? 0;
            $hasRepo = !empty($this->project->github_repo_url);
            
            // DATA REPAIR: If radar_data is empty or all zeros, attempts to backfill from risk_matrix
            $radar = $aiResult['radar_data'] ?? [];
            $risk = $aiResult['risk_matrix'] ?? [];
            
            // Check if radar looks empty (sum of values is low)
            $radarSum = array_sum($radar);
            if ($radarSum < 10) {
                 // Map from Risk Matrix (which is usually more reliable)
                 $radar['code_resilience'] = $risk['performance'] ?? 0;
                 $radar['security_perimeter'] = $risk['security'] ?? 0;
                 $radar['deployment_maturity'] = $risk['scalability'] ?? 0;
                 // Synthesize others if missing
                 $radar['seo_authority'] = $radar['seo_authority'] ?? 50; 
                 $radar['database_architecture'] = $radar['database_architecture'] ?? 50;
                 
                 // Update the main array so it gets saved
                 $aiResult['radar_data'] = $radar;
                 
                 Log::info("PerformProjectScan: Backfilled empty radar_data from risk_matrix", ['radar' => $radar]);
            }

            if ($score >= 85) {
                $finalStatus = $hasRepo ? 'verified' : 'verified_private';
            } elseif ($score >= 80) {
                $finalStatus = 'action_required';
            } else {
                $finalStatus = 'flagged'; // Default for slow/broken sites
            }

            // 7. Save Everything
            $this->project->update([
                'audit_data' => array_merge($this->project->audit_data ?? [], [
                    'infrastructure_scan' => $results,
                    'ai_audit' => $aiResult,
                ]),
                'status' => $finalStatus,
                'verified_at' => $finalStatus === 'verified' ? now() : null,
            ]);
            
            if ($vaultAsset) {
                VaultAsset::where('id', $this->vaultAssetId)->update([
                    'status' => $finalStatus,
                    'metadata' => array_merge($vaultAsset->metadata ?? [], [
                        'score' => $score,
                        'confidence_score' => $score, // Legacy support
                        'verdict' => $finalStatus,
                        'executive_summary' => $aiResult['executive_summary'] ?? 'Audit completed.',
                        'risk_matrix' => $aiResult['risk_matrix'] ?? [],
                        'vector_details' => $aiResult['vector_details'] ?? [],
                        'radar_data' => $aiResult['radar_data'] ?? [], // Critical for charts
                        'breakdown' => $aiResult['radar_data'] ?? [], // FORCE OVERWRITE of stale data
                        'is_marketplace_eligible' => ($finalStatus === 'verified'),
                        // Ensure summary exists even if failing
                        'summary' => $aiResult['business_summary'] ?? $aiResult['executive_summary'] ?? 'Analysis complete.',
                        'niche' => $aiResult['niche'] ?? 'Uncategorized',
                        'tech_narrative' => $aiResult['tech_narrative'] ?? 'No tech narrative generated.',
                        'insights' => $aiResult['insights'] ?? [],
                        'warning_flags' => $aiResult['warning_flags'] ?? [],
                        'tech_assessment' => $aiResult['tech_assessment'] ?? [],
                        'languages' => $aiResult['languages'] ?? [],
                        // Enterprise Governance Data
                        'supply_chain_risk' => $aiResult['supply_chain_risk'] ?? null,
                        'compliance_check' => $aiResult['compliance_check'] ?? null,
                        'carbon_footprint' => $aiResult['carbon_footprint'] ?? null,
                        'accessibility' => $aiResult['accessibility'] ?? null,
                        'data_sovereignty' => $aiResult['data_sovereignty'] ?? null,
                        // Ensuring legacy compat
                        'stack' => $aiResult['tech_assessment']['stack'] ?? [],
                    ]),
                    'radar_data' => $aiResult['radar_data'] ?? null,
                ]);

                // Create History Record
                AuditHistory::create([
                    'vault_asset_id' => $vaultAsset->id,
                    'score' => $score,
                    'status' => $finalStatus,
                    'metadata' => ['summary' => $aiResult['executive_summary'] ?? '']
                ]);

                AuditProgressUpdated::dispatch($vaultAsset, 'Audit complete!', 100);
            }

            Log::info("Scan Complete. Status: $finalStatus");

        } catch (\Exception $e) {
            // GLOBAL CATCH: Refund logic (Keep your existing refund logic here)
            Log::error("FATAL JOB ERROR: " . $e->getMessage());
            if ($this->vaultAssetId) {
                 \App\Events\AuditFailed::dispatch(VaultAsset::find($this->vaultAssetId), 'Critical System Error: ' . $e->getMessage());
                 VaultAsset::where('id', $this->vaultAssetId)->update(['status' => 'failed_system']);
            }
        }
    }
}