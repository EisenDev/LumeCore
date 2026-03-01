<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Services\AI\WebURLandRepoAuditor;
use App\Services\InfrastructureScannerService;
use App\Services\Calculations\ForensicCalculator;

class PerformSyncComparison implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Explicitly disable retries

    public function __construct(
        protected ProjectAsset $project,
        protected string $webAssetId,
        protected string $repoAssetId,
        protected ?string $syncBatchId = null // TITAN V2: Context Isolation
    ) {}

    public function handle(WebURLandRepoAuditor $auditor, InfrastructureScannerService $scanner)
    {
        Log::info("PerformSyncComparison: Starting Analysis", [
            'project_id' => $this->project->id,
            'web' => $this->webAssetId,
            'repo' => $this->repoAssetId,
            'batch_id' => $this->syncBatchId
        ]);

        $webAsset = VaultAsset::find($this->webAssetId);
        $repoAsset = VaultAsset::find($this->repoAssetId);
        $progressAsset = $webAsset; // TITAN FIX: Always use the original ID for UI broadcasting

        // TITAN V2: Context Isolation - Resolve Child Assets
        if ($this->syncBatchId) {
            // We must find the actual scanned assets (children) linked to this batch
            // The Original IDs passed to constructor are the "Parents" (Solo Assets)
            $childWeb = VaultAsset::where('batch_id', $this->syncBatchId)
                ->where('metadata->audit_type', 'website_scan')
                ->latest()
                ->first();

            $childRepo = VaultAsset::where('batch_id', $this->syncBatchId)
                ->where('metadata->audit_type', 'repository_scan')
                ->latest()
                ->first();

            if ($childWeb && $childRepo) {
                Log::info("PerformSyncComparison: Context Switched to Child Assets", [
                    'web_child' => $childWeb->id,
                    'repo_child' => $childRepo->id
                ]);
                $webAsset = $childWeb;
                $repoAsset = $childRepo;
                
                // Update references for internal logic
                $this->webAssetId = $webAsset->id;
                $this->repoAssetId = $repoAsset->id;
            } else {
                Log::error("PerformSyncComparison: CRITICAL - Batch ID provided but Child Assets NOT FOUND. Aborting to protect Parent Assets.", [
                     'batch' => $this->syncBatchId
                ]);
                return; // ABORT: Do not proceed with Parent Assets
            }
        }

        if (!$webAsset || !$repoAsset) {
            Log::error("PerformSyncComparison: Assets not found.");
            return;
        }

        // ... (Existing extraction logic) ...

        // ... (Inside ScanActivity::updateOrCreate) ...


        if (!$webAsset || !$repoAsset) {
            Log::error("PerformSyncComparison: Assets not found.");
            return;
        }

        // Extract metadata for comparison
        $webData = $webAsset->metadata ?? [];
        $repoData = $repoAsset->metadata ?? [];

        // Check if we have enough data (at least hex vectors or tech stack)
        // TITAN UPDATE: We now allow empty web data if it signifies a "Deployment Failure" (Scenario B)
        // The Auditor will detect the missing tech and judge if it's a 404/500 error vs a fraud.
        if (empty($webData['hexagon_vectors']) && empty($webData['tech_stack'])) {
            Log::warning("PerformSyncComparison: Web Asset missing deep scan data. Proceeding with limited evidence for Deployment Verification.");
        }

        // --- ROBUST IDENTITY EXTRACTION (Moved Before AI) ---
        // Fix: Use Elvis operator (?:) combined with Null Coalesce (??) to safely skip empty strings and missing keys
        $webRowUrl = (($webData['url'] ?? null) ?: null)
                  ?? (($webAsset->website_metadata['url'] ?? null) ?: null)
                  ?? (($webAsset->metadata['url'] ?? null) ?: null)
                  ?? ($webAsset->url ?: null)
                  ?? ($webAsset->file_name ?: null) 
                  ?? '';
                  
        $repoRowUrl = (($repoData['url'] ?? null) ?: null)
                   ?? (($repoAsset->repository_metadata['url'] ?? null) ?: null)
                   ?? (($repoAsset->metadata['url'] ?? null) ?: null)
                   ?? ($repoAsset->url ?: null)
                   ?? ($repoAsset->file_name ?: null)
                   ?? '';

        $webUrl = strtolower(parse_url($webRowUrl, PHP_URL_HOST) ?? '');
        // Fallback: If parse_url returned empty (e.g. 'himsog.tech'), use raw string
        if (empty($webUrl)) $webUrl = strtolower($webRowUrl);

        $repoName = strtolower(basename($repoRowUrl, '.git'));
        
        // INJECT INTO AI PACKET
        $webData['url'] = $webRowUrl;
        $repoData['url'] = $repoRowUrl;
        $repoData['name'] = $repoName;

        // TITAN V6.5: Fetch Repository Route Structure for DNA Audit
        $repoRoutes = [];
        if (!empty($repoData['url'])) {
             $repoRoutes = $scanner->scanRouteStructure($repoData['url']);
        }

        // Prepare Data for Auditor
        $webData['live_routes'] = $webData['routing_topology'] ?? [];
        $webData['route_contexts'] = $webData['route_contexts'] ?? []; // TITAN V6.6
        $repoData['repo_routes'] = $repoRoutes;
        
        Log::info("TITAN DNA DEBUG: Data Prepared for Auditor", [
            'live_routes_count' => count($webData['live_routes']),
            'repo_routes_keys' => array_keys($repoRoutes),
            'repo_routes_preview' => array_map(fn($r) => count($r), $repoRoutes)
        ]);

        // TITAN V6.9: Broadcast Comparison Phase Start
        event(new \App\Events\AuditProgressUpdated($webAsset, "Synchronizing", 85, 'processing', "Initializing Cross-Component Comparison..."));
        event(new \App\Events\AuditProgressUpdated($repoAsset, "Synchronizing", 85, 'processing', "Initializing Cross-Component Comparison..."));

        try {
            // Perform AI Comparison (With Injected Identity Data)
            event(new \App\Events\AuditProgressUpdated($progressAsset, "Auditing", 88, 'processing', "Performing Semantic DNA Audit..."));
            $comparisonResult = $auditor->compare($webData, $repoData);
            
            event(new \App\Events\AuditProgressUpdated($progressAsset, "Scoring", 92, 'processing', "Calculating Forensic Alignment Score..."));            
            // --- TITAN ADDITIVE SCORING (V3.0) ---
            // We now calculate the score deterministically using the "Proof of Work" Additive Model.
            
            $calculator = new \App\Services\Calculations\ForensicCalculator();
            
            // 1. Prepare Enriched Tech Stacks (TITAN V6.8 FIX: Combine all sources)
            $collectTech = function($data) {
                return array_merge(
                    $data['tech_assessment']['stack'] ?? [],
                    $data['tech_footprint'] ?? [],
                    $data['tech_stack'] ?? []
                );
            };
            
            $rawWebStack = $collectTech($webData);
            $rawRepoStack = $collectTech($repoData);

            // 1. Prepare Context Flags & Identity Verification
            $context = [
                'topology_match' => !empty($webData['topology']) && !empty($comparisonResult['topology_verified']), 
                'strategic_match' => false,
                'identity_match' => false,
            ];
            
            // --- STRATEGIC CONTEXT MATCH (+9 Points) ---
            // Compare Business Summaries for keyword overlap
            $webSummary = strtolower($webData['business_summary'] ?? $webData['website_metadata']['business_summary'] ?? '');
            $repoSummary = strtolower($repoData['business_summary'] ?? $repoData['repository_metadata']['business_summary'] ?? '');

            if (!empty($webSummary) && !empty($repoSummary)) {
                // Tokenize and compare
                $webTokens = array_unique(array_filter(str_word_count($webSummary, 1), fn($w) => strlen($w) > 3));
                $repoTokens = array_unique(array_filter(str_word_count($repoSummary, 1), fn($w) => strlen($w) > 3));
                $intersection = array_intersect($webTokens, $repoTokens);
                
                // If they share at least 4 significant words (e.g. 'himsog', 'healthcare', 'governance', 'portal'), likely a match
                // TITAN UPDATE: Increased threshold to reduce false positives for generic sites
                if (count($intersection) >= 4) {
                    $context['strategic_match'] = true;
                    Log::info("TITAN DEBUG: Strategic Match Confirmed", ['keywords' => array_values($intersection)]);
                }
            }
            
            // --- IDENTITY MATCH (+5 Points) ---
            // 1. Manual Check (Regex/String)
            // Clean web url (remove .tech, .com for raw name check if needed), but simple contains is safer
            if (!empty($webUrl) && !empty($repoName)) {
                $hostParts = explode('.', $webUrl);
                // Check bidirectional containment
                if (str_contains($webUrl, $repoName) || str_contains($repoName, $hostParts[0])) {
                    $context['identity_match'] = true;
                }
            }
            
            // 2. AI Override (Auditor Result)
            if (!empty($comparisonResult['identity_match_boolean'])) {
                 $context['identity_match'] = true;
                 Log::info("TITAN DEBUG: AI Overrode Identity Match", ['ai_verdict' => true]);
            }
            
            // TITAN DEBUG: Logs
            Log::info("TITAN DEBUG: Identity Check", [
               'manual_match' => $context['identity_match'],
               'ai_match' => $comparisonResult['identity_match_boolean'] ?? false,
               'inputs' => [$webUrl, $repoName]
            ]);
            
            // TITAN DEBUG: Topology Inputs
            Log::info("TITAN DEBUG: Topology Check", [
               'web_topology_count' => count($webData['topology'] ?? []),
               'topology_verified_flag' => $comparisonResult['topology_verified'] ?? false
            ]);
            
            // 2. Calculate Additive Score
            $webVectors = $webData['hexagon_vectors'] ?? [];
            $repoVectors = $repoData['hexagon_vectors'] ?? [];

            // NORMALIZE TECH STACKS: Ensure all are ['name' => 'React'] format
            $normWebStack = array_map(function($item) {
                return Is_array($item) ? $item : ['name' => (string)$item];
            }, $rawWebStack);

            $normRepoStack = array_map(function($item) {
                return is_array($item) ? $item : ['name' => (string)$item];
            }, $rawRepoStack);

            Log::info("TITAN DEBUG: Calculator Inputs (Normalized)", [
                'web_stack_count' => count($normWebStack),
                'repo_stack_count' => count($normRepoStack),
                'context' => $context,
                'identity_check' => [
                    'web_url' => $webUrl,
                    'repo_name' => $repoName
                ]
            ]);
            
            $aiScores = [
                'tech_stack_score' => $comparisonResult['tech_stack_score'] ?? null,
                'strategic_context_score' => $comparisonResult['strategic_context_score'] ?? null,
                'structural_dna_score' => $comparisonResult['structural_dna_score'] ?? null,
                'structural_dna_map' => $comparisonResult['structural_dna_map'] ?? [], // TITAN V6.6
                'tech_stack_analysis' => $comparisonResult['tech_stack_analysis'] ?? null,
                'strategic_analysis' => $comparisonResult['strategic_analysis'] ?? null
            ];

            $additiveResult = $calculator->calculateSyncScoreWithBreakdown(
                $webVectors, 
                $repoVectors, 
                !empty($repoData['verified_handshake']), 
                $normWebStack, 
                $normRepoStack, 
                [], 
                $context,
                $aiScores // TITAN AI OVERRIDE
            );

            // 3. Apply Deterministic Overrides
            $score = $additiveResult['score'];
            
            Log::info("TITAN DEBUG: Calculator Output", [
                'score' => $score,
                'deductions' => $additiveResult['deductions']
            ]);

            $comparisonResult['sync_score'] = $score; // Override AI Score
            
            // Map the "Points Log" to the UI's drift analysis
            if (!isset($comparisonResult['drift_analysis'])) $comparisonResult['drift_analysis'] = [];
            
            // Map to BOTH common keys to ensure Frontend pickup
            $comparisonResult['drift_analysis']['calculation_log'] = $additiveResult['deductions']; 
            $comparisonResult['drift_analysis']['key_discrepancies'] = $additiveResult['deductions'];
            $comparisonResult['drift_analysis']['matches'][] = "Additive Match Verification Complete";
            
            // TITAN V7.2: Removed hard-coded summaries. 
            // Auditor summary from $comparisonResult will be used instead.

            // TITAN V6.9: Detailed Tech Matching for UI Evidence
            $norm = fn($s) => strtolower(trim(is_array($s) ? ($s['name'] ?? '') : (is_string($s) ? $s : '')));
            $webProcessed = array_map(function($t) { return is_array($t) ? ($t['name'] ?? '') : $t; }, $rawWebStack);
            $repoProcessed = array_map(function($t) { return is_array($t) ? ($t['name'] ?? '') : $t; }, $rawRepoStack);
            
            $webNames = array_map($norm, $rawWebStack);
            $repoNames = array_map($norm, $rawRepoStack);
            
            $matchingTech = [];
            foreach ($webNames as $i => $wn) {
                if (empty($wn)) continue;
                foreach ($repoNames as $rn) {
                    if (empty($rn)) continue;
                    if ($wn === $rn || str_contains($wn, $rn) || str_contains($rn, $wn)) {
                        $matchingTech[] = $webProcessed[$i];
                        break;
                    }
                }
            }
            $matchingTech = array_unique($matchingTech);

            $comparisonResult['stack_analysis'] = [
                'matching_status' => count($matchingTech) > 0 
                    ? "Verified " . count($matchingTech) . " Core Technologies" 
                    : "Tech Stack Mismatch / No Data",
                'live_evidence' => count($matchingTech) > 0 ? $matchingTech : (count($webProcessed) > 0 ? $webProcessed : ["No Live Tech Detected"]),
                'repo_evidence' => count($matchingTech) > 0 ? $matchingTech : (count($repoProcessed) > 0 ? $repoProcessed : ["No Repo Tech Detected"]),
            ];

            // TITAN V6.8: Inject Heuristic Gates into comparison result for UI immediate pickup
            $comparisonResult['metadata']['heuristic_gates'] = [
                'route_topology' => !empty($webData['topology']) ? 'verified' : 'pending',
                'asset_hash' => count($rawWebStack) > 0 ? 'verified' : 'pending',
                'dom_parity' => $score > 70 ? 'verified' : 'drift',
                'metadata_consistency' => $context['identity_match'] ?? false ? 'verified' : 'flagged'
            ];
            
            // Populate logic_gates for backward compatibility
            $comparisonResult['logic_gates'] = [
                'topology' => !empty($webData['topology']) ? 'VERIFIED' : 'PENDING',
                'hash' => count($rawWebStack) > 0 ? 'VERIFIED' : 'PENDING',
                'dom' => $score > 70 ? 'VERIFIED' : 'CALCULATING...',
                'metadata' => ($context['identity_match'] ?? false) ? 'VERIFIED' : 'FLAGGED'
            ];

            // Standardize Metadata
            $metadata = $webAsset->metadata ?? [];
            $metadata['comparison_data'] = $comparisonResult;
            $metadata['latest_sync_comparison'] = $comparisonResult; 
            $metadata['synced_with_repo_id'] = $repoAsset->id;
            
            // Determine Status
            $status = $score > 50 ? 'verified' : 'flagged';
            $comparisonStatus = $score > 75 ? 'Verified (Synced)' : ($score > 50 ? 'Action Required' : 'Flagged (Not Sync)');

            $metadata['comparison_status_label'] = $comparisonStatus;
            
            // TITAN FIX: Enforce Score Consistency
            $webAsset->score = $score;
            $repoAsset->score = $score;

            // Ensure File Sizes are Populated for Ghost Asset Detector
            if (empty($webAsset->file_size) && !empty($webData['content_length'])) {
                $webAsset->file_size = (int) $webData['content_length'];
            } elseif (empty($webAsset->file_size)) {
                // Heuristic: Average 2MB for a modern web app payload if unknown
                $webAsset->file_size = 2048 * 1024; 
            }

            if (empty($repoAsset->file_size) && !empty($repoData['ghost_code']['total_size_bytes'])) {
                $repoAsset->file_size = (int) $repoData['ghost_code']['total_size_bytes'];
            } elseif (empty($repoAsset->file_size)) {
                // Heuristic: If we have file count, approx 15KB per file
                $files = $repoData['files_scanned'] ?? 50;
                $repoAsset->file_size = $files * 15 * 1024;
            }

            // Update Web Asset (Primary) with Real Marketplace Data
            $webAsset->synced_assets = [
                'web' => $webAsset->id,
                'repo' => $repoAsset->id
            ];
            $webAsset->website_url = $webData['url'] ?? null;
            $webAsset->repository_url = $repoData['url'] ?? null;
            $webAsset->website_metadata = $webData;
            $webAsset->repository_metadata = $repoData;
            $webAsset->synced_metadata = $comparisonResult; // Snapshot
            $webAsset->sync_score = $score;
            
            $webAsset->metadata = $metadata;
            $webAsset->status = $status;
            
            // Create Immutable Audit History (For Sync)
            // TITAN V6.0: Migrated to VaultHistory
            // TITAN V6.0: Migrated to VaultHistory
            $syncHistory = \App\Models\VaultHistory::create([
                'vault_asset_id' => $webAsset->id,
                'batch_id' => $this->syncBatchId,
                'score' => $score,
                'individual_score' => null, // Sync record has no individual score
                'sync_score' => $score,     // Contextual Sync Score
                'status' => $status,
                'scanned_type' => 'sync',   // User Requested Column
                'metadata' => [
                    'summary' => 'Sync Comparison: ' . $comparisonStatus,
                    'audit_type' => 'sync_scan', // Explicit type for Triad View
                    'audit_context' => 'sync',
                    'comparison_data_snapshot' => $comparisonResult,
                    
                    // TITAN V6.3 FIX: Inject Topology for "Structural DNA Mapper"
                    'topology' => $webData['topology'] ?? [],
                    'repo_structure' => $repoData['structure'] ?? [],
                    
                    // TITAN V6.7 USER REQUEST: Store Linked IDs for easy access
                    'linked_histories' => [
                        'web' => $webAsset->id, // Asset ID (Used by Frontend)
                        'repo' => $repoAsset->id // Asset ID
                    ],
                    
                    'heuristic_gates' => [
                        'structural_dna' => (($aiScores['structural_dna_score'] ?? 0) > 40 || $context['topology_match']) ? 'verified' : 'drift',
                        'tech_stack' => (count($matchingTech) > 0 || ($aiScores['tech_stack_score'] ?? 0) > 15) ? 'verified' : 'flagged',
                        'strategic_context' => ($context['strategic_match'] || ($aiScores['strategic_context_score'] ?? 0) > 5) ? 'verified' : 'flagged',
                        'identity_verdict' => $context['identity_match'] ? 'verified' : 'flagged'
                    ],

                    // PERSIST VECTORS FOR HISTORY ACCURACY
                    'breakdown' => $comparisonResult['hexagon_vectors'] ?? [],
                    'risk_matrix' => [
                        'performance' => $comparisonResult['sync_score'] ?? 0,
                        'security' => $comparisonResult['security_score'] ?? $score,
                        'scalability' => $comparisonResult['scalability_score'] ?? $score,
                    ]
                ],
                'scanned_at' => now(),
            ]);

            // --- TITAN V6.5: THE STITCHING PHASE (Safety Link) ---
            try {
                // 1. Fetch Siblings from this Batch
                $webHistory = \App\Models\VaultHistory::where('batch_id', $this->syncBatchId)
                    ->where('metadata->audit_type', 'website_scan')
                    ->latest()
                    ->first();

                $repoHistory = \App\Models\VaultHistory::where('batch_id', $this->syncBatchId)
                    ->where('metadata->audit_type', 'repository_scan')
                    ->latest()
                    ->first();

                // 2. Cross-Link and Inject Sync Score
                if ($webHistory && $repoHistory) {
                    $webHistory->update([
                        'sync_score' => $score,
                        'linked_history_id' => $repoHistory->id
                    ]);

                    $repoHistory->update([
                        'sync_score' => $score,
                        'linked_history_id' => $webHistory->id
                    ]);
                    
                    Log::info("TITAN V6.5: History Stitching Complete", [
                        'web_hist_id' => $webHistory->id,
                        'repo_hist_id' => $repoHistory->id,
                        'linked' => true
                    ]);
                } else {
                    Log::warning("TITAN V6.5: History Stitching Skipped - Missing Siblings", [
                        'has_web' => !!$webHistory,
                        'has_repo' => !!$repoHistory
                    ]);
                }
            } catch (\Exception $stitchError) {
                // Non-critical, just log it
                Log::error("TITAN V6.5: History Stitching Failed: " . $stitchError->getMessage());
            }

            $webAsset->touch();
            $webAsset->save(); // This saves file_size too

            // Update Repo Asset (Link back and status)
            $repoMetadata = $repoAsset->metadata ?? [];
            $repoMetadata['synced_with_web_id'] = $webAsset->id;
            $repoMetadata['comparison_status_label'] = $comparisonStatus;
            
            $repoAsset->metadata = $repoMetadata;
            $repoAsset->status = $status;
            $repoAsset->touch();
            $repoAsset->save(); // This saves file_size too
            
            // Broadcast update
            event(new \App\Events\AssetStatusUpdated($webAsset));
            event(new \App\Events\AssetStatusUpdated($repoAsset));

            // Unified Scan Activity Record (New Requirement)
            // Unified Scan Activity Record (New Requirement)
            // Update existing record if exists to prevent duplicates in list
            \App\Models\ScanActivity::updateOrCreate(
                [
                    'batch_id' => $this->syncBatchId, // Unique per batch
                    'type' => 'sync'
                ],
                [
                    'primary_asset_id' => $webAsset->id,
                    'user_id' => $webAsset->user_id,
                    'urls_and_sync' => "Sync: " . ($webAsset->file_name ?? 'Web') . " & " . ($repoAsset->file_name ?? 'Repo'),
                    'secondary_asset_id' => $repoAsset->id,
                    'sync_status' => ($score > 85 ? 'verified' : ($score > 74 ? 'action_required' : 'flagged')),
                    'docu_and_urls_status' => null,
                    'sync_confidence_score' => $score,
                    'individual_score' => null,
                    'vectors' => $comparisonResult['hexagon_vectors'] ?? [], // Sync Vectors
                    'scanned_at' => now(),
                ]
            );

            event(new \App\Events\AuditProgressUpdated($progressAsset, "Finalizing", 98, 'processing', "Handshake Verified. Finalizing Report..."));
            
            Log::info("PerformSyncComparison: Comparison Complete. Score: " . $score . " Status: " . $status);
            
            // --- TITAN V7: Finalize Marketplace Listing Status ---
            $listing = \App\Models\MarketplaceListing::where('vault_asset_id', $webAsset->id)
                ->orWhere('vault_asset_id', $repoAsset->id)
                ->first();
            
            if ($listing) {
                if ($score < 85) {
                    $listing->update(['status' => 'flagged']);
                } else {
                    $listing->update([
                        'status' => 'active',
                        'ai_score_snapshot' => (int) $score
                    ]);
                }
            }

            event(new \App\Events\AuditProgressUpdated($progressAsset, "Complete", 100, 'verified', "Titan Sync Complete."));
            event(new \App\Events\AuditProgressUpdated($repoAsset, "Complete", 100, 'verified', "Titan Sync Complete."));
        } catch (\Exception $e) {
            Log::error("PerformSyncComparison Failed: " . $e->getMessage());
        }
    }
}
