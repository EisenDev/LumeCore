<?php

namespace App\Jobs;

use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Models\AuditHistory;
use App\Services\InfrastructureScannerService;
use App\Services\AI\GithubRepositoryAuditor;
use App\Services\GithubFlattenerService;
use App\Services\LedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Events\AuditProgressUpdated;
use App\Events\AssetStatusUpdated;
use App\Events\AuditFailed;

/**
 * PerformGithubRepositoryScan Job
 * 
 * Specialized scan for Code Repositories only (Zero-Website Crawl).
 * - Focuses on Code Quality, Architecture, and Security via Static Analysis.
 * - Uses Github API to flatten codebase for AI.
 */
class PerformGithubRepositoryScan implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Retries Removed by User Request
    public int $timeout = 1200; // 20 minutes (Allow for long backoffs)

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    /*
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }
    */

    public function __construct(
        public ProjectAsset $project,
        public ?string $githubToken = null,
        public ?string $vaultAssetId = null, // Original Asset ID (for cloning)
        public bool $suppressActivityLog = false,
        public ?string $auditContext = null,
        public ?string $syncBatchId = null // TITAN V2: Context Isolation
    ) {}




    public function handle(
        InfrastructureScannerService $scanner, 
        GithubRepositoryAuditor $auditor,
        GithubFlattenerService $flattener,
        \App\Services\GitForensicsService $forensics,
        LedgerService $ledgerService
    ): void {
        Log::info("LUME TITAN: Starting GITHUB REPO SCAN for project {$this->project->id}", ['vault_asset_id' => $this->vaultAssetId]);

        $vaultAsset = $this->vaultAssetId ? VaultAsset::find($this->vaultAssetId) : null;

        // TITAN V2: Context Isolation Logic
        // If syncBatchId is present, we DO NOT use the original asset. We create a new "Context-Aware" asset.
        // TITAN V2: Context Isolation Logic
        // If syncBatchId is present, we DO NOT use the original asset. We create a new "Context-Aware" asset.
        // TITAN V2: Context Isolation Logic
        // The ProjectController now generates the Batch ID and creates/updates the specific Sync Asset BEFORE dispatching.
        // So $vaultAsset should ALREADY be the correct, isolated asset.
        
        if ($this->syncBatchId && $vaultAsset) {
            // VERIFICATION: Ensure the asset we received actually belongs to this batch
            if ($vaultAsset->batch_id === $this->syncBatchId) {
                // Perfect. We are working on the correct isolated asset.
                Log::info("PerformGithubRepositoryScan: Using verified Context-Isolated Asset", ['id' => $vaultAsset->id, 'batch' => $this->syncBatchId]);
            } else {
                 // FALLBACK: If for some reason we got a non-batched asset (legacy or race condition),
                 // we MUST clone it to preserve isolation.
                 Log::warning("PerformGithubRepositoryScan: Received Non-Batched Asset in Sync Job. Cloning...", ['id' => $vaultAsset->id]);
                 
                 $originalAsset = $vaultAsset;
                 $vaultAsset = VaultAsset::create([
                    'user_id' => $originalAsset->user_id,
                    'file_name' => $originalAsset->file_name, // Keep same name for UI consistency
                    'file_path' => $originalAsset->file_path,
                    'file_size' => $originalAsset->file_size,
                    'mime_type' => $originalAsset->mime_type,
                    'status' => 'processing',
                    'batch_id' => $this->syncBatchId, // LINK TO SYNC BATCH
                    'metadata' => array_merge($originalAsset->metadata ?? [], [
                        'audit_type' => 'repository_scan',
                        'context' => 'sync_child',
                        'parent_asset_id' => $originalAsset->id
                    ])
                ]);
                // Update the Job's reference to the new asset
                $this->vaultAssetId = $vaultAsset->id;
            }
        }
        
        $localRepoPath = null;
        $fingerprintHash = null;

        try {
            // 1. INITIALIZE STATUS
            $this->project->update(['status' => 'pending_verification']);
            if ($vaultAsset) {
                $vaultAsset->update([
                    'status' => 'processing',
                    // CRITICAL: Set type immediately so Dashboard knows which modal to use even if it fails later
                    'metadata' => array_merge($vaultAsset->metadata ?? [], ['audit_type' => 'repository_scan'])
                ]);
                // EXPLICITLY BROADCAST STATUS to ensure Frontend gets the new audit_type immediately
                AssetStatusUpdated::dispatch($vaultAsset);
                
                AuditProgressUpdated::dispatch($vaultAsset, 'Initializing Source Code Analyst...', 5);
            }

            // Resolve Token
            $resolvedToken = $this->githubToken;
            if (empty($resolvedToken) && method_exists($this->project, 'getGithubToken')) {
                $resolvedToken = $this->project->getGithubToken();
            }

            // 2. DNA RECONNAISSANCE (Dependencies) via API (Optional, but safe to keep for manifest checks)
            // Skip for now, we will get tech stack from AI analysis of files
            $dnaEvidence = [];

            // 3. CLONE & FORENSICS (The Deep Search)
            $flattenedContext = "";
            $forensicData = [];

            // PRIORITY: Check Metadata/Asset first (Specific overrides General)
            $repoUrl = null;
            
            if ($vaultAsset) {
                // Check asset filename (often the URL)
                if (str_contains($vaultAsset->file_name, 'github.com')) {
                    $repoUrl = $vaultAsset->file_name;
                }
                // Check metadata
                if (!empty($vaultAsset->metadata['github_repo_url'])) {
                    $repoUrl = $vaultAsset->metadata['github_repo_url'];
                }
            }
            
            // Fallback: Use Project Default URL
            if (empty($repoUrl)) {
                 $repoUrl = $this->project->github_repo_url;
            }

            if (!$repoUrl) {
                throw new \Exception("No Github Repository URL provided.");
            }

            try {
                // A. CLONE
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Git: Cloning Repository (This may take a moment)...', 10);
                
                $localRepoPath = $forensics->clone($repoUrl, $resolvedToken);
                
                // B. FORENSIC ANALYSIS
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Forensics: Analyzing Churn & Complexity...', 25);
                $forensicData['toxicity'] = $forensics->getChurnMetrics($localRepoPath);
                
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Forensics: Calculating Bus Factor...', 35);
                $forensicData['bus_factor'] = $forensics->getBusFactorStats($localRepoPath);

                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Forensics: Measuring Development Velocity...', 40);
                $forensicData['pulse'] = $forensics->getPulseData($localRepoPath);
                Log::info("TITAN DEBUG: Forensics Complete", ['data_keys' => array_keys($forensicData)]);

                // Sovereign Fingerprint (Duplicate Detection)
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Sovereign: Generating Digital Fingerprint...', 45);
                $fingerprintHash = $forensics->getFingerprint($localRepoPath);

                // TITAN V5.5: Capture Raw Commit Hash for Assurance Protocol
                $process = new \Symfony\Component\Process\Process(['git', 'rev-parse', 'HEAD'], $localRepoPath);
                $process->run();
                $headCommitSha = trim($process->getOutput()) ?: 'unknown';

                // C. FLATTEN CONTEXT (From Local)
                 if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Harvester: Reading Codebase (Local Scan)...', 50);
                 Log::info("TITAN DEBUG: Starting Local Flattening...", ['path' => $localRepoPath]);

                $flattenedContext = $flattener->flattenLocalRepo(
                    $localRepoPath,
                    function ($msg, $percent) use ($vaultAsset) {
                        // TITAN: Throttle updates to prevent WebSocket flooding/Frontend Crash
                        static $lastUpdate = 0;
                        if (microtime(true) - $lastUpdate < 0.2) return; // Allow 5 updates/sec max
                        $lastUpdate = microtime(true);

                        // Rescale 0-100 local progress to 50-80 global progress
                        $globalPercent = 50 + ($percent * 0.3);
                        // Send "msg" (Reading: file.php) as DETAILS, keep Step stable
                        if ($vaultAsset) AuditProgressUpdated::dispatch(
                            $vaultAsset, 
                            "Analyzing Source Code Structure...", 
                            (int) $globalPercent,
                            'processing',
                            $msg // Details as 5th argument
                        );
                    }
                );
                Log::info("TITAN DEBUG: Flattening Complete.", ['length' => strlen($flattenedContext)]);

            } catch (\Exception $e) {
            } catch (\Exception $e) {
                 Log::error("Code Forensics Failed: " . $e->getMessage());
                 if ($vaultAsset) AuditFailed::dispatch($vaultAsset, 'Forensic System Error: ' . $e->getMessage());
                 throw $e; // Re-throw to trigger catch block
            }



            // 4. AI ANALYSIS (The Brain)
            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Auditor: Analyzing Architectural Integrity...', 85);

            $metadata = [
                'name' => $this->project->name ?? 'Repository',
                'description' => $this->project->description ?? 'No description',
                'url' => $repoUrl,
                'forensics' => $forensicData // PASS FORENSIC DATA TO AI AUDITOR
            ];

            try {
                // AI Call
                Log::info("TITAN DEBUG: Dispatching to AI Auditor...", ['context_length' => strlen($flattenedContext)]);
                $aiResult = $auditor->analyzeRepo($flattenedContext, $metadata);
                Log::info("TITAN DEBUG: AI Analysis Returned.", ['score' => $aiResult['score'] ?? 'N/A']);
            } catch (\Exception $e) {
                 // Check for Rate Limits inside the AI specific block
                 if ($e->getCode() === 429 || $e->getCode() === 503 || str_contains(strtolower($e->getMessage()), 'overloaded')) {
                     throw $e; // Re-throw to be caught by main catch as transient
                 }
                 // Otherwise wrap and rethrow
                 throw new \Exception("AI Analysis Failed: " . $e->getMessage());
            }

            // Merge Forensic Data into AI Result so it's saved in metadata
            $aiResult['forensics'] = $forensicData;

            // 5. POST-PROCESSING
            // Map 'code_quality' to insight flags
            if (!empty($aiResult['code_quality']['major_issues'])) {
                $aiResult['warning_flags'] = array_merge($aiResult['warning_flags'] ?? [], $aiResult['code_quality']['major_issues']);
            }
            
            // Map Risk Matrix (Code Repo focus)
            // TITAN V5.3: Standardized Risk Matrix Calculation (Same as ProjectScan)
            $vectors = $aiResult['hexagon_vectors'] ?? [];
            $v = [];
            foreach ($vectors as $k => $val) $v[strtolower(str_replace(' ', '_', $k))] = (int)$val;

            $v_p1 = $v['client_side_velocity'] ?? 0;
            $v_p2 = $v['code_efficiency'] ?? 0;
            $v_s1 = $v['security_perimeter'] ?? 0;
            $v_s2 = $v['supply_chain_governance'] ?? 0;
            $v_i1 = $v['infrastructure_maturity'] ?? 0;
            $v_i2 = $v['database_architecture'] ?? 0;

            $aiResult['risk_matrix'] = [
                'performance' => (int) round(($v_p1 + $v_p2) / 2),
                'security'    => (int) round(($v_s1 + $v_s2) / 2),
                'scalability' => (int) round(($v_i1 + $v_i2) / 2),
            ];

            // TITAN V5.3: Risk Matrix Calculation (Backwards Compatibility)
            // But do NOT overwrite the Score. The ProjectAuditor already calculated the Sovereign Score (74).
            // $aiResult['score'] is already correct.

            // 6. PERSISTENCE
            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Ledger: Securing Code Forensic Report...', 95);

            $score = intval($aiResult['score'] ?? 0);
            
            // Status Logic: Strict Code Quality
            if ($score >= 85) $finalStatus = 'verified';
            elseif ($score >= 70) $finalStatus = 'action_required';
            else $finalStatus = 'flagged';

            DB::transaction(function () use ($finalStatus, $score, $aiResult, $dnaEvidence, $vaultAsset, $ledgerService, $fingerprintHash, $headCommitSha, $repoUrl, $forensicData) {
                
                $this->project->update([
                    'status' => $finalStatus,
                    'fingerprint_hash' => $fingerprintHash, // Capture Fingerprint
                    'audit_data' => array_merge($this->project->audit_data ?? [], [
                        'dna' => $dnaEvidence,
                        'ai_report' => $aiResult,
                        'scanned_at' => now(),
                        'scan_type' => 'repository'
                    ]),
                ]);

                if ($vaultAsset) {
                    // CRITICAL: For Sync Scans, use lock to prevent race with parallel Project Scan
                    if ($this->auditContext === 'sync') {
                        $vaultAsset = VaultAsset::where('id', $vaultAsset->id)->lockForUpdate()->first();
                    }
                    
                    $vaultAsset->update([
                        'status' => $finalStatus,
                        'score' => $score,
                        'fingerprint_hash' => $fingerprintHash, // Capture Fingerprint
                        'metadata' => array_merge($vaultAsset->metadata ?? [], $aiResult, [
                            'audit_type' => 'repository_scan',
                            'url' => $repoUrl, 
                            'lines_analyzed' => 'Dynamic', 
                            'topology' => [],
                            // TITAN V6.0: Backward Compatibility for UI
                            // Map the new flat 'tech_stack' to the old 'tech_assessment.stack' structure
                            'tech_assessment' => [
                                'stack' => $aiResult['tech_stack'] ?? [],
                                'architecture' => 'Modern', // Default
                                'quality_score' => $score
                            ],
                            'tech_stack' => $aiResult['tech_stack'] ?? []
                        ]),
                        'radar_data' => $aiResult['hexagon_vectors'] ?? []
                    ]);

                    // TITAN V6.0: Create Immutable Audit History (Repo)
                    \App\Models\VaultHistory::create([
                        'vault_asset_id' => $vaultAsset->id,
                        'batch_id' => $this->syncBatchId,
                        'score' => $score,
                        'individual_score' => $score, // TITAN V6.5: Schema Refactor
                        'status' => $finalStatus,
                        'scanned_type' => 'repository', // User Requested Column
                        'metadata' => array_merge($aiResult, [
                            'audit_type' => 'repository_scan', // Explicit Type for History Aggregation
                            'summary' => $aiResult['executive_summary'] ?? 'Repo Scan Complete.',
                            'quality_rating' => $aiResult['code_quality']['rating'] ?? 'N/A',
                            'audit_context' => $this->auditContext,
                            'hexagon_vectors' => $aiResult['hexagon_vectors'] ?? [],
                            'risk_matrix' => $aiResult['risk_matrix'] ?? [],
                            'forensic_data' => $forensicData, // Include raw metrics
                            'tech_stack' => array_map(function($t) {
                                return $t['name'] ?? 'Unknown';
                            }, $aiResult['tech_stack'] ?? [])
                        ]),
                        'scanned_at' => now()
                    ]);

                    // Financial Settlement: Deduct only on successful completion
                    if ($this->auditContext !== 'sync') {
                        $ledgerService->consumeAuditCredits($vaultAsset->user, 'project', $this->project->name ?? 'Repo Scan');
                    }

                    // Unified Scan Activity Record (New Requirement)
                    if (!$this->suppressActivityLog) {
                        \App\Models\ScanActivity::updateOrCreate(
                            [
                                'primary_asset_id' => $vaultAsset->id,
                                'batch_id' => $this->syncBatchId // Strict Context Isolation
                            ],
                            [
                                'user_id' => $vaultAsset->user_id,
                                'urls_and_sync' => $repoUrl,
                                'type' => 'repository',
                                'secondary_asset_id' => null,
                                'sync_status' => null,
                                'docu_and_urls_status' => $finalStatus,
                                'sync_confidence_score' => null,
                                'individual_score' => $score,
                                'vectors' => $aiResult['hexagon_vectors'] ?? [], // New column
                                'vectors' => $aiResult['hexagon_vectors'] ?? [], // New column
                                'details' => json_encode(array_merge($aiResult, [
                                    // TITAN V6.0: Legacy UI Support for ScanActivity
                                    'tech_assessment' => [
                                        'stack' => $aiResult['tech_stack'] ?? [],
                                        'architecture' => 'Modern',
                                        'quality_score' => $score
                                    ]
                                ])), // Capture Full Report with Legacy Props
                                'scanned_at' => now(),
                            ]
                        );
                    }
                }
            });

            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Sovereign Code Audit Complete.', 100);

            if ($vaultAsset) {
                $vaultAsset->touch();
                event(new AssetStatusUpdated($vaultAsset));
            }

        } catch (\Exception $e) {
            // RETRY LOGIC (Enhanced for Backoff)
            // RETRY LOGIC REMOVED BY USER REQUEST
            /*
            if ($e->getCode() === 503 || $e->getCode() === 429 || str_contains(strtolower($e->getMessage()), 'overloaded')) {
                Log::warning("TITAN TRANSIENT ERROR: AI Overloaded. Aborting to prevent ghosts...");
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'AI Busy. Aborting Scan.', 100);
                
                // THROW the exception to trigger Laravel's automatic retry with backoff()
                // throw $e; 
                return;
            }
            */

            Log::error("TITAN FATAL REPO SCAN ERROR: " . $e->getMessage());
            
            if ($vaultAsset) {
                $ledgerService->refundAuditCredits($vaultAsset->user, 'project', $vaultAsset->file_name);
                AuditFailed::dispatch($vaultAsset, 'Scan Failure: ' . $e->getMessage());
                $vaultAsset->update(['status' => 'failed_system']);
                event(new AssetStatusUpdated($vaultAsset));
            }
        } finally {
            Log::info("TITAN DEBUG: Entering Finally Block - Cleaning up.");
            // CLEANUP CLONED REPO
            if ($localRepoPath && isset($forensics)) {
                 $forensics->cleanup($localRepoPath);
            }
        }
    }
}
