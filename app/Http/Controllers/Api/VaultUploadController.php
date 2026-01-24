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
            'custom_prompt' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $asset = VaultAsset::where('id', $request->input('asset_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Check if deep audit already exists
        if (!empty($asset->radar_data)) {
            return response()->json([
                'success' => true,
                'asset_id' => $asset->id,
                'radar_data' => $asset->radar_data,
                'message' => 'Deep audit already completed.',
            ]);
        }

        // === CREDIT DEDUCTION: 20 CREDITS ===
        $deepAuditCost = 20.00;
        
        // Get credits from wallet (primary) or user (fallback)
        $availableCredits = $user->wallet?->credits ?? $user->credits ?? 0;
        
        if ($availableCredits < $deepAuditCost) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient credits. Deep Audit requires 20 credits.',
            ], 402);
        }

        // Deduct credits
        $ledgerService->deductCredits($user, $deepAuditCost, 'deep_audit', "Deep Forensic Audit: {$asset->file_name}");

        // Get the website URL from asset metadata
        $websiteUrl = $asset->metadata['website_url'] ?? $asset->file_name;

        if (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            // Refund if URL is invalid
            $ledgerService->refundAuditCredits($user, 'deep_audit', $asset->file_name, $deepAuditCost);
            return response()->json([
                'success' => false,
                'error' => 'Invalid website URL for deep audit.',
            ], 400);
        }

        // Get custom prompt for personalized consultation
        $customPrompt = $request->input('custom_prompt');

        // Dispatch the deep audit job with custom prompt
        \App\Jobs\PerformDeepAudit::dispatch($asset, $websiteUrl, $customPrompt);

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'status' => 'processing',
            'message' => 'Deep Forensic Audit initiated. This may take 2-3 minutes.',
        ]);
    }
}
