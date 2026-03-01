<?php

namespace App\Jobs;

use App\Models\VaultAsset;
use App\Services\VaultAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AuditVaultAsset Job
 * 
 * Dispatched when a file/project is uploaded and needs AI auditing.
 * Uses VaultAuditService for branching logic:
 * - 'document': PDFs, images, text - Skip domain/code checks
 * - 'project': Websites, codebases - Run InfrastructureScannerService
 * 
 * Credits: 1 for documents, 5 for projects
 * On failure: Credits are automatically refunded
 * On completion: Broadcasts AssetStatusUpdated via Reverb
 */
class AuditVaultAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     * Increased to 300s to accomodate Deep Crawls (up to 70s) + Gemini Analysis.
     */
    public int $timeout = 300;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Helpful for 503 Gemini API overloads.
     */
    public function backoff(): array
    {
        // Wait 1 minute, 2 minutes, then 3 minutes to clear the RPM limit (5 req/min)
        return [60, 120, 180];
    }

    /**
     * Get the middleware the job should pass through.
     * 
     * Uses 'WithoutOverlapping' on a GLOBAL key 'browsershot_mutex' to force
     * sequential execution of audits on this server. This prevents parallel
     * Browsershot instances from crashing the system or conflicting on ports.
     */
    public function middleware(): array
    {
        // Release lock after 10 minutes (twice the timeout) if job crashes hard
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('browsershot_mutex'))->releaseAfter(600)];
    }

    /**
     * The asset to audit.
     */
    public VaultAsset $asset;

    /**
     * The audit type: 'document' or 'project'.
     * Default is 'document' for backward compatibility with old serialized jobs.
     */
    public string $auditType = 'document';

    /**
     * Additional options for the audit (e.g., deep_wait).
     */
    public array $options = [];

    /**
     * Create a new job instance.
     */
    public function __construct(VaultAsset $asset, string $auditType = 'document', array $options = [])
    {
        $this->asset = $asset;
        $this->auditType = $auditType;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(VaultAuditService $auditService): void
    {
        Log::info("AuditVaultAsset Job: Starting {$this->auditType} audit for asset {$this->asset->id}");

        try {
            // Delegate to VaultAuditService for all branching logic
            // This handles:
            // - Document audits (skip domain/code checks, analyze content)
            // - Project audits (run InfrastructureScannerService, analyze tech stack)
            // - Broadcasting via Reverb on completion
            // - Automatic credit refund on failure
            $auditService->runAudit($this->asset, $this->auditType, $this->options);

            Log::info("AuditVaultAsset Job: Completed successfully for asset {$this->asset->id}");

        } catch (\Throwable $e) {
            Log::error("AuditVaultAsset Job: Failed for asset {$this->asset->id}", [
                'error' => $e->getMessage(),
                'audit_type' => $this->auditType,
            ]);

            // The VaultAuditService handles refunds and status updates
            // Re-throw to allow job retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AuditVaultAsset Job: Permanently failed for asset {$this->asset->id}", [
            'error' => $exception->getMessage(),
            'audit_type' => $this->auditType,
        ]);

        // Ensure asset is marked as flagged if all retries exhausted
        $this->asset->update([
            'status' => 'flagged',
            'metadata' => array_merge($this->asset->metadata ?? [], [
                'summary' => 'Audit failed after multiple attempts.',
                'audit_type' => $this->auditType,
                'error' => 'Maximum retry attempts reached.',
            ]),
        ]);
    }
}
