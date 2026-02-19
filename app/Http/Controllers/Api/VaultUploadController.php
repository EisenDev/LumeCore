<?php

namespace App\Http\Controllers\Api;

use App\Events\AssetUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteAssetFromR2;
use App\Models\VaultAsset;
use App\Services\AI\DocumentAuditor;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class VaultUploadController extends Controller
{
    /**
     * Generate a presigned URL for direct upload to R2.
     *
     * TypeScript Response Interface:
     * ```typescript
     * interface PresignedUrlResponse {
     *     upload_url: string;      // The presigned URL for PUT request
     *     headers: {               // Required headers for the upload
     *         'Content-Type': string;
     *     };
     *     asset_id: string;        // UUID of the created vault asset
     *     expires_at: string;      // ISO 8601 timestamp when URL expires
     * }
     * ```
     */
    public function getPresignedUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $fileName = $validated['file_name'];
        $fileType = $validated['file_type'];

        // Generate a unique file path using slugified email for URL-safe directory names
        $userSlug = Str::slug($user->email);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueName = Str::uuid() . '.' . $extension;
        $filePath = "vault/{$userSlug}/{$uniqueName}";

        // Create a pending vault asset record
        $asset = VaultAsset::create([
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => 0, // Will be updated after upload confirmation
            'mime_type' => $fileType,
            'status' => 'pending',
            'metadata' => null,
        ]);

        // Generate presigned upload URL (5 minutes expiry)
        $expiresAt = now()->addMinutes(5);

        /** @var array{url: string, headers: array<string, string>} $presignedData */
        $presignedData = Storage::disk('r2')->temporaryUploadUrl(
            $filePath,
            $expiresAt,
            [
                'ContentType' => $fileType,
            ]
        );

        return response()->json([
            'upload_url' => $presignedData['url'],
            'headers' => [
                'Content-Type' => $fileType,
            ],
            'asset_id' => $asset->id,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Confirm that the upload has completed and update asset status.
     *
     * TypeScript Response Interface:
     * ```typescript
     * interface ConfirmUploadResponse {
     *     success: boolean;
     *     asset_id: string;
     *     status: 'uploaded';
     * }
     * ```
     */
    public function confirmUpload(Request $request, LedgerService $ledgerService, DocumentAuditor $auditor): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'uuid'],
            'audit_type' => ['nullable', 'string', 'in:document,project'],
        ]);

        $user = $request->user();
        $auditType = $validated['audit_type'] ?? 'document';

        // Find the asset and verify ownership
        $asset = VaultAsset::where('id', $validated['asset_id'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Update file size from R2
        $fileSize = Storage::disk('r2')->size($asset->file_path);

        // Get credit cost from LedgerService constants
        // Document Audit: 1 Credit | Project Audit: 5 Credits
        $creditCost = LedgerService::getAuditFee($auditType);
        
        // AI Auditor: Extract text and analyze
        // Default values
        $verificationStatus = 'flagged';
        $analysisResult = null;

        try {
            $mimeType = $asset->mime_type;
            $text = '';

            // Get file content from R2
            $content = Storage::disk('r2')->get($asset->file_path);

            // Enforce File Types for Document Audit
            if ($auditType === 'document') {
                $allowedMimes = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                    'application/msword', // doc
                ];
                $isImage = Str::startsWith($mimeType, 'image/');
                
                if (!in_array($mimeType, $allowedMimes) && !$isImage) {
                    // Cleanup R2 and DB
                    Storage::disk('r2')->delete($asset->file_path);
                    $asset->delete();
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid file type. Only PDF, DOCX, and Images are allowed for document analysis.',
                        'error' => 'Unsupported file type: ' . $mimeType
                    ], 422);
                }
            }

            if ($mimeType === 'application/pdf') {
                $parser = new Parser();
                $pdf = $parser->parseContent($content);
                $text = $pdf->getText();
                
                // Determine if this is a visual (image-based) PDF or text-based
                $isVisual = strlen(trim($text)) < 50;
                
                if ($isVisual) {
                    Log::info("Handling Visual PDF for asset {$asset->id}");
                } else {
                    Log::info("Handling Text PDF for asset {$asset->id}");
                }
            } elseif (Str::startsWith($mimeType, 'image/')) {
                Log::info("Handling Image Upload for asset {$asset->id} (Mime: {$mimeType})");
            } elseif (Str::startsWith($mimeType, 'text/') || $mimeType === 'application/json') {
                Log::info("Handling Text Upload for asset {$asset->id}");
            } else {
                Log::warning("Unsupported file type for asset {$asset->id}: {$mimeType}");
            }

            if ($auditType === 'document') {
                $asset->update([
                    'file_size' => $fileSize, 
                    'status' => 'uploaded',
                    'metadata' => [
                        'audit_type' => 'document',
                        'document_type' => 'Pending Scan',
                        'summary' => 'Initial upload complete. Ready for manual scan.',
                    ]
                ]);
                return response()->json([
                    'success' => true,
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                    'status' => 'uploaded',
                    'payout' => 0,
                    'message' => 'Upload successful. Ready for manual scan.',
                    'metadata' => $asset->metadata,
                    'file_size' => $asset->file_size,
                ]);
            }

            // CHECK CREDITS (DO NOT DEDUCT YET)
            // Deduction happens at the end of a successful audit in VaultAuditService
            if (!$ledgerService->hasCreditsForAudit($user, $auditType)) {
                $asset->update(['file_size' => $fileSize, 'status' => 'uploaded']);
                return response()->json([
                    'success' => true,
                    'asset_id' => $asset->id,
                    'status' => 'payment_required',
                    'message' => "Insufficient credits. {$creditCost} credit(s) required for {$auditType} audit.",
                    'required_credits' => $creditCost,
                    'payout' => 0,
                ]);
            }

            // ASYNC AUDIT: Credits paid - now start the job
            // Update status to 'processing' and dispatch job
            $asset->update([
                'file_size' => $fileSize,
                'status' => 'processing',
                'metadata' => [
                    'summary' => 'Queued for LUME Sovereign Audit...',
                    'confidence_score' => 0,
                    'is_professional' => false,
                    'document_type' => 'Processing',
                    'audit_type' => $auditType,
                    'credit_cost' => $creditCost,
                ]
            ]);

            // Dispatch Async Job with audit type
            \App\Jobs\AuditVaultAsset::dispatch($asset, $auditType);

            // Record upload trail
            $ledgerService->recordAssetUpload($asset);
            AssetUploaded::dispatch($asset);

        } catch (\Throwable $e) {
            Log::error("Upload failed for asset {$asset->id}: " . $e->getMessage());
            
            // AUTOMATIC REFUND ON FAILURE
            $ledgerService->refundAuditCredits($user, $auditType, $asset->file_name);
            
            $asset->update(['status' => 'flagged']);
        }

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'status' => 'processing',
            'payout' => 0,
            'message' => 'Upload successful. LUME Semantic analysis in progress.',
            'metadata' => $asset->metadata,
            'file_size' => $asset->file_size,
        ]);
    }

    /**
     * Initiate a manual document scan.
     * Requires Active Subscription AND 1 Credit.
     */
    public function scanDocument(Request $request, LedgerService $ledgerService): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        Log::info("SCAN DOCUMENT: API hit for asset {$validated['asset_id']} by user {$user->id}");

        // 1. SUBSCRIPTION CHECK
        if (!$user->activeSubscription) {
             Log::warning("SCAN DOCUMENT: BLOCKED - No active subscription for user {$user->id}");
             return response()->json([
                'success' => false,
                'error' => 'Active subscription required.',
                'message' => 'You must have an active subscription to scan documents.',
                'requires_subscription' => true
            ], 403);
        }

        // 2. ASSET OWNERSHIP
        $asset = VaultAsset::where('id', $validated['asset_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        Log::info("SCAN DOCUMENT: Asset found - {$asset->file_name} (status: {$asset->status})");

        // 3. CREDIT CHECK (1 Credit)
        if (!$ledgerService->hasCreditsForAudit($user, 'document')) {
             Log::warning("SCAN DOCUMENT: BLOCKED - Insufficient credits for user {$user->id}");
             return response()->json([
                'success' => false,
                'error' => 'Insufficient credits.',
                'message' => 'Document scan requires 1 credit.',
            ], 402);
        }

        // 4. DEDUCT & DISPATCH
        $ledgerService->lockAuditCredits($user, 'document', $asset->file_name ?? 'Document Scan');
        
        Log::info("SCAN DOCUMENT: Credits locked. Dispatching PerformDocumentScan for asset {$asset->id}");
        
        \App\Jobs\PerformDocumentScan::dispatch($asset);

        Log::info("SCAN DOCUMENT: Job dispatched successfully for asset {$asset->id}");

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'message' => 'Document scan initiated.',
            'status' => 'processing'
        ]);

    }

    /**
     * Generate a temporary download URL for an asset.
     *
     * TypeScript Response Interface:
     * ```typescript
     * interface DownloadUrlResponse {
     *     download_url: string;
     *     file_name: string;
     *     expires_at: string;
     * }
     * ```
     */
    public function download(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        // Find the asset and verify ownership
        $asset = VaultAsset::where('id', $validated['asset_id'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['uploaded', 'processing', 'ready'])
            ->firstOrFail();

        // Generate temporary download URL (15 minutes expiry)
        $expiresAt = now()->addMinutes(15);

        $downloadUrl = Storage::disk('r2')->temporaryUrl(
            $asset->file_path,
            $expiresAt
        );

        return response()->json([
            'download_url' => $downloadUrl,
            'file_name' => $asset->file_name,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Delete an asset from the database and R2 storage.
     *
     * TypeScript Response Interface:
     * ```typescript
     * interface DeleteAssetResponse {
     *     success: boolean;
     *     asset_id: string;
     * }
     * ```
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        // Find the asset and verify ownership
        $asset = VaultAsset::where('id', $validated['asset_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Store values before deletion
        $assetId = $asset->id;
        $filePath = $asset->file_path;

        // Delete the database record FIRST for instant UI update
        $asset->delete();

        // Dispatch job to delete file from R2 in the background
        DeleteAssetFromR2::dispatch($filePath);

        return response()->json([
            'success' => true,
            'asset_id' => $assetId,
            'asset' => [
                'id' => $asset->id,
                'user_id' => $asset->user_id,
                'file_name' => $asset->file_name,
                'file_size' => $asset->file_size,
                'mime_type' => $asset->mime_type,
                'status' => $asset->status,
                'metadata' => $asset->metadata,
                 // Add other fields as needed by frontend
                'created_at' => $asset->created_at->toISOString(),
                'updated_at' => $asset->updated_at->toISOString(),
            ]
        ]);
    }
    /**
     * Get a single asset by ID.
     * Used for polling status updates.
     */
    public function getAsset(Request $request, VaultAsset $asset): JsonResponse
    {
        // Ensure user owns this asset
        if ($asset->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'asset' => $asset,
        ]);
    }


    /**
     * Detect context from the provided URL.
     */
    /**
     * Detect context from the provided URL.
     */
    /**
     * Detect context from the provided URL.
     * TLD-Agnostic Validation Update.
     */
    public function detectContext(Request $request): JsonResponse
    {
        // 1. Basic Input Validation (No strict Regex on content yet)
        $validated = $request->validate(['url' => 'required|string']); 
        $url = trim($validated['url']);
        
        // 2. Protocol Normalization (Frontend check relies on this)
        // Ensure "http://" or "https://" presence for filter_var to work correctly on hosts
        if (!preg_match('~^https?://~i', $url)) {
            $urlToCheck = 'https://' . $url;
        } else {
            $urlToCheck = $url;
        }

        // 3. Structure Verification (PHP Native)
        // TLD-Agnostic: filter_var validates structure, not the specific extension.
        if (!filter_var($urlToCheck, FILTER_VALIDATE_URL)) {
            return response()->json([
                'status' => 'success',
                'context' => [
                    'type' => 'unknown',
                    'message' => 'Invalid URL structure.',
                    'next_step_instruction' => 'Please enter a valid URL (e.g., https://example.tech).'
                ]
            ]);
        }

        $components = parse_url($urlToCheck);
        $host = $components['host'] ?? null;

        if (!$host) {
             return response()->json([
                'status' => 'success',
                'context' => [
                    'type' => 'unknown',
                    'message' => 'Could not parse hostname.',
                    'next_step_instruction' => 'Please check the URL format.'
                ]
            ]);
        }

        $context = [];

        // 4. Platform Detection
        if (str_contains($host, 'figma.com')) {
            $context = [
                'type' => 'design',
                'requires_images' => true,
                'min_images' => 5,
                'max_images' => 10,
                'message' => 'Figma Design detected.',
                'next_step_instruction' => 'Upload 5-10 screenshots including Mobile View and Component Library.'
            ];
        } elseif (str_contains($host, 'github.com')) {
            $context = [
                'type' => 'repo',
                'is_private' => 'check_via_api',
                'message' => 'GitHub Repository detected.',
                'next_step_instruction' => 'Do you have a live demo URL for this codebase?'
            ];
        } else {
            // 5. DNS Verification (Existence Check)
            // checkdnsrr verifies the domain actually exists on the internet (A or AAAA record),
            // making it completely agnostic to the TLD (.tech, .io, .xyz all work).
            $hasDns = checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA') || checkdnsrr($host, 'CNAME');

            if (!$hasDns) {
                return response()->json([
                    'status' => 'success',
                    'context' => [
                        'type' => 'unknown',
                        'message' => 'Domain unreachable.',
                        'next_step_instruction' => 'The domain does not have valid DNS records. Please check for typos.'
                    ]
                ]);
            }

            // Valid Live Website
            $context = [
                'type' => 'website',
                'requires_repo' => true,
                'message' => 'Live Website detected.',
                'next_step_instruction' => 'Please provide the GitHub Repository URL to verify ownership.'
            ];
        }

        return response()->json([
            'status' => 'success',
            'context' => $context
        ]);
    }
    /**
     * Retry an audit for an asset, optionally with "Deep Wait".
     *
     * TypeScript Response Interface:
     * ```typescript
     * interface RetryAuditResponse {
     *     success: boolean;
     *     asset_id: string;
     *     status: 'processing';
     * }
     * ```
     */
    public function retryAudit(Request $request, VaultAsset $asset, LedgerService $ledgerService): JsonResponse
    {
        // Ensure user owns this asset
        if ($asset->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $options = [];
        if ($request->boolean('deep_wait')) {
            $options['deep_wait'] = true;
            Log::info("Retrying audit for asset {$asset->id} with Deep Wait enabled.");
        } else {
            Log::info("Retrying audit for asset {$asset->id}.");
        }

        // Reset status to processing
        $asset->update([
            'status' => 'processing',
            'metadata' => array_merge($asset->metadata ?? [], [
                'summary' => 'Retrying LUME Audit...' . (isset($options['deep_wait']) ? ' (Deep Scan)' : ''),
                'confidence_score' => 0, // Reset score
            ])
        ]);

        // Dispatch Job with options
        // Use existing audit type or default to 'project' if it has a URL, else 'document'
        $auditType = $asset->metadata['audit_type'] ?? 
                     (!empty($asset->metadata['website_url']) ? 'project' : 'document');

        \App\Jobs\AuditVaultAsset::dispatch($asset, $auditType, $options);

        // Broadcast status update
        event(new \App\Events\AssetStatusUpdated($asset));

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'status' => 'processing',
            'message' => 'Audit retry initiated.',
        ]);
    }

    /**
     * Run a Deep Forensic Audit on an asset.
     * 
     * Cost: 20 Credits
     * 
     * TypeScript Response Interface:
     * ```typescript
     * interface DeepAuditResponse {
     *     success: boolean;
     *     asset_id: string;
     *     radar_data: {
     *         code_resilience: number;
     *         security_perimeter: number;
     *         deployment_maturity: number;
     *         seo_authority: number;
     *         database_architecture: number;
     *     };
     *     message: string;
     * }
     * ```
     */
    public function runDeepAudit(Request $request, LedgerService $ledgerService): JsonResponse
    {
        $request->validate([
            'asset_id' => 'required|exists:vault_assets,id',
            'custom_prompt' => 'nullable|string|max:10000',
            'is_rescan' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $asset = VaultAsset::where('id', $request->input('asset_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Allow re-scanning even if radar_data exists
        // (Block removed to permit QA & Penetration Testing re-runs)

        // === CREDIT DEDUCTION: 20 CREDITS (or 5 for Re-Scan) ===
        // === CREDIT/QUOTA DEDUCTION ===
        $isRescan = $request->boolean('is_rescan');
        
        // Determine audit type for quota usage
        $auditType = $isRescan ? 'rescan' : 'pentest';
        $assetName = $asset->file_name ?? 'Asset';

        // 1. Check Eligibility (Credits OR Quota)
        if (!$ledgerService->hasCreditsForAudit($user, $auditType)) {
             $cost = \App\Services\LedgerService::getAuditFee($auditType);
             return response()->json([
                'success' => false, 
                'error' => "Insufficient credits or quota. Scan requires {$cost} credits or an active subscription."
            ], 402);
        }

        // 2. Lock/Deduct Credits
        // This will automatically use quota if available, or deduct credits
        $ledgerService->lockAuditCredits($user, $auditType, "Deep Forensic Audit: {$assetName}");

        // CRITICAL: Ensure URL is accessible to PerformSecurityScan
        // Check multiple sources for URL
        $websiteUrl = $asset->website_url 
            ?? $asset->original_url 
            ?? $asset->metadata['website_url'] 
            ?? $asset->file_name 
            ?? null;

        if (!$websiteUrl || !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            // Refund if URL is invalid or missing
            $ledgerService->refundAuditCredits($user, $auditType, $assetName, \App\Services\LedgerService::getAuditFee($auditType));
            return response()->json([
                'success' => false,
                'error' => 'No valid website URL found for this asset. Cannot perform security scan.',
            ], 400);
        }

        // Update asset: Set status to processing and ensure URL is in metadata
        $metadata = $asset->metadata ?? [];
        $metadata['website_url'] = $websiteUrl; // CRITICAL: Ensure job can find URL
        
        $asset->update([
            'status' => 'processing',
            'metadata' => $metadata
        ]);

        // Get custom prompt for personalized consultation
        $customPrompt = $request->input('custom_prompt');

        // Dispatch the security audit job
        Log::info("Dispatching PerformSecurityScan for asset {$asset->id} with URL: {$websiteUrl}");
        
        \App\Jobs\PerformSecurityScan::dispatch($asset, $customPrompt, $isRescan, $user->id, $auditType);

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'status' => 'processing',
            'message' => 'Deep Forensic Audit initiated. This may take 2-3 minutes.',
        ]);
    }
    /**
     * Sync Project and Repo assets (Re-Scan).
     */
    public function syncProjectAndRepo(Request $request, LedgerService $ledgerService): JsonResponse
    {
        $validated = $request->validate([
            'asset_id' => 'nullable|uuid',
            'web_asset_id' => 'nullable|uuid',
            'repo_asset_id' => 'nullable|uuid',
            'website_url' => 'nullable|string',
            'github_repo_url' => 'nullable|string',
            'batch_id' => 'nullable|string', // TITAN V8.5: Allow forced batch update
        ]);

        $user = $request->user();
        
        // Resolve Assets
        $webAsset = null;
        $repoAsset = null;

        if (!empty($validated['web_asset_id']) && !empty($validated['repo_asset_id'])) {
            $webAsset = VaultAsset::where('id', $validated['web_asset_id'])->where('user_id', $user->id)->first();
            $repoAsset = VaultAsset::where('id', $validated['repo_asset_id'])->where('user_id', $user->id)->first();
        } elseif (!empty($validated['asset_id'])) {
            $asset = VaultAsset::where('id', $validated['asset_id'])->where('user_id', $user->id)->firstOrFail();
            
            // TITAN V7: Try to find if this is part of a Sync Pair
            $activity = \App\Models\ScanActivity::where('primary_asset_id', $asset->id)
                ->orWhere('secondary_asset_id', $asset->id)
                ->orderBy('scanned_at', 'desc')
                ->first();

            if ($activity && $activity->primary_asset_id && $activity->secondary_asset_id) {
                $webAsset = $activity->primaryAsset;
                $repoAsset = $activity->secondaryAsset;
            } else {
                // Fallback: Single asset scan
                \App\Jobs\PerformSecurityScan::dispatch($asset);
                return response()->json(['success' => true, 'message' => 'Single asset scan initiated.']);
            }
        }

        if ($webAsset && $repoAsset) {
            // --- ENFORCE CREDITS / QUOTA (RE-SCAN) ---
            if (!$ledgerService->hasCreditsForAudit($user, 'rescan')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credits or re-scan quota. Please upgrade your plan or purchase credits.',
                ], 402);
            }

            // Lock credits upfront
            $ledgerService->lockAuditCredits($user, 'rescan', $webAsset->file_name);

            // Find or Create Project Asset Wrapper (Required for Jobs)
            // TITAN V8: Prioritize URLs from request (passed from UI) to fix stub metadata issues
            $webUrl = $validated['website_url'] ?? $webAsset->website_url ?? $webAsset->metadata['target_url'] ?? $webAsset->file_name;
            $repoUrl = $validated['github_repo_url'] ?? $repoAsset->github_repo_url ?? $repoAsset->metadata['github_repo_url'] ?? $repoAsset->file_name;
            
            Log::info("SYNC DEBUG: Resolved URLs for Re-scan", [
                'web' => $webUrl,
                'repo' => $repoUrl,
                'source' => isset($validated['website_url']) ? 'request' : 'metadata'
            ]);

            $project = \App\Models\ProjectAsset::firstOrCreate(
                ['vault_asset_id' => $webAsset->id],
                [
                    'user_id' => $user->id,
                    'name' => $webAsset->file_name ?? 'Sync Project',
                    'website_url' => $webUrl,
                    'github_repo_url' => $repoUrl,
                    'status' => 'pending_verification'
                ]
            );

            // TITAN V7: If listed on Marketplace, set status to 'updating'
            $listing = \App\Models\MarketplaceListing::where('vault_asset_id', $webAsset->id)
                ->orWhere('vault_asset_id', $repoAsset->id)
                ->first();
            
            if ($listing) {
                $listing->update(['status' => 'updating']);
            }

            // TITAN V8.5: Smart Batch Detection (Replicate ProjectController Logic)
            // 1. Check if provided `batch_id` is valid
            $newBatchId = null;
            if (!empty($validated['batch_id'])) {
                 $newBatchId = $validated['batch_id'];
                 Log::info("SYNC: Re-using Batch ID from Request: {$newBatchId}");
            }

            // 2. If no batch_id, try to find an existing pair by checking if these assets are already linked
            if (!$newBatchId) {
                // Check if these exact assets are already linked in a batch
                if ($webAsset->batch_id && $repoAsset->batch_id && $webAsset->batch_id === $repoAsset->batch_id) {
                     $newBatchId = $webAsset->batch_id;
                     Log::info("SYNC: Auto-detected Existing Batch ID from Assets: {$newBatchId}");
                }
            }
            
            // 3. Last Resort: Generate New
            if (!$newBatchId) {
                 $newBatchId = 'SYNC-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
                 Log::info("SYNC: Generating New Batch ID: {$newBatchId}");
            } else {
                 // TITAN V8.5: Archive History if reusing batch (To prevent data loss)
                 // This ensures we save the previous state before overwriting with new scan processing
                 foreach ([$webAsset, $repoAsset] as $asset) {
                    if ($asset) {
                        \App\Models\VaultHistory::create([
                            'vault_asset_id' => $asset->id,
                            'batch_id' => $asset->batch_id,
                            'score' => $asset->score,
                            'status' => $asset->status,
                            'metadata' => $asset->metadata,
                            'scanned_at' => $asset->updated_at ?? now(),
                        ]);
                    }
                }
            }
            
            // Mark assets as processing with (potentially new or existing) batch ID
            $webAsset->update(['status' => 'processing', 'batch_id' => $newBatchId]);
            $repoAsset->update(['status' => 'processing', 'batch_id' => $newBatchId]);

            // Dispatch Sync Scan (Full Re-scan: Web + Repo + Comparison)
            if (class_exists(\App\Jobs\PerformSyncScan::class)) {
                \App\Jobs\PerformSyncScan::dispatch($project, $webAsset->id, $repoAsset->id, null, $newBatchId);
                $message = 'Full Sync re-scan initiated.';
            } elseif (class_exists(\App\Jobs\PerformSyncComparison::class)) {
                // Fallback: Just compare existing data if full scan job missing
                \App\Jobs\PerformSyncComparison::dispatch($project, $webAsset->id, $repoAsset->id, $newBatchId);
                $message = 'Sync Comparison re-run.';
            } else {
                \App\Jobs\PerformSecurityScan::dispatch($webAsset);
                \App\Jobs\PerformSecurityScan::dispatch($repoAsset);
                $message = 'Sync Job not found, triggered parallel scans.';
            }

            return response()->json([
                'success' => true, 
                'status' => 'processing',
                'message' => $message,
                'web_asset_id' => $webAsset->id,
                'repo_asset_id' => $repoAsset->id
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Could not resolve sync pair.'], 400);
    }

    /**
     * Get the audit history for a specific asset.
     */
    /**
     * Get the audit history for a specific asset.
     * TITAN V6.4: Aggregates Sync Triad (Web + Repo + Sync) by batch_id.
     */
    public function getHistory(Request $request, VaultAsset $asset): JsonResponse
    {
        // Ensure user owns this asset or it's a public demo
        if ($asset->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // 1. Identify Related Assets (if this is a sync pair)
        $repoAssetId = $asset->synced_assets['repo'] ?? null;
        $assetsToFetch = [$asset->id];
        if ($repoAssetId) {
            $assetsToFetch[] = $repoAssetId;
        }

        // 2. Fetch Initial History for current asset
        $primaryHistory = \App\Models\VaultHistory::whereIn('vault_asset_id', $assetsToFetch)
            ->orderBy('created_at', 'desc')
            ->get();

        // 2b. TITAN V6.5 FIX: Fetch Siblings (Repo/Web) that share the same Batch ID
        // This ensures the "Triad" view can see the Repository Score even if we are viewing the Website Asset
        $batchIds = $primaryHistory->pluck('batch_id')->filter()->unique()->toArray();
        
        $siblingHistory = collect();
        if (!empty($batchIds)) {
            $siblingHistory = \App\Models\VaultHistory::whereIn('batch_id', $batchIds)
                ->whereNotIn('id', $primaryHistory->pluck('id')) // Avoid duplicates
                ->get();
        }

        // Merge and Sort
        $rawHistory = $primaryHistory->merge($siblingHistory)->sortByDesc('created_at');

        // 3. Group by Batch ID to form "Triads"
        $grouped = [];

        foreach ($rawHistory as $item) {
            $batchId = trim($item->batch_id); // TITAN V6.7: Trim whitespace for strict key matching
            $meta = $item->metadata ?? [];
            $type = $meta['audit_type'] ?? 'unknown';
            $context = $meta['audit_context'] ?? null;

            // Handle Legacy/Null Batch IDs (Treat as individual items)
            if (empty($batchId)) {
                $grouped[] = $this->formatHistoryItem($item);
                continue;
            }

            // Init Batch Group
            if (!isset($grouped[$batchId])) {
                $grouped[$batchId] = [
                    'id' => $item->id, // Use the most recent ID as key
                    'batch_id' => $batchId,
                    'created_at' => $item->created_at, // Will use latest
                    'status' => 'processing', // Default
                    'score' => 0,
                    'is_triad' => true,
                    'entries' => [
                        'sync' => null,
                        'website' => null,
                        'repository' => null
                    ]
                ];
            }

            // TITAN V6.6: Use explicit 'scanned_type' column if available (Robustness)
            $scannedType = $item->scanned_type;
            
            if (!$scannedType) {
                // Fallback to Metadata (Legacy)
                $meta = $item->metadata ?? [];
                $scannedType = $meta['audit_type'] ?? 'unknown';
            }

            // Map Item to Role (Sync/Web/Repo)
            $formatted = $this->formatHistoryItem($item);
            $role = null;

            if ($context === 'sync' || in_array($scannedType, ['sync', 'sync_scan'])) {
                $role = 'sync';
                
                // Master Record sets the top-level status/score for the row
                $grouped[$batchId]['score'] = $item->score;
                $grouped[$batchId]['status'] = $item->status;
                // If the sync record has specific metadata, merge it up to the group
                if (!empty($item->metadata)) {
                    $grouped[$batchId]['metadata'] = array_merge($grouped[$batchId]['metadata'] ?? [], $item->metadata);
                }
            } elseif (in_array($scannedType, ['website', 'website_scan', 'project'])) {
                $role = 'website';
            } elseif (in_array($scannedType, ['repository', 'repository_scan', 'repository'])) {
                $role = 'repository';
            }

            if ($role) {
                $grouped[$batchId]['entries'][$role] = $formatted;
                
                // TITAN V6.8 USER REQUEST: Synthesize Linked Histories Array
                // We add this ID to a list of linked IDs on the GROUP level (which represents the Sync)
                if (!isset($grouped[$batchId]['linked_histories'])) {
                    $grouped[$batchId]['linked_histories'] = [];
                }
                $grouped[$batchId]['linked_histories'][$role] = $item->id;
            }
        }

        // 4. Flatten and Sort
        $finalHistory = array_values($grouped);
        
        // Sort by created_at desc
        usort($finalHistory, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // 5. Final Polish (Downgrade single-item batches to normal rows if they aren't true syncs)
        $polishedHistory = array_map(function ($group) {
            // If it was a legacy item (no breakdown), return as is
            if (!isset($group['is_triad'])) return $group;

            // If it has a Sync entry, it is definitely a Triad.
            // If it has BOTH Web and Repo, it is a Triad.
            // Otherwise, it might just be a batched single scan.
            $hasSync = !empty($group['entries']['sync']);
            $hasWeb = !empty($group['entries']['website']);
            $hasRepo = !empty($group['entries']['repository']);

            if ($hasSync || ($hasWeb && $hasRepo)) {
                return $group;
            }
            
            // If only one entry exists, unwrap it (Individual Scan with a batch_id)
            if ($hasWeb) return $group['entries']['website'];
            if ($hasRepo) return $group['entries']['repository'];
            
            return $group;

        }, $finalHistory);

        // 6. TITAN V6.6: Inject Linked Repo ID for Frontend Stitching
        $repoAssetId = null;
        if ($asset->batch_id) {
            $sibling = \App\Models\VaultAsset::where('batch_id', $asset->batch_id)
                ->where('id', '!=', $asset->id)
                ->where('metadata->audit_type', 'repository_scan') // TITAN V6.7: Explicitly target Repo
                ->first();
            
            if ($sibling) {
                $repoAssetId = $sibling->id;
            }
        }

        return response()->json([
            'asset_id' => $asset->id,
            'synced_assets' => [
                'repo' => $repoAssetId
            ],
            'history' => $polishedHistory
        ]);

    }

    private function formatHistoryItem($item)
    {
        return [
            'id' => $item->id,
            'batch_id' => $item->batch_id,
            'score' => $item->score,
            'individual_score' => $item->individual_score, // TITAN V6.5
            'sync_score' => $item->sync_score,             // TITAN V6.5
            'linked_id' => $item->linked_history_id,       // TITAN V6.5
            'scanned_type' => $item->scanned_type,         // TITAN V6.6
            'status' => $item->status,
            'metadata' => $item->metadata ?? [],
            'created_at' => $item->created_at,
            'scanned_at' => $item->scanned_at,
        ];
    }
}
