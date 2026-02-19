<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Services\HealthCheckService;
use App\Services\CredentialValidationService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private HealthCheckService $healthService,
        private CredentialValidationService $credentialService,
        private LedgerService $ledgerService
    ) {}

    /**
     * Create a new project asset for scanning.
     */
    public function create(Request $request)
    {
        Log::info('Project Scan Starting', [
            'url' => $request->input('website_url') ?? $request->input('github_repo_url'),
            'user_id' => Auth::id(),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'website_url' => 'nullable|url|max:500',
            'github_repo_url' => 'nullable|url|max:500',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'monthly_visitors' => 'nullable|integer|min:0',
            'github_token' => 'nullable|string',
            'audit_type' => 'nullable|string|in:project,design,repository',
            'proof_asset_ids' => 'nullable|array',
            'proof_asset_ids.*' => 'string|exists:vault_assets,id',
            'batch_id' => 'nullable|string|exists:vault_assets,batch_id', // TITAN V8: Allow forcing specific batch update
        ]);

        // At least one URL is required
        if (empty($validated['website_url']) && empty($validated['github_repo_url'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of website URL or GitHub repository URL is required.',
            ], 422);
        }

        // Generate verification UUID
        $verificationUuid = $this->healthService->generateVerificationUuid();

        // Determine the production URL (priority: website > github)
        $productionUrl = $validated['website_url'] ?? $validated['github_repo_url'];
        
        // REFINED AUDIT TYPE: Determine if it's a Repo Scan vs Website Scan for immediate UI feedback
        $auditType = $validated['audit_type'] ?? 'project';
        if (empty($validated['website_url']) && !empty($validated['github_repo_url'])) {
             $auditType = 'repository_scan';
        }

        // Defensive: Prevent website_url from being same as repo url (User error or frontend spill)
        if (!empty($validated['website_url']) && !empty($validated['github_repo_url'])) {
            if ($validated['website_url'] === $validated['github_repo_url']) {
                // Determine which one it really is? 
                // If it looks like github, prioritize repo.
                if (str_contains($validated['website_url'], 'github.com')) {
                    $validated['website_url'] = null;
                } else {
                    $validated['github_repo_url'] = null;
                }
            }
        }

        // Create ProjectAsset for tracking
        $project = ProjectAsset::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'github_repo_url' => $validated['github_repo_url'] ?? null,
            'verification_uuid' => $verificationUuid,
            'monthly_revenue' => $validated['monthly_revenue'] ?? null,
            'monthly_visitors' => $validated['monthly_visitors'] ?? null,
            'status' => 'pending_verification',
        ]);

        // Create or Update VaultAsset to prevent duplicates on re-scan
        // Uses file_name (productionUrl) + user_id as unique identifier
        
        // 1. Check for existing asset to save HISTORY
        $existingVaultAsset = VaultAsset::where('user_id', Auth::id())
            ->where('file_name', $productionUrl)
            ->first();

        if ($existingVaultAsset && in_array($existingVaultAsset->status, ['verified', 'verified_private', 'flagged', 'action_required'])) {
            \App\Models\AuditHistory::create([
                'vault_asset_id' => $existingVaultAsset->id,
                'score' => (int) ($existingVaultAsset->metadata['confidence_score'] ?? ($existingVaultAsset->score ?? 0)), // Cast to int for PGSQL
                'status' => $existingVaultAsset->status,
                'metadata' => $existingVaultAsset->metadata,
                'created_at' => now(),
            ]);
        }

        // --- ENFORCE CREDITS / QUOTA ---
        $isSync = !empty($validated['website_url']) && !empty($validated['github_repo_url']);
        // TITAN V9.2: Correctly map ledger type. Websites/Repos are PROJECTS (10 credits), not documents (1 credit).
        $ledgerAuditType = $isSync ? 'sync' : 'project'; 
        
        if (!$this->ledgerService->hasCreditsForAudit(Auth::user(), $ledgerAuditType)) {
             return response()->json([
                 'success' => false,
                 'message' => 'Insufficient credits or quota. Please upgrade your plan or purchase more credits.',
             ], 402);
        }

        // Lock credits upfront (returns 0 if quota used)
        $this->ledgerService->lockAuditCredits(Auth::user(), $ledgerAuditType, $validated['name']);

        // 3. DISPATCH LOGIC: "The Trinity" (Web, Repo, or Sync)
        $repoAsset = null;
        $vaultAsset = null; // Will be assigned based on context
        
        // CASE A: SYNC SCAN (Web + Repo)
        if (!empty($validated['website_url']) && !empty($validated['github_repo_url'])) {
            Log::info('Dispatching PerformSyncScan (Dual Mode)');
            
            // TITAN V2: Context Isolation & History Strategy
            // 1. Check if this PAIR already exists (Web URL + Context + Sibling Repo)
            // TITAN V8: Check for explicit batch_id FIRST to allow forced re-scans of existing batches
            $existingWeb = null;
            
            if (!empty($validated['batch_id'])) {
                 $existingWeb = VaultAsset::where('batch_id', $validated['batch_id'])
                    ->where('user_id', Auth::id())
                    ->where('metadata->context', 'sync_child') // Ensure we don't pick up garbage
                    ->first();
            }

            // Fallback: Check strictly by URLs if no batch_id provided or found
            if (!$existingWeb) {
                $existingWeb = VaultAsset::where('file_name', $productionUrl)
                    ->where('user_id', Auth::id())
                    ->where('metadata->context', 'sync_child')
                    ->whereHas('sibling', function($q) use ($validated) {
                         $q->where('file_name', $validated['github_repo_url']);
                    })->first();
            }

            $batchId = null;
            $webAsset = null;
            $repoAsset = null;

            if ($existingWeb) {
                // SCENARIO: EXISTING PAIR -> UPDATE & ARCHIVE
                Log::info('Found existing Sync Pair. Archiving and Updating.', ['batch_id' => $existingWeb->batch_id]);
                
                $batchId = $existingWeb->batch_id;
                
                // Find the Sibling Repo
                $existingRepo = VaultAsset::where('batch_id', $batchId)
                    ->where('file_name', $validated['github_repo_url'])
                    ->first();

                // Archive Both
                foreach ([$existingWeb, $existingRepo] as $asset) {
                    if ($asset) {
                        \App\Models\VaultHistory::create([
                            'vault_asset_id' => $asset->id,
                            'batch_id' => $asset->batch_id,
                            'score' => $asset->score,
                            'status' => $asset->status,
                            'metadata' => $asset->metadata,
                            'scanned_at' => $asset->updated_at,
                        ]);
                    }
                }

                // Update Logic (Reuse ID)
                $webAsset = $existingWeb;
                $webAsset->update([
                    'status' => 'processing',
                    // Keep metadata but ensure URL is fresh if needed
                    'metadata' => array_merge($webAsset->metadata ?? [], [
                        'audit_type' => 'website_scan',
                        'website_url' => $validated['website_url'],
                        'project_asset_id' => $project->id,
                        'is_project' => true,
                        'context' => 'sync_child'
                    ]),
                    'radar_data' => null // Reset data
                ]);

                $repoAsset = $existingRepo;
                $repoAsset->update([
                    'status' => 'processing',
                    'metadata' => array_merge($repoAsset->metadata ?? [], [
                        'audit_type' => 'repository_scan', 
                        'is_repo_scan' => true,
                        'github_repo_url' => $validated['github_repo_url'],
                        'project_asset_id' => $project->id,
                        'is_project' => true,
                        'context' => 'sync_child'
                    ]),
                    'radar_data' => null
                ]);

            } else {
                // SCENARIO: NEW PAIR -> CREATE NEW
                Log::info('New Sync Pair Detected. Creating new Batch.');
                $batchId = 'SYNC-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
                
                // 1. Create Web
                $webAsset = VaultAsset::create([
                    'user_id' => Auth::id(), 
                    'file_name' => $productionUrl, 
                    'batch_id' => $batchId,
                    'status' => 'processing',
                    'metadata' => [
                        'audit_type' => 'website_scan',
                        'website_url' => $validated['website_url'],
                        'project_asset_id' => $project->id,
                        'is_project' => true,
                        'context' => 'sync_child'
                    ],
                ]);
                
                // 2. Create Repo
                $repoAsset = VaultAsset::create([
                    'user_id' => Auth::id(), 
                    'file_name' => $validated['github_repo_url'], 
                    'batch_id' => $batchId,
                    'status' => 'processing',
                    'metadata' => [
                        'audit_type' => 'repository_scan', 
                        'is_repo_scan' => true,
                        'github_repo_url' => $validated['github_repo_url'],
                        'project_asset_id' => $project->id,
                        'is_project' => true,
                        'context' => 'sync_child'
                    ],
                ]);
            }

            // Primary Asset for Response is the Web Asset (Dashboard convention)
            $vaultAsset = $webAsset;

            // Dispatch with the PRE-GENERATED Batch ID
            \App\Jobs\PerformSyncScan::dispatch(
                $project, 
                $webAsset->id, 
                $repoAsset->id, 
                $validated['github_token'] ?? null,
                $batchId // Pass the ID we just used
            );
        }
        // CASE B: REPO ONLY
        elseif (!empty($validated['github_repo_url'])) {
             Log::info('Dispatching PerformGithubRepositoryScan (Repo Mode)');
             
             $vaultAsset = VaultAsset::updateOrCreate(
                ['user_id' => Auth::id(), 'file_name' => $validated['github_repo_url'], 'batch_id' => null],
                [
                    'status' => 'processing',
                    'metadata' => [
                        'audit_type' => 'repository_scan',
                        'is_repo_scan' => true,
                        'github_repo_url' => $validated['github_repo_url'],
                        'project_asset_id' => $project->id,
                        'is_project' => true
                    ],
                    'radar_data' => null
                ]
             );
             
             \App\Jobs\PerformGithubRepositoryScan::dispatch($project, $validated['github_token'] ?? null, $vaultAsset->id);
        }
        // CASE C: WEB ONLY
        else {
             Log::info('Dispatching PerformProjectScan (Web Mode)');
             
             $vaultAsset = VaultAsset::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'file_name' => $productionUrl, // Unique by URL + user
                    'batch_id' => null, // TITAN V2: Ensure we only touch 'Solo' assets
                ],
                [
                    'file_path' => null, // No physical file for project scans
                    'file_size' => null,
                    'mime_type' => null,
                    'status' => 'processing',
                    'metadata' => [
                        'audit_type' => $auditType,
                        'website_url' => $validated['website_url'] ?? null,
                        'github_repo_url' => $validated['github_repo_url'] ?? null,
                        'project_asset_id' => $project->id,
                        'is_project' => true,
                        'verification_uuid' => $verificationUuid,
                    ],
                    'radar_data' => null,
                    'full_audit_report' => null,
                ]
            );
             
             \App\Jobs\PerformProjectScan::dispatch($project, $validated['github_token'] ?? null, $vaultAsset->id);
        }
        
        // COMMON: Link Proofs to whichever asset became the Primary
        if (!empty($validated['proof_asset_ids']) && $vaultAsset) {
            VaultAsset::whereIn('id', $validated['proof_asset_ids'])
                ->update([
                    'status' => 'processing',
                    'metadata->project_asset_id' => $project->id, 
                    'metadata->vault_asset_id' => $vaultAsset->id,
                    'metadata->is_proof' => true
                ]);
        }

        Log::info('Scan job dispatched', [
            'project_id' => $project->id,
            'vault_asset_id' => $vaultAsset->id,
            'mode' => !empty($validated['github_repo_url']) ? 'repo' : 'web'
        ]);

        return response()->json([
            'success' => true,
            'project' => $project,
            'asset' => [
                'id' => $vaultAsset->id,
                'user_id' => $vaultAsset->user_id,
                'file_name' => $vaultAsset->file_name,
                'file_size' => $vaultAsset->file_size,
                'mime_type' => $vaultAsset->mime_type,
                'status' => $vaultAsset->status,
                'metadata' => $vaultAsset->metadata,
                'created_at' => $vaultAsset->created_at->toISOString(),
                'updated_at' => $vaultAsset->updated_at->toISOString(),
            ],
            'repo_asset' => $repoAsset ? [
                'id' => $repoAsset->id,
                'status' => $repoAsset->status,
                'file_name' => $repoAsset->file_name,
            ] : null,
            'verification_uuid' => $verificationUuid,
            'verification_meta_tag' => "<meta name=\"lume-verification\" content=\"{$verificationUuid}\">",
        ]);
    }

    /**
     * Run health check on website and GitHub repo.
     */
    public function healthCheck(Request $request, ProjectAsset $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $results = [];

        // Check website if provided
        if ($project->website_url) {
            $results['website'] = $this->healthService->checkWebsite($project->website_url);
            $results['domain'] = $this->healthService->checkDomainWhois($project->website_url);
        }

        // Check GitHub repo if provided
        if ($project->github_repo_url) {
            $token = $project->getGithubToken();
            $results['github'] = $this->healthService->checkGitHubRepo($project->github_repo_url, $token);
        }

        // Update project with health data
        $project->update([
            'health_data' => $results['website'] ?? null,
            'github_data' => $results['github'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Verify site ownership via meta tag.
     */
    public function verifySiteOwnership(Request $request, ProjectAsset $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$project->website_url) {
            return response()->json([
                'success' => false,
                'message' => 'No website URL to verify.',
            ], 422);
        }

        $result = $this->healthService->verifySiteOwnership(
            $project->website_url,
            $project->verification_uuid
        );

        if ($result['verified']) {
            $project->update([
                'website_verified' => true,
                'verified_at' => now(),
                'status' => $project->github_verified || !$project->github_repo_url ? 'verified' : 'pending_verification',
            ]);
        }

        return response()->json([
            'success' => $result['verified'],
            'verified' => $result['verified'],
            'found_value' => $result['found_value'],
            'error' => $result['error'],
        ]);
    }

    /**
     * Validate credentials (GitHub token, Cloudflare key) with dry-run.
     */
    public function validateCredentials(Request $request, ProjectAsset $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'github_token' => 'required|string|min:10',
            'cloudflare_key' => 'nullable|string',
            'cloudflare_email' => 'nullable|email',
        ]);

        // Dry-run validation
        $dryRunResult = $this->credentialService->dryRunValidation([
            'github_token' => $validated['github_token'],
            'repo_url' => $project->github_repo_url,
            'cloudflare_key' => $validated['cloudflare_key'] ?? null,
            'cloudflare_email' => $validated['cloudflare_email'] ?? null,
        ]);

        if (!$dryRunResult['success']) {
            return response()->json([
                'success' => false,
                'validation' => $dryRunResult,
                'errors' => $dryRunResult['errors'],
            ], 422);
        }

        // Credentials are valid - store them encrypted
        $project->setGithubToken($validated['github_token']);
        if (!empty($validated['cloudflare_key'])) {
            $project->setCloudflareKey($validated['cloudflare_key']);
            $project->cloudflare_email = $validated['cloudflare_email'] ?? null;
        }

        // Mark GitHub as verified if we have access
        if ($dryRunResult['github']['has_repo_access']) {
            $project->github_verified = true;
            if ($project->website_verified || !$project->website_url) {
                $project->status = 'verified';
                $project->verified_at = now();
            }
        }

        $project->save();

        return response()->json([
            'success' => true,
            'validation' => $dryRunResult,
            'project' => $project->fresh(),
        ]);
    }

    /**
     * List project for sale.
     */
    public function listForSale(Request $request, ProjectAsset $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'asking_price' => 'required|numeric|min:1|max:10000000',
        ]);

        if (!$project->canBeListed()) {
            return response()->json([
                'success' => false,
                'message' => 'Project must be fully verified with a LUME score of 70+ to be listed.',
            ], 422);
        }

        // Enforce LUME Handshake Verification
        $infraScan = $project->audit_data['infrastructure_scan'] ?? [];
        $liveVerification = $infraScan['live_verification'] ?? [];
        
        // Only require handshake if a website URL is present
        if ($project->website_url && (empty($liveVerification['handshake_passed']) || !$liveVerification['handshake_passed'])) {
             return response()->json([
                'success' => false,
                'message' => 'LUME Handshake verification failed. You must upload the "lume_handshake.txt" file to your server\'s public directory and run the infrastructure scan again to prove ownership.',
            ], 422);
        }

        $project->update([
            'asking_price' => $validated['asking_price'],
            'status' => 'listed',
        ]);

        return response()->json([
            'success' => true,
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Get project details.
     */
    public function show(ProjectAsset $project)
    {
        // Public projects (listed) can be viewed by anyone
        if ($project->status !== 'listed' && $project->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'success' => true,
            'project' => $project->load('user:id,name'),
        ]);
    }

    /**
     * Get user's projects.
     */
    public function myProjects()
    {
        $projects = ProjectAsset::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'projects' => $projects,
        ]);
    }

    /**
     * Scan infrastructure to detect tech stack, DNS provider, and required env variables.
     */
    public function scanInfrastructure(Request $request, ProjectAsset $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$project->github_repo_url && !$project->website_url) {
            return response()->json([
                'success' => false,
                'message' => 'No GitHub repo or website URL to scan.',
            ], 422);
        }

        // Run the infrastructure scan
        $scanner = app(\App\Services\InfrastructureScannerService::class);
        $results = $scanner->scanProject($project);

        // Store the results in the project
        $project->update([
            'audit_data' => array_merge($project->audit_data ?? [], [
                'infrastructure_scan' => $results,
            ]),
            'transfer_requirements' => $results['env_variables'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'results' => $results,
            'project' => $project->fresh(),
        ]);
    }
    /**
     * Compare two assets (Website vs Repository).
     */
    public function compare(Request $request)
    {
        $request->validate([
            'asset_ids' => 'required|array|min:2|max:2',
            'asset_ids.*' => 'exists:vault_assets,id'
        ]);

        $assets = VaultAsset::whereIn('id', $request->input('asset_ids'))->get();
        if ($assets->count() !== 2) {
             return response()->json(['success' => false, 'message' => 'Assets not found.'], 404);
        }

        // Auto-detect which is Web and which is Repo based on metadata or audit_type
        // Heuristic: 'project' audit_type with 'is_project' usually implies Web in the old system, 
        // but now we have separated them. 
        // We can check 'github_repo_url' vs 'website_url' in metadata.
        
        $assetA = $assets[0];
        $assetB = $assets[1];
        
        // Simple heuristic: If one has 'github_data' or 'file_count' in metadata and the other doesn't?
        // Or check the file_name (which is the url).
        
        $isARepo = str_contains($assetA->file_name, 'github.com');
        $isBRepo = str_contains($assetB->file_name, 'github.com');
        
        $webAsset = $isARepo ? $assetB : $assetA;
        $repoAsset = $isARepo ? $assetA : $assetB;
        
        // Comparator Service
        $comparator = new \App\Services\ComparatorService();
        $comparison = $comparator->compare($webAsset, $repoAsset);
        
        return response()->json([
            'success' => true,
            'web_asset' => $webAsset,
            'repo_asset' => $repoAsset,
            'comparison' => $comparison
        ]);
    }
}
