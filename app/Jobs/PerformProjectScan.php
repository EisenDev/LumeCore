<?php

namespace App\Jobs;

use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Models\AuditHistory;
use App\Models\ScanActivity;
use App\Services\InfrastructureScannerService;
use App\Services\AI\ProjectAuditor;
use App\Services\VaultAuditService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Events\AuditProgressUpdated;
use App\Events\AssetStatusUpdated;
use App\Events\AuditFailed;

class PerformProjectScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1200; // Increased to 20 mins for Deep Crawls + AI

    public function __construct(
        public ProjectAsset $project,
        public ?string $githubToken = null,
        public ?string $vaultAssetId = null,
        public ?string $linkedRepoAssetId = null,
        public ?string $syncBatchId = null // TITAN V2: Context Isolation
    ) {}



    /*
    public function backoff(): array
    {
        return [60, 120, 180];
    }
    */

    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('browsershot_mutex'))->releaseAfter(900)];
    }

    public function handle(
        InfrastructureScannerService $scanner, 
        ProjectAuditor $aiAuditor,
        VaultAuditService $auditService,
        LedgerService $ledgerService
    ): void {
        Log::info("LUME TITAN: Starting forensic scan for project {$this->project->id}", ['vault_asset_id' => $this->vaultAssetId]);

        $vaultAsset = $this->vaultAssetId ? VaultAsset::find($this->vaultAssetId) : null;

        // TITAN V2: Context Isolation Logic
        // The ProjectController now generates the Batch ID and creates/updates the specific Sync Asset BEFORE dispatching.
        // So $vaultAsset should ALREADY be the correct, isolated asset.
        
        if ($this->syncBatchId && $vaultAsset) {
            // VERIFICATION: Ensure the asset we received actually belongs to this batch
            if ($vaultAsset->batch_id === $this->syncBatchId) {
                // Perfect. We are working on the correct isolated asset.
                Log::info("PerformProjectScan: Using verified Context-Isolated Asset", ['id' => $vaultAsset->id, 'batch' => $this->syncBatchId]);
            } else {
                // FALLBACK: If for some reason we got a non-batched asset (legacy or race condition),
                // we MUST clone it to preserve isolation.
                Log::warning("PerformProjectScan: Received Non-Batched Asset in Sync Job. Cloning...", ['id' => $vaultAsset->id]);
                
                $originalAsset = $vaultAsset;
                $vaultAsset = VaultAsset::create([
                    'user_id' => $originalAsset->user_id,
                    'file_name' => $originalAsset->file_name,
                    'file_path' => $originalAsset->file_path,
                    'file_size' => $originalAsset->file_size,
                    'mime_type' => $originalAsset->mime_type,
                    'status' => 'processing',
                    'batch_id' => $this->syncBatchId,
                    'metadata' => array_merge($originalAsset->metadata ?? [], [
                        'audit_type' => 'website_scan',
                        'context' => 'sync_child',
                        'parent_asset_id' => $originalAsset->id
                    ])
                ]);
                // Update the Job's reference
                $this->vaultAssetId = $vaultAsset->id;
            }
        }

        try {
            // 1. INITIALIZE STATUS
            $this->project->update(['status' => 'pending_verification']);
            if ($vaultAsset) {
                $vaultAsset->update(['status' => 'processing']);
                AuditProgressUpdated::dispatch($vaultAsset, 'Initializing Sovereign Infrastructure Analyst...', 5);
            }

            // 2. DESIGN AUDIT (If visual proofs exist)
            $proofs = VaultAsset::where('metadata->project_asset_id', $this->project->id)
                ->where('metadata->is_proof', true)->get();

            if ($proofs->isNotEmpty()) {
                AuditProgressUpdated::dispatch($vaultAsset, 'Visual Harvester: Analyzing Design Proofs...', 15);
                $this->handleDesignAudit($proofs, $vaultAsset, $aiAuditor);
            }

            // 3. PHASE 1: DNA RECONNAISSANCE (Codebase)
            $dnaEvidence = [];
            try {
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Harvester: Extracting DNA & Manifests...', 25);
                $dnaEvidence = $scanner->scanProject($this->project, $this->githubToken);
            } catch (\Exception $e) {
                Log::warning("DNA Harvest Failure: " . $e->getMessage());
                $dnaEvidence = ['error' => 'Repository inaccessible: ' . $e->getMessage()];
            }

            // 4. PHASE 2: FORENSIC CRAWL (The Eyes)
            $evidencePacketRaw = "";
            $evidenceString = ""; // Initialize explicitly
            $topology = [];
            
            try {
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Harvester: Capturing Live DOM & Network Signals...', 45);
                
                // TITAN V6.2: HANDS UPGRADE (Python Surface Scanner)
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Harvester: Engaging Titan Surface Scanner (Deep Probe)...', 45);

                $pythonScript = base_path('app/Services/Python/TitanSurface.py');
                
                Log::info("TITAN V6.2: Launching Python Scanner", [
                    'script' => $pythonScript,
                    'target' => $this->project->website_url,
                    'timeout' => 900
                ]);

                // Titan v6.2: Extended Timeout (15 mins) for Deep Recursive Crawl
                $process = \Illuminate\Support\Facades\Process::timeout(900)->run([ 
                    'python3', 
                    $pythonScript, 
                    $this->project->website_url 
                ]);

                if ($process->successful()) {
                    $output = $process->output();
                    $scanData = json_decode($output, true); 
                } else {
                    $scanData = []; // Fallback
                }

                Log::info("TITAN V6.2: Python Process Completed", [
                    'exit_code' => $process->exitCode(),
                    'output_length' => strlen($process->output()),
                    'error_output' => $process->errorOutput() // Capture Python stderr logs
                ]);

                if ($process->successful()) {
                    $scanData = json_decode($process->output(), true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error("TITAN V6.2: JSON Decode Error", ['error' => json_last_error_msg(), 'raw_output' => substr($process->output(), 0, 1000)]);
                    } else {
                        Log::info("TITAN V6.2: Tech Detected (Raw Python)", ['tech' => $scanData['tech_detected'] ?? []]);
                    }
                    
                    // Construct Evidence String for AI
                    $evidenceString = "TITAN SURFACE SCAN REPORT:\n";
                    $evidenceString .= "Status: " . ($scanData['status_code'] ?? 'Unknown') . "\n";
                    $evidenceString .= "Latency: " . ($scanData['latency_ms'] ?? '0') . "ms\n";
                    $evidenceString .= "Headers: " . json_encode($scanData['headers'] ?? [], JSON_PRETTY_PRINT) . "\n";
                    $evidenceString .= "Tech Detected: " . json_encode($scanData['tech_detected'] ?? [], JSON_PRETTY_PRINT) . "\n";
                    $evidenceString .= "Security Headers Missing: " . json_encode($scanData['security_headers_missing'] ?? [], JSON_PRETTY_PRINT) . "\n";
                    
                    // Construct Nodes/Links Topology
                    $nodes = [];
                    $links = [];
                    
                    // Root
                    $nodes[] = ['id' => '/', 'status' => $scanData['status_code'] ?? 200, 'type' => 'root'];
                    
                    // Scripts
                    foreach (($scanData['assets']['scripts'] ?? []) as $script) {
                        $name = basename($script);
                        $nodes[] = ['id' => $name, 'status' => 200, 'type' => 'script'];
                        $links[] = ['source' => '/', 'target' => $name];
                    }
                    
                    // External
                    foreach (($scanData['assets']['external_links'] ?? []) as $link) {
                        $host = parse_url($link, PHP_URL_HOST);
                        if ($host) {
                            $nodes[] = ['id' => $host, 'status' => 200, 'type' => 'external'];
                            $links[] = ['source' => '/', 'target' => $host];
                        }
                    }

                    $topology = [
                        'nodes' => array_slice($nodes, 0, 50), // Limit for visualizer perf
                        'links' => array_slice($links, 0, 50),
                        'raw_assets' => $scanData['assets'] ?? []
                    ];

                    // Merge Verified Python Tech into main Tech Stack
                    if (!isset($dnaEvidence['tech_footprint'])) {
                        $dnaEvidence['tech_footprint'] = [];
                    }
                    foreach (($scanData['tech_detected'] ?? []) as $tech) {
                        $dnaEvidence['tech_footprint'][] = [
                            'name' => $tech['name'],
                            'category' => 'Verified Fingerprint', // Mark as verified
                            'version' => null,
                            'confidence' => $tech['confidence']
                        ];
                    }

                    $evidencePacketRaw = [
                        'evidence' => $evidenceString,
                        'topology' => $topology,
                        'scan_data' => $scanData // Keep full data
                    ];

                    Log::info("TITAN V6.2: Evidence Packet Constructed", [
                        'evidence_length' => strlen($evidenceString),
                        'topology_nodes' => count($topology['nodes']),
                        'tech_footprint_count' => count($dnaEvidence['tech_footprint'] ?? []),
                        'raw_assets_count' => count($scanData['assets'] ?? [])
                    ]);

                    Log::info("TITAN V6.2: Python Scan Successful", [
                        'url' => $this->project->website_url,
                        'tech_count' => count($scanData['tech_detected'] ?? []),
                        'nodes_count' => count($nodes)
                    ]);

                } else {
                    throw new \Exception("Titan Surface Scanner Failed: " . $process->errorOutput());
                }
                
            } catch (\Exception $e) {
                Log::error("Harvester Failure: " . $e->getMessage());
                $evidenceString = "CRITICAL_HARVEST_FAILURE: Unresponsive target infrastructure. Context: " . $e->getMessage();
                if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Target unresponsive. Engaging Forensic Pathologist...', 55);
            }

            // 5. PHASE 3: SOVEREIGN AUDIT (The Brain)
            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Auditor: Synthesizing Forensic Vectors...', 75);

            $projectData = [
                'website_url' => $this->project->website_url,
                'github_url'  => $this->project->github_repo_url,
                'tech_stack'  => $dnaEvidence['tech_footprint'] ?? [],
                'dns_provider'=> $dnaEvidence['dns_provider'] ?? null,
                'env_map'     => $dnaEvidence['env_variables'] ?? [],
                // Titan v5.0: Enhanced Forensic Mapping
                'config_files'      => $dnaEvidence['config_files'] ?? [],
                'dependency_files'  => $dnaEvidence['dependency_files'] ?? [],
                'ssl_forensics'     => $dnaEvidence['ssl_forensics'] ?? [],
                'server_headers'    => $dnaEvidence['server_headers'] ?? [],
                'strategic_context' => $dnaEvidence['strategic_context'] ?? null,
                'raw_files' => $dnaEvidence['config_files'] ?? [], 
            ];

            // TITAN V6.3: SYNC CONTEXT INJECTION (Github First Strategy)
            if ($this->linkedRepoAssetId) {
                $repoAsset = VaultAsset::find($this->linkedRepoAssetId);
                if ($repoAsset && !empty($repoAsset->metadata)) {
                    Log::info("TITAN V6.3: Injecting Repository Context into Web Scan", ['repo_id' => $repoAsset->id]);
                     $projectData['repo_context'] = [
                        'detected_tech' => $repoAsset->metadata['tech_stack'] ?? [],
                        'file_count' => $repoAsset->metadata['files_scanned'] ?? 0,
                        'security_issues' => $repoAsset->metadata['hexagon_vectors']['security_perimeter'] ?? 100,
                        'summary' => $repoAsset->metadata['tech_narrative'] ?? 'No narrative available.'
                    ];
                }
            }

            // Trigger AI with the Titan v5.0 Rules
            $aiResult = $aiAuditor->analyzeProject($projectData, $evidenceString);

            // POST-PROCESSING: TITAN V6.0 AI SCHEMA ADAPTATION
            // ProjectAuditor now uses ForensicCalculator to compute the final score
            // using official weights. We trust the AI+Calculator output.
            
            // 1. Extract Tech Footprint Explanations (Frontend Compatibility)
            if (!empty($aiResult['tech_stack'])) {
                $explanations = [];
                foreach ($aiResult['tech_stack'] as $tech) {
                    if (!empty($tech['name']) && !empty($tech['why'])) {
                        $explanations[$tech['name']] = $tech['why'];
                    }
                }
                $aiResult['tech_footprint_explanations'] = $explanations;
            }
            
            // 2. ALWAYS Normalize Vectors (Crucial for Calc)
            $vectors = $aiResult['hexagon_vectors'] ?? [];
            $v = [];
            foreach ($vectors as $k => $val) {
                // Handle "Security Perimeter" -> "security_perimeter" AND "SecurityPerimeter" -> "security_perimeter"
                // Remove spaces/dashes, then snake_case if needed? 
                // Simple approach: lowercase and underscore replacement
                $v[strtolower(str_replace([' ', '-'], '_', $k))] = (int)$val;
            }

            // 2. Derive Risk Matrix from Hexagon Vectors (3-Pillar UI Compatibility)
            if (!isset($aiResult['risk_matrix']) || empty($aiResult['risk_matrix'])) {
                $aiResult['risk_matrix'] = [
                    'performance' => (int) round((($v['client_side_velocity'] ?? 0) + ($v['code_efficiency'] ?? 0)) / 2),
                    'security'    => (int) round((($v['security_perimeter'] ?? 0) + ($v['supply_chain_governance'] ?? 0)) / 2),
                    'scalability' => (int) round((($v['infrastructure_maturity'] ?? 0) + ($v['database_architecture'] ?? 0)) / 2),
                ];
            }
            
            // Ensure Insights (Key Evidence) is always an array
            if (!isset($aiResult['insights']) || !is_array($aiResult['insights'])) {
                $aiResult['insights'] = [];
            }

            // 3. Fallback for Languages (If AI misses it)
            if (empty($aiResult['languages']) && !empty($aiResult['tech_assessment']['stack'])) {
                $langs = [];
                $stack = $aiResult['tech_assessment']['stack'];
                foreach ($stack as $t) {
                    $n = strtolower($t['name']);
                    if (str_contains($n, 'laravel') || str_contains($n, 'php') || str_contains($n, 'symfony')) $langs['PHP'] = 1;
                    if (str_contains($n, 'react') || str_contains($n, 'vue') || str_contains($n, 'node') || str_contains($n, 'next')) $langs['TypeScript'] = 1;
                    if (str_contains($n, 'python') || str_contains($n, 'django') || str_contains($n, 'flask')) $langs['Python'] = 1;
                }
                
                if (!empty($langs)) {
                    $count = count($langs);
                    $share = floor(100 / $count);
                    $aiResult['languages'] = [];
                    foreach ($langs as $l => $v) {
                        $aiResult['languages'][] = ['name' => $l, 'percentage' => $share];
                    }
                }
            }

            // Memory Cleanup (Crucial for 1GB Server)
            unset($evidencePacketRaw);
            gc_collect_cycles();

            // 6. PHASE 4: DETERMINISTIC SCORING & SETTLEMENT
            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Ledger: Securing Forensic Hash to Ledger...', 90);

            // 5. SANITIZER: Force-Fix Language Labels & Missing Breakdown
            if (!empty($aiResult['languages'])) {
                foreach ($aiResult['languages'] as &$lang) {
                    if ($lang['name'] === 'JavaScript (Compiled Bundle)') {
                        $lang['name'] = 'TypeScript';
                    }
                }
            }

            // FILTER: Remove "Handshake Failed" warnings from Score Logic (User Feedback: Irrelevant for Score 89)
            if (!empty($aiResult['score_breakdown'])) {
                $aiResult['score_breakdown'] = array_values(array_filter($aiResult['score_breakdown'], function($reason) {
                    return !str_contains($reason, 'Handshake Failed');
                }));
            }

            // Ensure Score Breakdown exists for UI
            if (empty($aiResult['score_breakdown'])) {
                $deductions = [];
                // Generate synthetic deductions based on penalties if AI forgot them
                if (($aiResult['hexagon_vectors']['security_perimeter'] ?? 100) < 80) $deductions[] = 'Security Gaps Detected (-Points)';
                if (($aiResult['hexagon_vectors']['infrastructure_maturity'] ?? 100) < 50) $deductions[] = 'Manual Infrastructure (-Points)';
                if (empty($deductions)) $deductions = ['Verified Architecture (+)', 'Modern Stack Identified (+)'];
                
                $aiResult['score_breakdown'] = $deductions;
            }

            // 6. PHASE 4: DETERMINISTIC SCORING & SETTLEMENT
            // FORCE RECALCULATION: Score must match the Weighted Average of Vectors (User Request)
            // Weights defined in ForensicCalculator::calculateWebsiteScore
            $w_velocity = 0.25;
            $w_security = 0.25;
            $w_infra    = 0.20;
            $w_db       = 0.10;
            $w_supply   = 0.10;
            $w_code     = 0.10;

            // Use the normalized vectors ($v) we prepared earlier
            $calcScore = 
                (($v['client_side_velocity']    ?? 0) * $w_velocity) +
                (($v['security_perimeter']      ?? 0) * $w_security) +
                (($v['infrastructure_maturity'] ?? 0) * $w_infra) +
                (($v['database_architecture']   ?? 0) * $w_db) +
                (($v['supply_chain_governance'] ?? 0) * $w_supply) +
                (($v['code_efficiency']         ?? 0) * $w_code);

            // We use the CALCULATED score instead of the AI's estimation
            $score = (float) round($calcScore, 2);
            $aiResult['score'] = $score; // Update the blob too for consistency

            $hasRepo = !empty($this->project->github_repo_url);
            
            // TITAN HANDSHAKE OVERRIDE
            // TITAN STATUS CALCULATOR (Source of Truth: ForensicCalculator)
            $statusCalc = new \App\Services\Calculations\ForensicCalculator();
            $statusResult = $statusCalc->calculateStatus($score);
            $baseStatus = $statusResult['status'];

            // Apply "Verified Private" Logic
            if ($baseStatus === 'verified' && !$hasRepo) {
                $finalStatus = 'verified_private';
            } else {
                $finalStatus = $baseStatus;
            }

            // 7. PERSISTENCE
            DB::transaction(function () use ($finalStatus, $score, $aiResult, $dnaEvidence, $vaultAsset, $ledgerService, $topology, $scanData) {
                
                // Update Project Core
                $this->project->update([
                    'status' => $finalStatus,
                    'audit_data' => array_merge($this->project->audit_data ?? [], [
                        'dna' => $dnaEvidence,
                        'ai_report' => $aiResult,
                        'scanned_at' => now()
                    ]),
                    'verified_at' => $finalStatus === 'verified' ? now() : null
                ]);

                if ($vaultAsset) {
                    // TITAN V6.0: Update Vault Asset with Hexagon Schema + Dynamic Modal Data
                    $vaultAsset->update([
                        'status' => $finalStatus,
                        'score' => $score,
                        'metadata' => array_merge($vaultAsset->metadata ?? [], $aiResult, [
                            // New Schema Fields (V6.0)
                            'audit_path' => $aiResult['audit_path'] ?? 'A',
                            'calculation_audit' => $aiResult['calculation_audit'] ?? 'N/A',
                            'handshake_status' => $aiResult['handshake_status'] ?? ($finalStatus === 'verified' ? 'URL + Code Synced' : 'URL Only'),
                            'topology' => $topology ?? [],
                            'routing_topology' => $scanData['routing_topology'] ?? [], // TITAN V6.5: Live Routes
                            'route_contexts' => $scanData['route_contexts'] ?? [],   // TITAN V6.6: Semantic Context
                            'forensic_insights' => $aiResult['forensic_insights'] ?? [],
                            'remediation_roadmap' => $aiResult['remediation_roadmap'] ?? [],
                            
                            // TITAN V6.3: TRIPLE-LAYER TECH MERGE (DNA + Python + AI)
                            'tech_stack' => (function() use ($aiResult, $scanData, $dnaEvidence) {
                                $merged = $aiResult['tech_stack'] ?? [];
                                $existingNames = array_map(fn($t) => strtolower($t['name']), $merged);

                                // 1. Merge hard DNA evidence (Manifest files like package.json)
                                foreach (($dnaEvidence['tech_footprint'] ?? []) as $dnaTech) {
                                    $name = $dnaTech['name'];
                                    if (!in_array(strtolower($name), $existingNames)) {
                                        $merged[] = [
                                            'name' => $name,
                                            'category' => $dnaTech['category'] ?? 'Verified DNA',
                                            'version' => $dnaTech['version'] ?? null,
                                            'dot_color' => '#10B981', // Emerald-500 for DNA
                                            'why' => 'Verified via Manifest File (DNA)',
                                            'confidence' => 'Very High'
                                        ];
                                        $existingNames[] = strtolower($name);
                                    }
                                }

                                // 2. Merge Python Surface signatures
                                foreach (($scanData['tech_detected'] ?? []) as $pyTech) {
                                    $name = $pyTech['name'];
                                    if (!in_array(strtolower($name), $existingNames)) {
                                        $merged[] = [
                                            'name' => $name,
                                            'category' => 'Verified Fingerprint',
                                            'version' => null,
                                            'dot_color' => '#64748B', 
                                            'why' => 'Detected by Titan Surface Scanner',
                                            'confidence' => $pyTech['confidence']
                                        ];
                                        $existingNames[] = strtolower($name);
                                    }
                                }
                                return $merged;
                            })(),

                            // TITAN V6.1: Dynamic Modal Fields (Extracted from Real Evidence)
                            'languages' => $aiResult['languages'] ?? [],
                            'network_signals' => $aiResult['network_signals'] ?? [
                                'protocol' => 'Unknown',
                                'dns_authority' => 'Unknown',
                                'server_signature' => 'Unknown'
                            ],
                            'scan_integrity' => $aiResult['scan_integrity'] ?? [
                                'handshake_verified' => false,
                                'method' => 'None',
                                'timestamp' => now()->toIso8601String()
                            ],
                            'enterprise_governance' => $aiResult['enterprise_governance'] ?? [
                                'license' => 'Unknown',
                                'gdpr_ready' => false,
                                'supply_chain_score' => 0
                            ],
                        ]),
                        'radar_data' => (function() use ($aiResult) {
                            // CRITICAL: Extract hexagon_vectors from new AI schema
                            $hexagon = $aiResult['hexagon_vectors'] ?? [];
                            
                            // Normalize keys (handle underscore/camelCase variations)
                            $normalized = [];
                            foreach ($hexagon as $k => $v) {
                                $key = strtolower(str_replace([' ', '-'], '_', $k));
                                $normalized[$key] = (int) $v;
                            }
                            
                            // Return in the exact 6-vector format expected by the Dashboard
                            return [
                                'security_perimeter' => $normalized['security_perimeter'] ?? 0,
                                'supply_chain_governance' => $normalized['supply_chain_governance'] ?? 0,
                                'infrastructure_maturity' => $normalized['infrastructure_maturity'] ?? 0,
                                'database_architecture' => $normalized['database_architecture'] ?? 0,
                                'client_side_velocity' => $normalized['client_side_velocity'] ?? 0,
                                'code_efficiency' => $normalized['code_efficiency'] ?? 0,
                            ];
                        })()
                    ]);
                    
                    Log::info("TITAN V6.1: VaultAsset metadata updated", [
                        'has_topology_in_metadata' => !empty($vaultAsset->metadata['topology'] ?? null),
                        'has_tech_assessment_in_metadata' => !empty($vaultAsset->metadata['tech_assessment'] ?? null),
                        'has_tech_stack_in_metadata' => !empty($vaultAsset->metadata['tech_stack'] ?? null)
                    ]);

                    // TITAN V6.0: Create Immutable Audit History with Enhanced Metadata
                    \App\Models\VaultHistory::create([
                        'vault_asset_id' => $vaultAsset->id,
                        'batch_id' => $this->syncBatchId, // Traceability
                        'score' => $score,
                        'individual_score' => $score, // TITAN V6.5: Schema Refactor
                        'status' => $finalStatus,
                        'scanned_type' => 'website', // User Requested Column
                        'metadata' => array_merge($aiResult, [
                            'audit_type' => 'website_scan', // Explicit Type for History Aggregation
                            'summary' => $aiResult['business_summary'] ?? $aiResult['executive_summary'] ?? 'Audit Complete.',
                            'calculation_log' => $aiResult['calculation_audit'] ?? 'N/A',
                            'audit_path' => $aiResult['audit_path'] ?? 'A',
                            'hexagon_vectors' => $aiResult['hexagon_vectors'] ?? [],
                            'risk_matrix' => $aiResult['risk_matrix'] ?? [],
                            'forensic_insights' => $aiResult['forensic_insights'] ?? [],
                            'topology' => $topology ?? [],
                            // Flattened Tech Stack for History UI
                            'tech_stack' => array_map(function($t) {
                                return $t['name']; 
                            }, $scanData['tech_detected'] ?? [])
                        ]),
                        'scanned_at' => now()
                    ]);

                    // TITAN V6.1: Create Scan Activity Record for Dashboard
                    // This ensures website scans appear in "Recently Scanned Projects"
                    Log::info("TITAN V6.1: Attempting to create ScanActivity record", [
                        'user_id' => $vaultAsset->user_id,
                        'vault_asset_id' => $vaultAsset->id,
                        'url' => $this->project->website_url,
                        'score' => $score,
                    ]);
                    
                    try {
                        ScanActivity::updateOrCreate(
                            [
                                'primary_asset_id' => $vaultAsset->id,
                                'batch_id' => $this->syncBatchId // Strict Context Isolation
                            ],
                            [
                                'user_id' => $vaultAsset->user_id,
                                'urls_and_sync' => $this->project->website_url ?? 'N/A',
                                'type' => 'website',
                                'secondary_asset_id' => null,
                                'sync_status' => null,
                                'docu_and_urls_status' => $finalStatus,
                                'sync_confidence_score' => null,
                                'individual_score' => $score,
                                'vectors' => $aiResult['hexagon_vectors'] ?? [],
                                'details' => json_encode($aiResult),
                                'scanned_at' => now(),
                            ]
                        );
                        
                        Log::info("TITAN V6.1: ScanActivity created successfully!");
                    } catch (\Exception $scanActivityError) {
                        Log::error("TITAN V6.1: ScanActivity creation FAILED", [
                            'error' => $scanActivityError->getMessage(),
                            'trace' => $scanActivityError->getTraceAsString()
                        ]);
                        // Don't throw - let scan complete even if activity log fails
                    }

                    // Financial Settlement: Deduct only on successful completion
                    $ledgerService->consumeAuditCredits($vaultAsset->user, 'project', $this->project->name ?? 'Project Scan');
                }
            });

            if ($vaultAsset) AuditProgressUpdated::dispatch($vaultAsset, 'Sovereign Audit Complete.', 100);

            // 8. FINAL BROADCAST (Trigger Frontend Refresh)
            if ($vaultAsset) {
                $vaultAsset->touch();
                event(new AssetStatusUpdated($vaultAsset));
            }

            Log::info("Scan Complete. Status: $finalStatus");

        } catch (\Exception $e) {
            // RETRY LOGIC REMOVED BY USER REQUEST
            /*
            if ($e->getCode() === 503 || $e->getCode() === 429 || str_contains(strtolower($e->getMessage()), 'overloaded')) {
                Log::warning("TITAN TRANSIENT ERROR: AI Overloaded. Aborting scan instead of retrying to prevent ghosts... (" . $e->getMessage() . ")");
                if ($vaultAsset) {
                    AuditProgressUpdated::dispatch($vaultAsset, 'AI Overloaded. Scan Aborted.', 100);
                }
                return;
            }
            */

            Log::error("TITAN FATAL JOB ERROR: " . $e->getMessage());
            
            if ($vaultAsset) {
                // Refund credits on infrastructure failure
                $ledgerService->refundAuditCredits($vaultAsset->user, 'project', $vaultAsset->file_name);
                
                AuditFailed::dispatch($vaultAsset, 'System Failure: Infrastructure timeout or resource exhaustion.');
                $vaultAsset->update(['status' => 'failed_system']);
            }
        }
    }

    /**
     * Internal handler for Design audits to prevent handle() bloat.
     */
    protected function handleDesignAudit($proofs, $vaultAsset, $aiAuditor) {
        // ... (Design audit implementation follows same Titan principles)
    }
}