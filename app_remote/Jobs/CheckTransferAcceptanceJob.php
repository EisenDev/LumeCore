<?php

namespace App\Jobs;

use App\Models\EscrowTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTransferAcceptanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public EscrowTransaction $escrow
    ) {}

    /**
     * Execute the job.
     * Check if the buyer has accepted the GitHub repository transfer.
     * If accepted, start the 7-day escrow timer.
     */
    public function handle(): void
    {
        $project = $this->escrow->projectAsset;
        
        if (!$project) {
            Log::error('CheckTransferAcceptanceJob: No project found', [
                'escrow_id' => $this->escrow->id,
            ]);
            return;
        }

        // Skip if already accepted or not in transferring state
        if ($this->escrow->buyer_accepted_transfer || $this->escrow->status !== 'transferring') {
            return;
        }

        $buyerUsername = $this->escrow->buyer_github_username;
        $githubToken = $project->getGithubToken();

        if (!$buyerUsername || !$githubToken) {
            $this->escrow->addTransferLog('Missing buyer username or GitHub token', 'error');
            return;
        }

        // Check if transfer has been accepted by looking up the repo under buyer's account
        $transferAccepted = $this->checkRepoTransferAccepted($project, $buyerUsername, $githubToken);

        if ($transferAccepted) {
            $this->onTransferAccepted();
        } else {
            // Re-schedule check for later
            $this->escrow->addTransferLog('Transfer pending buyer acceptance...', 'info');
            
            // Dispatch another check in 30 minutes
            self::dispatch($this->escrow)
                ->delay(now()->addMinutes(30));
        }
    }

    /**
     * Check if the repository transfer has been accepted.
     */
    protected function checkRepoTransferAccepted($project, string $buyerUsername, string $token): bool
    {
        try {
            // Parse original repo URL
            preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $project->github_repo_url, $matches);
            if (count($matches) < 3) {
                return false;
            }

            $repoName = rtrim($matches[2], '.git');

            // Check if repo now exists under buyer's account
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LUME-Platform',
                ])
                ->withToken($token)
                ->get("https://api.github.com/repos/{$buyerUsername}/{$repoName}");

            if ($response->successful()) {
                $data = $response->json();
                // Verify the owner is indeed the buyer
                return ($data['owner']['login'] ?? '') === $buyerUsername;
            }

            return false;

        } catch (\Exception $e) {
            Log::warning('CheckTransferAcceptanceJob: Failed to check repo', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle successful transfer acceptance.
     * Start the 7-day escrow timer.
     */
    protected function onTransferAccepted(): void
    {
        $now = now();
        $releaseAt = $now->copy()->addDays(7);

        $this->escrow->update([
            'buyer_accepted_transfer' => true,
            'transfer_accepted_at' => $now,
            'status' => 'locked',
            'locked_at' => $now,
            'release_at' => $releaseAt, // 7 days from NOW (not from transfer initiation)
        ]);

        $this->escrow->addTransferLog('✓ Buyer accepted repository transfer!', 'success');
        $this->escrow->addTransferLog("7-day escrow period started. Funds will be released on {$releaseAt->format('M d, Y')}", 'info');

        // Update project status
        $this->escrow->projectAsset->update([
            'status' => 'transferred',
        ]);

        Log::info('CheckTransferAcceptanceJob: Transfer accepted, 7-day timer started', [
            'escrow_id' => $this->escrow->id,
            'release_at' => $releaseAt->toIso8601String(),
        ]);

        // TODO: Send notification emails to buyer and seller
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckTransferAcceptanceJob failed', [
            'escrow_id' => $this->escrow->id,
            'error' => $exception->getMessage(),
        ]);

        $this->escrow->addTransferLog('Failed to verify transfer acceptance', 'error');
    }
}
