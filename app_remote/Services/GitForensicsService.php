<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process as SymfonyProcess;

class GitForensicsService
{
    protected string $tempPath;

    public function __construct()
    {
        $this->tempPath = storage_path('app/temp/repos');
        if (!File::exists($this->tempPath)) {
            File::makeDirectory($this->tempPath, 0777, true);
        } else {
             // Ensure it's writable if it already exists
             @chmod($this->tempPath, 0777);
        }
    }

    /**
     * Clones a repository to a temporary directory.
     * 
     * @param string $repoUrl
     * @param string|null $token
     * @return string The absolute path to the cloned repository
     */
    public function clone(string $repoUrl, ?string $token = null): string
    {
        $uuid = Str::uuid()->toString();
        $targetPath = "{$this->tempPath}/{$uuid}";

        // Inject token into URL for authentication
        $authUrl = $repoUrl;
        if ($token) {
            $authUrl = str_replace('https://', "https://oauth2:{$token}@", $repoUrl);
        }

        // Clone with depth 1000 to get enough history for churn analysis (shallow clone is bad for churn)
        // actually full clone is safer for accurate stats, but maybe depth 5000 is enough? 
        // Let's do a full clone for accuracy, usually fine for average repos.
        $command = "git clone {$authUrl} {$targetPath}";

        Log::info("[GitForensics] Cloning to {$targetPath}...");
        
        $result = Process::timeout(300)->run($command);

        if ($result->failed()) {
            throw new \Exception("Git Clone Failed: " . $result->errorOutput());
        }

        return $targetPath;
    }

    /**
     * cleanup: Remove the temporary repository
     */
    public function cleanup(string $path): void
    {
        if (File::exists($path)) {
            Log::info("[GitForensics] Cleaning up {$path}...");
            File::deleteDirectory($path);
        }
    }

    /**
     * Get Churn Metrics (Toxicity)
     * High Churn + Large File = Toxic
     */
    public function getChurnMetrics(string $path): array
    {
        // Get numstat (added/deleted lines per file) from git log
        // git log --pretty=format: --name-only --since="1 year ago" // simplified for count
        // Better: git log --numstat --no-merges
        
        $process = new SymfonyProcess(['git', 'log', '--numstat', '--no-merges', '--since=1.year.ago'], $path);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning("[GitForensics] Churn Analysis Failed: " . $process->getErrorOutput());
            return [];
        }

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $fileStats = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Output format: <added> <deleted> <filename>
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 3) continue;

            $added = is_numeric($parts[0]) ? (int)$parts[0] : 0;
            $deleted = is_numeric($parts[1]) ? (int)$parts[1] : 0;
            $filename = $parts[2];

            if (!isset($fileStats[$filename])) {
                $fileStats[$filename] = ['churn' => 0, 'commits' => 0];
            }
            
            // Simple Churn Score: Sum of changes + commit frequency
            $fileStats[$filename]['churn'] += ($added + $deleted);
            $fileStats[$filename]['commits']++;
        }

        // Identify Top Toxic Candidates (Top 50 by churn)
        uasort($fileStats, fn($a, $b) => $b['churn'] <=> $a['churn']);
        
        return array_slice($fileStats, 0, 50, true);
    }

    /**
     * Get Bus Factor Stats (Author distribution)
     */
    public function getBusFactorStats(string $path): array
    {
        // git shortlog -sn --no-merges
        $process = new SymfonyProcess(['git', 'shortlog', '-sn', '--no-merges', '--all'], $path);
        $process->run();

        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $authors = [];
        $totalCommits = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Format: 123  Author Name
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
                $count = (int)$matches[1];
                $name = trim($matches[2]);
                
                $authors[] = ['name' => $name, 'commits' => $count];
                $totalCommits += $count;
            }
        }

        // Calculate Percentages
        foreach ($authors as &$author) {
            $author['percent'] = $totalCommits > 0 ? round(($author['commits'] / $totalCommits) * 100, 1) : 0;
        }

        return [
            'total_commits' => $totalCommits,
            'authors' => array_slice($authors, 0, 10), // Top 10
            'bus_factor_score' => $this->calculateBusRisk($authors, $totalCommits)
        ];
    }

    private function calculateBusRisk(array $authors, int $total): int
    {
        if (empty($authors)) return 0;
        // If top author has > 50% commits, Risk is High (Low Bus Factor)
        if ($authors[0]['percent'] > 60) return 1; // Critical
        if ($authors[0]['percent'] > 40) return 3; // Moderate
        return 5; // Healthy
    }

    /**
     * Get Pulse Data (Commit frequency per week for last year)
     */
    public function getPulseData(string $path): array
    {
        // git log --date=format:'%Y-%W' --pretty=format:'%ad' --since="1 year ago"
        $process = new SymfonyProcess(['git', 'log', "--date=format:%Y-%W", "--pretty=format:%ad", '--since=1.year.ago'], $path);
        $process->run();
        
        $output = $process->getOutput();
        $lines = explode("\n", $output);
        $weeks = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            // Format: 2024-05 (Year-Week)
            if (!isset($weeks[$line])) $weeks[$line] = 0;
            $weeks[$line]++;
        }

        // Fill missing weeks? For now just return raw data, frontend can fill.
        // Actually, let's sort roughly
        ksort($weeks);

        // Convert to array of [week, count]
        $data = [];
        foreach ($weeks as $week => $count) {
            $data[] = ['week' => $week, 'commits' => $count];
        }

        return $data;
    }
    /**
     * Generate Sovereign Fingerprint Hash.
     * Combines Git Commit Hash (History) + Dependency/File Structure (Composition).
     * 
     * @param string $path Path to repo
     * @return string SHA256 Hash
     */
    public function getFingerprint(string $path): string
    {
        // 1. Get Latest Commit Hash
        $process = new SymfonyProcess(['git', 'rev-parse', 'HEAD'], $path);
        $process->run();
        $commitHash = trim($process->getOutput());
        
        if (empty($commitHash)) {
            $commitHash = 'unknown_' . Str::random(10);
        }

        // 2. Get Composition Hash (Dependencies or File Count)
        $compositionData = '';
        
        if (File::exists("{$path}/package.json")) {
            $compositionData = File::get("{$path}/package.json");
        } elseif (File::exists("{$path}/composer.json")) {
             $compositionData = File::get("{$path}/composer.json");
        } elseif (File::exists("{$path}/requirements.txt")) {
             $compositionData = File::get("{$path}/requirements.txt");
        } else {
             // Fallback: File Count + Top Level Structure
             $files = File::allFiles($path);
             $compositionData = count($files) . '_files';
        }

        // 3. Combine and Hash
        // We hash the composition to keep the fingerprint string short
        $compositionHash = hash('sha256', $compositionData);
        
        // Final Fingerprint: sha256(commit + composition)
        // This ensures identical code at identical state = identical hash
        return hash('sha256', $commitHash . $compositionHash);
    }
}
