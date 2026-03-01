<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Models\ProjectAsset;
use App\Jobs\PerformProjectScan;
use App\Jobs\PerformGithubRepositoryScan;
use App\Jobs\PerformSyncComparison;

class PerformSyncScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ProjectAsset $project,
        public string $webAssetId,
        public string $repoAssetId,
        public ?string $githubToken = null,
        public ?string $injectedBatchId = null // TITAN V2: Allow Controller to dictate Batch ID
    ) {}

    public function handle()
    {
        Log::info("PerformSyncScan: Starting Sync Batch", [
            'project_id' => $this->project->id,
            'web' => $this->webAssetId,
            'repo' => $this->repoAssetId,
            'injected_batch' => $this->injectedBatchId
        ]);

        $projectId = $this->project->id;
        $webId = $this->webAssetId;
        $repoId = $this->repoAssetId;

        // TITAN V2: Context Isolation
        // Use injected ID or Generate unique Batch ID for this sync session
        $batchId = $this->injectedBatchId ?? ('SYNC-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid()), 0, 4)));

        Log::info("PerformSyncScan: Generated Batch ID", ['batch_id' => $batchId]);
        
        // TITAN V2: Save Batch ID to assets immediately to ensure isolation persistence
        \App\Models\VaultAsset::where('id', $this->webAssetId)->update(['batch_id' => $batchId]);
        \App\Models\VaultAsset::where('id', $this->repoAssetId)->update(['batch_id' => $batchId]);

        Bus::chain([
            // 1. Repo Scan (Generates Context)
            // We scan the code first to establish the "Truth" before checking the live site
            new PerformGithubRepositoryScan(
                $this->project, 
                $this->githubToken, 
                $this->repoAssetId, 
                false, 
                'sync',
                syncBatchId: $batchId // Pass Batch ID
            ),

            // 2. Web Scan (Consumes Context)
            // We pass the repoAssetId so the Web Auditor can read the code analysis results
            new PerformProjectScan(
                $this->project, 
                $this->githubToken, 
                $this->webAssetId, 
                $this->repoAssetId, // Context Injection
                syncBatchId: $batchId // Pass Batch ID
            ),

            // 3. Comparison (Finalizes)
            // Compares the results of both previous scans
            new PerformSyncComparison(
                $this->project,
                $this->webAssetId,
                $this->repoAssetId,
                $batchId // Pass Batch ID
            )
        ])->dispatch();
    }
}
