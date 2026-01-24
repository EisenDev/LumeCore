<?php

namespace App\Jobs;

use App\Models\VaultAsset;
use App\Services\AI\DeepAuditService;
use App\Services\AI\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PerformDeepAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public VaultAsset $asset,
        public string $websiteUrl,
        public ?string $customPrompt = null
    ) {}

    public function handle(DeepAuditService $deepAuditService, EmbeddingService $embeddingService): void
    {
        Log::info("PerformDeepAudit: Starting deep audit for asset {$this->asset->id}", [
            'custom_prompt' => $this->customPrompt ? 'provided' : 'none'
        ]);

        try {
            // === PREMIUM STEP 1: Initializing ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Initializing Deep Forensic Scan...',
                5,
                'step_init'
            ));

            // === PREMIUM STEP 2: 10-Page Recursive Crawl ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Performing 10-Page Recursive Crawl',
                15,
                'step_crawl'
            ));

            // Run the deep audit with optional custom prompt
            $result = $deepAuditService->runDeepAudit($this->asset, $this->websiteUrl, $this->customPrompt);

            // === PREMIUM STEP 3: Security Header Audit ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Auditing Security Header Compliance',
                40,
                'step_security'
            ));

            // === PREMIUM STEP 4: Component Dependencies ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Mapping Component Inter-dependencies',
                55,
                'step_dependencies'
            ));

            // Update asset with radar_data
            $radarData = $result['radar_data'] ?? $result['breakdown'] ?? [
                'code_resilience' => 0,
                'security_perimeter' => 0,
                'deployment_maturity' => 0,
                'seo_authority' => 0,
                'database_architecture' => 0
            ];

            // Get markdown report if available
            $fullReport = $result['markdown_report'] ?? json_encode($result);

            $this->asset->update([
                'radar_data' => $radarData,
                'full_audit_report' => $fullReport,
            ]);

            // === PREMIUM STEP 5: Technical Remediation Roadmap ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Generating Technical Remediation Roadmap',
                70,
                'step_roadmap'
            ));

            // === PREMIUM STEP 6: Generating Embeddings ===
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Generating AI Embeddings for RAG...',
                85,
                'step_embeddings'
            ));

            // Generate embeddings for RAG
            $this->generateEmbeddings($embeddingService, $result);

            // Broadcast completion
            event(new \App\Events\AuditProgressUpdated(
                $this->asset,
                'Deep Forensic Audit Complete',
                100
            ));

            // Broadcast status update
            event(new \App\Events\AssetStatusUpdated($this->asset->fresh()));

            Log::info("PerformDeepAudit: Completed deep audit for asset {$this->asset->id}");

        } catch (\Throwable $e) {
            Log::error("PerformDeepAudit: Failed for asset {$this->asset->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Broadcast failure
            event(new \App\Events\AuditFailed($this->asset, 'Deep Audit Failed: ' . $e->getMessage()));

            throw $e;
        }
    }

    /**
     * Generate embeddings for the deep audit result.
     */
    protected function generateEmbeddings(EmbeddingService $embeddingService, array $result): void
    {
        try {
            // Build content to embed
            $contentParts = [];
            
            if (!empty($result['summary'])) {
                $contentParts[] = "SUMMARY: " . $result['summary'];
            }
            
            if (!empty($result['insights'])) {
                $contentParts[] = "INSIGHTS:\n" . implode("\n", $result['insights']);
            }
            
            if (!empty($result['recommendations'])) {
                $contentParts[] = "RECOMMENDATIONS:\n" . implode("\n", $result['recommendations']);
            }
            
            if (!empty($result['warning_flags'])) {
                $contentParts[] = "WARNINGS:\n" . implode("\n", $result['warning_flags']);
            }
            
            $fullReportJson = json_encode($result, JSON_PRETTY_PRINT);
            $contentParts[] = "FULL REPORT:\n" . $fullReportJson;
            
            $fullContent = implode("\n\n", $contentParts);
            
            // Chunk and embed
            $chunks = $embeddingService->chunkText($fullContent, 1500, 150);
            $embeddedChunks = $embeddingService->embedChunks($chunks);
            
            // Clear existing embeddings
            \App\Models\AssetEmbedding::where('asset_id', $this->asset->id)->delete();
            
            // Save new embeddings
            foreach ($embeddedChunks as $item) {
                \App\Models\AssetEmbedding::create([
                    'asset_id' => $this->asset->id,
                    'content' => $item['content'],
                    'embedding' => $item['embedding'],
                ]);
            }
            
            Log::info("PerformDeepAudit: Generated " . count($embeddedChunks) . " embeddings");
            
        } catch (\Throwable $e) {
            Log::error("PerformDeepAudit: Embedding generation failed", ['error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PerformDeepAudit: Job failed for asset {$this->asset->id}", [
            'error' => $exception->getMessage()
        ]);
        
        event(new \App\Events\AuditFailed($this->asset, 'Deep Audit System Failure'));
    }
}
