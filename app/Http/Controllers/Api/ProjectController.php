<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Services\HealthCheckService;
use App\Services\CredentialValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private HealthCheckService $healthService,
        private CredentialValidationService $credentialService
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
            'audit_type' => 'nullable|string|in:project,design',
            'proof_asset_ids' => 'nullable|array',
            'proof_asset_ids.*' => 'string|exists:vault_assets,id',
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
        $auditType = $validated['audit_type'] ?? 'project';

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
                'score' => $existingVaultAsset->metadata['confidence_score'] ?? ($existingVaultAsset->score ?? 0),
                'status' => $existingVaultAsset->status,
                'metadata' => $existingVaultAsset->metadata,
                'created_at' => now(),
            ]);
        }

        $vaultAsset = VaultAsset::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'file_name' => $productionUrl, // Unique by URL + user
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
                // Reset radar_data and full_audit_report on re-scan
                'radar_data' => null,
                'full_audit_report' => null,
            ]
        );

        Log::info('VaultAsset updated/created for project scan', [
            'vault_asset_id' => $vaultAsset->id,
            'project_id' => $project->id,
            'was_recently_created' => $vaultAsset->wasRecentlyCreated,
        ]);

        // Link Proofs if provided
        if (!empty($validated['proof_asset_ids'])) {
            VaultAsset::whereIn('id', $validated['proof_asset_ids'])
                ->update([
                    'status' => 'processing',
                    'metadata->project_asset_id' => $project->id, 
                    'metadata->vault_asset_id' => $vaultAsset->id,
                    'metadata->is_proof' => true
                ]);
        }

        // Dispatch background scan job with the VaultAsset ID
        \App\Jobs\PerformProjectScan::dispatch($project, $validated['github_token'] ?? null, $vaultAsset->id);

        Log::info('PerformProjectScan job dispatched', [
            'project_id' => $project->id,
            'vault_asset_id' => $vaultAsset->id,
        ]);

        return response()->json([
            'success' => true,
            'project' => $project,
            'asset' => $vaultAsset, // Return real VaultAsset for modal
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
}
