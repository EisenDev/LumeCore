<?php

namespace App\Jobs;

use App\Jobs\CheckTransferAcceptanceJob;
use App\Models\EscrowTransaction;
use App\Models\ProjectAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransferProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public EscrowTransaction $escrow
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $project = $this->escrow->projectAsset;
        $buyerUsername = $this->escrow->buyer_github_username;

        if (!$project || !$buyerUsername) {
            $this->escrow->addTransferLog('Missing project or buyer GitHub username', 'error');
            $this->fail('Missing required data for transfer');
            return;
        }

        $this->escrow->update([
            'status' => 'transferring',
            'transfer_started_at' => now(),
        ]);

        $this->escrow->addTransferLog('Starting repository transfer process...', 'info');

        try {
            // Step 1: Verify buyer GitHub account exists
            $this->escrow->addTransferLog("Verifying buyer account: @{$buyerUsername}...", 'info');
            
            if (!$this->verifyGitHubUser($buyerUsername)) {
                throw new \Exception("Buyer GitHub account @{$buyerUsername} not found");
            }
            
            $this->escrow->addTransferLog("Buyer account verified ✓", 'success');

            // Step 2: Initiate repository transfer
            $githubToken = $project->getGithubToken();
            if (!$githubToken) {
                throw new \Exception('GitHub token not available for transfer');
            }

            $this->escrow->addTransferLog('Initiating GitHub repository transfer...', 'info');
            
            $transferResult = $this->initiateRepoTransfer($project, $buyerUsername, $githubToken);
            
            if (!$transferResult['success']) {
                throw new \Exception($transferResult['error'] ?? 'Transfer initiation failed');
            }

            $this->escrow->addTransferLog('Repository transfer initiated ✓', 'success');
            $this->escrow->addTransferLog('Buyer will receive a transfer invitation email from GitHub', 'info');
            $this->escrow->addTransferLog('Waiting for buyer to accept transfer...', 'info');

            // Step 3: Update escrow - but DON'T lock yet (wait for buyer acceptance)
            // The 7-day timer starts ONLY after buyer accepts the transfer
            $this->escrow->update([
                'escrow_type' => 'asset_transfer',
                'transfer_completed_at' => now(),
                // status stays 'transferring' until buyer accepts
            ]);

            // Step 4: Dispatch job to check for buyer acceptance
            // This job will poll GitHub API and start the 7-day timer once accepted
            CheckTransferAcceptanceJob::dispatch($this->escrow)
                ->delay(now()->addMinutes(5)); // First check in 5 minutes

            $this->escrow->addTransferLog('Transfer initiated! 7-day escrow will start when buyer accepts.', 'info');

            Log::info("TransferProjectJob: Transfer initiated for project {$project->id} to @{$buyerUsername}. Waiting for acceptance.");

        } catch (\Exception $e) {
            $this->escrow->addTransferLog("Transfer failed: {$e->getMessage()}", 'error');
            
            Log::error("TransferProjectJob failed", [
                'escrow_id' => $this->escrow->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            // Don't mark as failed yet - this may be retried
            if ($this->attempts() >= $this->tries) {
                $this->escrow->update(['status' => 'disputed']);
                $this->escrow->addTransferLog('Max retry attempts reached. Marked for manual review.', 'error');
            }

            throw $e;
        }
    }

    /**
     * Verify that a GitHub user exists.
     */
    private function verifyGitHubUser(string $username): bool
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'LUME-Platform',
            ])
            ->get("https://api.github.com/users/{$username}");

        return $response->successful();
    }

    /**
     * Initiate a GitHub repository transfer.
     */
    private function initiateRepoTransfer(ProjectAsset $project, string $newOwner, string $token): array
    {
        try {
            // Parse repo URL
            preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $project->github_repo_url, $matches);
            
            if (count($matches) < 3) {
                return ['success' => false, 'error' => 'Invalid GitHub repository URL'];
            }

            $currentOwner = $matches[1];
            $repoName = rtrim($matches[2], '.git');

            // GitHub API: Transfer a repository
            // POST /repos/{owner}/{repo}/transfer
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LUME-Platform',
                ])
                ->withToken($token)
                ->post("https://api.github.com/repos/{$currentOwner}/{$repoName}/transfer", [
                    'new_owner' => $newOwner,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Transfer invitation sent',
                    'data' => $response->json(),
                ];
            }

            $error = $response->json()['message'] ?? 'Unknown error';
            
            // Handle specific errors
            if ($response->status() === 403) {
                return [
                    'success' => false,
                    'error' => 'Insufficient permissions. Token needs admin access to the repository.',
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'error' => 'Repository not found or token lacks access.',
                ];
            }

            return [
                'success' => false,
                'error' => "GitHub API error: {$error}",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("TransferProjectJob failed permanently", [
            'escrow_id' => $this->escrow->id,
            'error' => $exception->getMessage(),
        ]);

        $this->escrow->update(['status' => 'disputed']);
        $this->escrow->addTransferLog('Transfer job failed permanently. Escalated for manual review.', 'error');
    }
}
