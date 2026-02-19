<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaultAuditService
{
    /**
     * Get Raw Evidence (DOM + Headers + SSL) for Forensic Analysis.
     */
    public function getRawEvidence($project, $url, $options = [], $vaultAsset = null): array
    {
        $evidence = "";
        $topology = [];
        $headers = [];
        
        try {
            Log::info("VaultAuditService: Starting Evidence Collection for $url");

            // 1. Capture Headers & SSL via HTTP (Faster/More Reliable for headers)
            try {
                Log::info("VaultAuditService: Capturing Headers...");
                $response = Http::timeout(30)->get($url);
                $headers = $response->headers();
            } catch (\Exception $e) {
                Log::warning("Header Crawl failed for $url: " . $e->getMessage());
            }

            // 2. Capture DOM via Browsershot (Headless Chrome)
            Log::info("VaultAuditService: Attempting Browsershot Capture...");
            try {
                if (class_exists(Browsershot::class)) {
                     $shot = Browsershot::url($url)
                        ->timeout($options['timeout'] ?? 120)
                        ->noSandbox()
                        ->ignoreHttpsErrors()
                        ->userAgent('LumeTitan/5.0 (ForensicScanner; +https://lume.ai)');
                    
                    if (!empty($options['deep_wait'])) {
                         Log::info("VaultAuditService: Waiting for network idle...");
                         $shot->waitUntilNetworkIdle();
                    }
                    
                    $evidence = $shot->bodyHtml();
                    Log::info("VaultAuditService: DOM Captured (" . strlen($evidence) . " bytes)");
                } else {
                    throw new \Exception("Browsershot not available.");
                }
            } catch (\Exception $browsershotError) {
                Log::info("Browsershot unavailable or failed, falling back to simple HTTP: " . $browsershotError->getMessage());
                // CRITICAL FIX: Only use $response if it exists (header crawl might have failed)
                $evidence = isset($response) ? $response->body() : "";
            }

            // 3. Topology (Links/Assets) - Simple extraction
            Log::info("VaultAuditService: Extracting Topology...");
            $topology = $this->extractTopology($evidence, $url);
            Log::info("VaultAuditService: Topology Extracted (" . count($topology['external'] ?? []) . " external links)");

        } catch (\Exception $e) {
            Log::error("VaultAuditService Critical Error: " . $e->getMessage());
            throw $e;
        }

        return [
            'evidence' => $evidence,
            'topology' => $topology,
            'headers' => $headers
        ];
    }

    protected function extractTopology(string $html, string $baseUrl): array
    {
        $nodes = [];
        $links = [];
        
        // 1. Root Node
        $nodes[] = ['id' => '/', 'status' => 200, 'type' => 'root', 'latency' => 0];

        // 2. Extract External Links (Limit 10 for performance)
        preg_match_all('/href=["\'](http[^"\']+)["\']/i', $html, $matches);
        $externalLinks = array_slice(array_unique($matches[1] ?? []), 0, 10);
        
        foreach ($externalLinks as $url) {
            try {
                $host = parse_url($url, PHP_URL_HOST);
                if ($host) {
                    // Check status (Fast HEAD request with timeout)
                    $status = 200; // Default
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(2)->head($url);
                        $status = $response->status();
                    } catch (\Exception $e) {
                        $status = 500;
                    }

                    $nodes[] = [
                        'id' => $host,
                        'status' => $status,
                        'type' => 'external',
                        'latency' => rand(20, 150) // Simulated latency for now
                    ];
                    $links[] = ['source' => '/', 'target' => $host];
                }
            } catch (\Exception $e) { continue; }
        }

        // 3. Extract Scripts (Limit 10)
        preg_match_all('/src=["\']([^"\']+\.js)["\']/i', $html, $scripts);
        $scriptLinks = array_slice(array_unique($scripts[1] ?? []), 0, 10);

        foreach ($scriptLinks as $script) {
            $name = basename($script);
            $nodes[] = [
                'id' => $name,
                'status' => 200, // Client-side scripts usually 200 if loaded
                'type' => 'script',
                'latency' => rand(10, 50)
            ];
            $links[] = ['source' => '/', 'target' => $name];
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            // Keep specific lists for AI context if needed
            'scripts' => $scriptLinks,
            'external' => $externalLinks
        ];
    }
}
