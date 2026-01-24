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
    public function flattenRepo(string $repoUrl, ?string $token = null): string
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
            $defaultBranch = $this->getDefaultBranch($owner, $repo, $authToken);
            
            // 4. Get Recursive Tree
            $tree = $this->getRecursiveTree($owner, $repo, $defaultBranch, $authToken);
            
            // 5. Filter & Fetch Files
            $flattenedContent = "=== GITHUB REPOSITORY FLATTENED CONTEXT ===\n";
            $flattenedContent .= "REPO: {$owner}/{$repo} (Branch: {$defaultBranch})\n";
            $flattenedContent .= "TIMESTAMP: " . now()->toIso8601String() . "\n\n";

            $fileCount = 0;
            $maxFiles = 50; // Safety limit for 1GB RAM env
            $totalSize = 0;
            $maxTotalSize = 2 * 1024 * 1024; // 2MB limit for AI context

            foreach ($tree as $node) {
                if ($fileCount >= $maxFiles) break;
                if ($totalSize >= $maxTotalSize) break;
                
                if ($node['type'] !== 'blob') continue;
                
                if ($this->shouldInclude($node['path'])) {
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

    protected function parseRepoUrl(string $url): ?array
    {
        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $url, $matches);
        if (count($matches) < 3) return null;
        return ['owner' => $matches[1], 'repo' => rtrim($matches[2], '.git')];
    }

    protected function getDefaultBranch(string $owner, string $repo, ?string $token): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'LUME-Core-Auditor',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ])->get("https://api.github.com/repos/{$owner}/{$repo}");

        if (!$response->successful()) {
            throw new \Exception("Could not fetch repo info: " . $response->status());
        }

        return $response->json('default_branch') ?? 'main';
    }

    protected function getRecursiveTree(string $owner, string $repo, string $branch, ?string $token): array
    {
        $response = Http::withHeaders([
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
            $response = Http::timeout(5)
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
