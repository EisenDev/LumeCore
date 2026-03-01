<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GithubFlattenerService
{
    protected array $whitelistExtensions = ['php', 'vue', 'ts', 'js', 'json', 'yml', 'yaml', 'md'];
    protected array $blacklistPaths = [
        'node_modules', 
        'vendor', 
        'storage', 
        'public/build', 
        '.git', 
        'dist', 
        'tests', 
        'database/seeders' // often huge or irrelevant
    ];

    /**
     * Flatten a GitHub repository into a single string context.
     * 
     * @param string $repoUrl
     * @param string|null $token
     * @return string
     */
    public function flattenRepo(string $repoUrl, ?string $token = null, ?callable $onProgress = null): string
    {
        // 1. Parse Repo
        $repoInfo = $this->parseRepoUrl($repoUrl);
        if (!$repoInfo) {
            return "ERROR: Invalid GitHub URL.";
        }
        $owner = $repoInfo['owner'];
        $repo = $repoInfo['repo'];

        // 2. Resolve Token
        $authToken = $token ?? config('services.github.system_token') ?? env('GITHUB_SYSTEM_TOKEN');
        
        try {
            // 3. Get Default Branch
            if ($onProgress) $onProgress("Git: Fetching default branch...", 10);
            $defaultBranch = $this->getDefaultBranch($owner, $repo, $authToken);
            
            // 4. Get Recursive Tree
            if ($onProgress) $onProgress("Git: Fetching file structure...", 15);
            $tree = $this->getRecursiveTree($owner, $repo, $defaultBranch, $authToken);
            
            // 5. Filter & Fetch Files
            $flattenedContent = "=== GITHUB REPOSITORY FLATTENED CONTEXT ===\n";
            $flattenedContent .= "REPO: {$owner}/{$repo} (Branch: {$defaultBranch})\n";
            $flattenedContent .= "TIMESTAMP: " . now()->toIso8601String() . "\n\n";

            $fileCount = 0;
            $maxFiles = 50; // Safety limit for 1GB RAM env
            $totalSize = 0;
            $maxTotalSize = 2 * 1024 * 1024; // 2MB limit for AI context

            if ($onProgress) $onProgress("Git: Found " . count($tree) . " files. Filtering...", 20);

            foreach ($tree as $index => $node) {
                if ($fileCount >= $maxFiles) break;
                if ($totalSize >= $maxTotalSize) break;
                
                if ($node['type'] !== 'blob') continue;
                
                if ($this->shouldInclude($node['path'])) {
                    // Check file size from tree metadata before fetching (Skip > 500KB)
                    if (isset($node['size']) && $node['size'] > 500 * 1024) {
                         Log::info("Skipping large file: {$node['path']} (" . round($node['size'] / 1024) . "KB)");
                         continue;
                    }

                    // Check if adding this file would exceed limits
                    if (isset($node['size']) && ($totalSize + $node['size']) > $maxTotalSize) {
                        Log::info("Context size limit reached. Stopping at: {$node['path']}");
                        break;
                    }

                    if ($onProgress) $onProgress("Downloading: {$node['path']}", 20 + min(50, $fileCount));
                    $content = $this->fetchFileContent($owner, $repo, $node['path'], $authToken);
                    if ($content) {
                        $flattenedContent .= "--- FILE: {$node['path']} ---\n";
                        $flattenedContent .= $content . "\n\n";
                        
                        $fileCount++;
                        $totalSize += strlen($content);
                    }
                }
            }

            return $flattenedContent;

        } catch (\Exception $e) {
            Log::error("GithubFlattenerService Error: " . $e->getMessage());
            return "ERROR: Failed to flatten repository. " . $e->getMessage();
        }
    }

    /**
     * Flatten a LOCAL git repository (after cloning).
     * Much faster and more reliable for large repos.
     * 
     * @param string $localPath
     * @param callable|null $onProgress
     * @return string
     */
    public function flattenLocalRepo(string $localPath, ?callable $onProgress = null): string
    {
        try {
            if ($onProgress) $onProgress("Local: Scanning files...", 5);
            
            $files = \Illuminate\Support\Facades\File::allFiles($localPath);
            
            $flattenedContent = "=== GITHUB REPOSITORY FLATTENED CONTEXT (LOCAL SCAN) ===\n";
            $flattenedContent .= "TIMESTAMP: " . now()->toIso8601String() . "\n\n";

            $fileCount = 0;
            $maxFiles = 50; 
            $totalSize = 0;
            $maxTotalSize = 2.5 * 1024 * 1024; // 2.5MB limit (Local can handle a bit more)

            if ($onProgress) $onProgress("Local: Found " . count($files) . " files. Filtering...", 10);

            foreach ($files as $file) {
                if ($fileCount >= $maxFiles) break;
                if ($totalSize >= $maxTotalSize) {
                    Log::info("Context size limit reached (Local).");
                    break;
                }

                $relativePath = Str::after($file->getPathname(), $localPath . DIRECTORY_SEPARATOR);
                // Normalize slashes
                $relativePath = str_replace('\\', '/', $relativePath);

                if ($this->shouldInclude($relativePath)) {
                    // Check size (skip > 500KB)
                    $size = $file->getSize();
                    if ($size > 500 * 1024) continue;

                    // Smoother Progress: 10% to 90% mapped across file count
                    $totalFiles = max(1, count($files));
                    $relativePercent = 10 + (($fileCount / $totalFiles) * 80);
                    
                    if ($onProgress) $onProgress("Reading: {$relativePath}", (int) $relativePercent);

                    $content = $file->getContents();
                    
                    // Truncate if huge
                    if (strlen($content) > 30000) {
                        $content = substr($content, 0, 30000) . "\n[...TRUNCATED...]";
                    }

                    $flattenedContent .= "--- FILE: {$relativePath} ---\n";
                    $flattenedContent .= $content . "\n\n";
                    
                    $fileCount++;
                    $totalSize += strlen($content);
                }
            }

            return $flattenedContent;

        } catch (\Exception $e) {
            Log::error("GithubFlattenerService Local Error: " . $e->getMessage());
            return "ERROR: Failed to flatten local repo. " . $e->getMessage();
        }
    }

    protected function parseRepoUrl(string $url): ?array
    {
        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $url, $matches);
        if (count($matches) < 3) return null;
        
        $repo = $matches[2];
        // FIX: rtrim removes characters from the mask, potentially corrupting repo names ending in g, i, or t.
        // Use regex to remove ONLY the .git suffix.
        $repo = preg_replace('/\.git$/', '', $repo);

        return ['owner' => $matches[1], 'repo' => $repo];
    }

    protected function getDefaultBranch(string $owner, string $repo, ?string $token): string
    {
        $url = "https://api.github.com/repos/{$owner}/{$repo}";
        Log::info("GithubFlattener: Fetching Default Branch for [{$url}]");

        $response = Http::retry(3, 200)->timeout(30)->withHeaders([
            'User-Agent' => 'LUME-Core-Auditor',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ])->get($url);

        if (!$response->successful()) {
            throw new \Exception("Could not fetch repo info: " . $response->status());
        }

        return $response->json('default_branch') ?? 'main';
    }

    protected function getRecursiveTree(string $owner, string $repo, string $branch, ?string $token): array
    {
        $response = Http::retry(3, 300)->timeout(60)->withHeaders([
            'User-Agent' => 'LUME-Core-Auditor',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ])->get("https://api.github.com/repos/{$owner}/{$repo}/git/trees/{$branch}?recursive=1");

        if (!$response->successful()) {
            throw new \Exception("Could not fetch repo tree: " . $response->status());
        }

        return $response->json('tree') ?? [];
    }

    protected function shouldInclude(string $path): bool
    {
        // Check Blacklist
        foreach ($this->blacklistPaths as $block) {
            if (Str::startsWith($path, $block) || Str::contains($path, "/{$block}/")) {
                return false;
            }
        }

        // Check Whitelist Extension
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array($ext, $this->whitelistExtensions);
    }

    protected function fetchFileContent(string $owner, string $repo, string $path, ?string $token): ?string
    {
        try {
            $response = Http::retry(3, 100)->timeout(30)
                ->withHeaders([
                    'User-Agent' => 'LUME-Core-Auditor',
                    'Authorization' => $token ? "Bearer {$token}" : null,
                    'Accept' => 'application/vnd.github.v3.raw' // RAW content
                ])->get("https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}");

            if ($response->successful()) {
                // Truncate large files
                return Str::limit($response->body(), 20000, "\n[...TRUNCATED PREVENT OOM...]");
            }
        } catch (\Exception $e) {
            Log::warning("Failed to fetch file {$path}: " . $e->getMessage());
        }
        return null;
    }
}
