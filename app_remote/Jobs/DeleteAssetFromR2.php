<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteAssetFromR2 implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Storage::disk('r2')->delete($this->filePath);

            Log::info('Successfully deleted asset from R2', [
                'file_path' => $this->filePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete asset from R2', [
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
