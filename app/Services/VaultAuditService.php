<?php

namespace App\Services;

use App\Events\AssetStatusUpdated;
use App\Events\AuditProgressUpdated;
use App\Models\ProjectAsset;
use App\Models\VaultAsset;
use App\Models\AssetEmbedding;
use App\Services\AI\DocumentAuditor;
use App\Services\AI\ProjectAuditor;
use App\Services\AI\EmbeddingService;
use App\Services\InfrastructureScannerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * VaultAuditService - Orchestrates the audit process for different asset types.
 * 
 * Handles branching logic between:
 * - Document audits (PDFs, images, text files) - Skip domain/code checks
 * - Project audits (websites, codebases) - Run InfrastructureScannerService
 */
class VaultAuditService
{
    public function __construct(
        private DocumentAuditor $documentAuditor,
        private ProjectAuditor $projectAuditor,
        private InfrastructureScannerService $infrastructureScanner,
        private LedgerService $ledgerService,
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Run the appropriate audit based on audit_type.
     * 
     * @param VaultAsset $asset The asset to audit
     * @param string $auditType Either 'document' or 'project'
     * @param array $options Additional options like 'deep_wait'
     * @return array The audit result
     */
    public function runAudit(VaultAsset $asset, string $auditType = 'document', array $options = []): array
    {
        Log::info("VaultAuditService: Starting {$auditType} audit for asset {$asset->id}", $options);

        // LOCK CREDITS UPFRONT
        // We charge the full amount initially. Refunds are issued based on the outcome.
        if (!$this->ledgerService->lockAuditCredits($asset->user, $auditType, $asset->file_name)) {
            Log::warning("VaultAuditService: Insufficient credits for asset {$asset->id}");
            $this->updateAssetStatus($asset, 'payment_required', [
                'summary' => 'Insufficient credits to perform this audit.',
            ]);
            // Stop execution if no credits
            return [];
        }

        try {
            // Update status to processing
            $this->updateAssetStatus($asset, 'processing', [
                'summary' => 'LUME Audit in progress...' . (!empty($options['deep_wait']) ? ' (Deep Scan Mode)' : ''),
                'audit_type' => $auditType,
            ]);

            // Branch based on audit type
            $result = match($auditType) {
                'project' => $this->runProjectAudit($asset, $options),
                'design' => $this->runDesignAudit($asset),
                default => $this->runDocumentAudit($asset),
            };

            // Process and save results
            $this->processAuditResult($asset, $result, $auditType);

            // Broadcast explicit 100% completion to close the loop
            $this->broadcastProgress($asset, 'Audit Complete', 100);

            // Broadcast completion via Reverb
            $this->broadcastStatusUpdate($asset);

            // === RAG: GENERATE EMBEDDINGS ===
            // For project audits, chunk and embed the crawl data for semantic search
            if ($auditType === 'project' && !empty($result)) {
                $this->generateEmbeddings($asset, $result);
            }

            // TIERED REFUND LOGIC
            // If flagged or action required, we refund 5 credits (Net Cost: 5)
            // If verified, we keep the full amount (Net Cost: 10)
            if (in_array($asset->status, ['flagged', 'action_required'])) {
                 $refundAmount = 5.00;
                 $this->ledgerService->refundAuditCredits($asset->user, $auditType, $asset->file_name, $refundAmount);
                 Log::info("VaultAuditService: Processed risk refund of {$refundAmount} for asset {$asset->id}");
            }

            Log::info("VaultAuditService: Completed {$auditType} audit for asset {$asset->id} - Status: {$asset->status}");

            return $result;

        } catch (\Throwable $e) {
            Log::error("VaultAuditService: Audit failed for asset {$asset->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'audit_type' => $auditType,
            ]);

            // EXCEPTION REFUND LOGIC
            // Deep technical failure -> Refund 9 credits (Net Cost: 1 credit for system usage)
            $refundAmount = 9.00;
            $this->ledgerService->refundAuditCredits($asset->user, $auditType, $asset->file_name, $refundAmount);

            // Update asset with error status
            $this->updateAssetStatus($asset, 'failed_system', [
                'summary' => 'Audit failed due to high-traffic bottleneck.',
                'audit_type' => $auditType,
                'error' => $e->getMessage(),
            ]);

            // Broadcast failure status via specific Disaster Recovery Event
            \App\Events\AuditFailed::dispatch($asset, 'Server Overloaded: ' . $e->getMessage());

            throw $e;
        }
    }



    /**
     * Run project audit.
     * RUNS InfrastructureScannerService for full tech stack analysis.
     */
    protected function runProjectAudit(VaultAsset $asset, array $options = []): array
    {
        Log::info("VaultAuditService: Running PROJECT audit (with InfrastructureScannerService)");

        $metadata = $asset->metadata ?? [];
        
        // Check if there's a linked ProjectAsset for more data
        $projectAsset = null;
        if (!empty($metadata['project_asset_id'])) {
            $projectAsset = ProjectAsset::find($metadata['project_asset_id']);
        }

        // Build project data from metadata or linked ProjectAsset
        $websiteUrl = $metadata['website_url'] ?? $projectAsset?->website_url;
        $githubUrl = $metadata['github_repo_url'] ?? $projectAsset?->github_repo_url;

        // STRICT VALIDATION: Website URL cannot be GitHub or Figma
        if ($websiteUrl && (Str::contains($websiteUrl, 'github.com') || Str::contains($websiteUrl, 'figma.com'))) {
             throw new \Exception("Invalid Production URL. Please enter the live website URL, not the repository or design link.");
        }

        $projectData = [
            'website_url' => $websiteUrl,
            'github_url' => $githubUrl,
            'dependency_files' => [], // New field for forensic audit
        ];

        // Run InfrastructureScannerService if we have a ProjectAsset with GitHub token
        if ($projectAsset && $projectAsset->github_repo_url) {
            Log::info("VaultAuditService: Running InfrastructureScannerService for project");
            
            try {
                $scanResult = $this->infrastructureScanner->scanProject($projectAsset);
                
                $projectData = array_merge($projectData, [
                    'tech_stack' => $scanResult['tech_footprint'] ?? [],
                    'dns_provider' => $scanResult['dns_provider'] ?? null,
                    'env_variables' => $scanResult['env_variables'] ?? [],
                    'config_files' => $scanResult['config_files'] ?? [],
                ]);
            } catch (\Exception $e) {
                Log::warning("InfrastructureScanner failed: " . $e->getMessage());
                // Continue with partial data
            }
        } else {
             // Use data from asset metadata for non-project assets (fallback)
             $projectData['tech_stack'] = $metadata['tech_stack'] ?? [];
             $projectData['health_data'] = $metadata['health_data'] ?? [];
             $projectData['github_data'] = $metadata['github_data'] ?? [];
             $projectData['dns_provider'] = $metadata['dns_provider'] ?? null;
             $projectData['env_variables'] = $metadata['env_variables'] ?? [];
        }

        // HARVEST RAW EVIDENCE (The "Shark Tweak")
        // Now runs for ALL project audits (URL or Repo)
        $evidence = "NO EVIDENCE AVAILABLE";
        if ($projectAsset) {
             $evidence = $this->getRawEvidence($projectAsset, $websiteUrl, $options);
        } elseif ($websiteUrl) {
             // Fallback if no ProjectAsset object exists (unlikely but safe)
             $evidence = $this->getRawWebsiteEvidence($websiteUrl, $options);
        }

        // Call ProjectAuditor (Sovereign Infrastructure Analyst) with project data AND evidence
        return $this->projectAuditor->analyzeProject($projectData, $evidence);
    }

    /**
     * Harvest Raw Evidence for the AI.
     * Fetches Website HTML and GitHub Dependency Files (composer.json, package.json).
     * 
     * @param ProjectAsset $projectAsset
     * @param string|null $websiteUrl
     * @param array $options
     * @param VaultAsset|null $vaultAsset Optional for broadcasting progress
     * @return string The formatted evidence string
     */
    public function getRawEvidence(ProjectAsset $projectAsset, ?string $websiteUrl, array $options = [], ?VaultAsset $vaultAsset = null): string
    {
        $evidence = "=== RAW EVIDENCE PACKET ===\n";
        $totalSizeStart = strlen($evidence);

        // 1. HARVEST LIVE DOM (The Eyes) - DECOUPLED
        if ($websiteUrl) {
            try {
                $evidence .= $this->fetchLiveDOM($websiteUrl, $options, $vaultAsset);
            } catch (\Exception $e) {
                Log::error("Browsershot Failed: " . $e->getMessage());
                $evidence .= "\n[EVIDENCE ERROR] Browser currently unavailable. Proceeding with code analysis only.\n";
            }
        }

        // 2. HARVEST FLATTENED CODEBASE (The Knowledge)
        // Also fetch composer.json separately for the "Receipt" log (Legacy Rule 3 compliance)
        $token = $projectAsset->getGithubToken();
        $repoParts = $projectAsset->getRepoParts();

        if ($repoParts) {
            // A. Quick Receipt (Composer)
            try {
                $url = "https://api.github.com/repos/{$repoParts['owner']}/{$repoParts['repo']}/contents/composer.json";
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => $token ? "Bearer {$token}" : null,
                    'Accept' => 'application/vnd.github.v3.raw',
                    'User-Agent' => 'LUME-Core-Auditor'
                ])->get($url);
                
                if ($response->successful()) {
                    $content = $response->body();
                    $evidence .= "\n[SOURCE: COMPOSER_JSON]\n{$content}\n";
                    Log::info("Evidence Receipt (composer.json): " . Str::limit($content, 50));
                }
            } catch (\Exception $e) {
                 // Ignore receipt failure, flattener will catch it or not
            }

            // B. The Flattener
            try {
                /** @var \App\Services\GithubFlattenerService $flattener */
                $flattener = app(\App\Services\GithubFlattenerService::class);
                $flattenedCode = $flattener->flattenRepo($projectAsset->github_repo_url, $token);
                
                $evidence .= "\n[SOURCE: FLATTENED_CODEBASE]\n{$flattenedCode}\n";
            } catch (\Exception $e) {
                 $evidence .= "\n[SOURCE: FLATTENED_CODEBASE]\nERROR: Flattener failed: {$e->getMessage()}\n";
            }
        }

        // Log Packet Size
        $packetSize = strlen($evidence);
        Log::info('Evidence Packet Prepared', ['size' => $packetSize]);

        return $evidence;
    }

    /**
     * Fetch Live DOM using Spatie Browsershot.
     * Includes Visual Persistence logic (Deep Wait, Retry, User-Agent rotation).
     * UPGRADED: Supports SSR/CSR Hyration (Next.js/React).
    /**
     * Fetch Live DOM using Spatie Browsershot.
     * Includes Visual Persistence logic (Deep Wait, Retry, User-Agent rotation).
     * UPGRADED: Supports SSR/CSR Hyration (Next.js/React).
     * FORENSIC CRAWLER (The "Spider")
     * 
     * 1. Multi-Page Ingestion: Scans Homepage + Top 10 Critical Links.
     * 2. Network Sniffer: Captures hidden API calls (XHR/Fetch).
     * 3. Visual Persistence: Handles SPAs and 404s responsibly.
     * 
     * @param string $entryUrl The entry URL to start crawling
     * @param array $options Options for crawling
     * @param VaultAsset|null $asset Optional asset for broadcasting progress
     */
    public function crawlForensicData(string $entryUrl, array $options = [], ?VaultAsset $asset = null): string
    {
        $projectMap = "=== PROJECT MAP (CRAWLED) ===\n";
        $evidencePacket = "=== PAGE CONTENT ===\n";
        $networkLogs = "=== NETWORK TRAFFIC ===\n";
        
        $crawledUrls = [$entryUrl];
        $allDiscoveredLinks = [];
        
        // Broadcast: Opening homepage
        if ($asset) {
            $this->broadcastProgress($asset, "Opening {$entryUrl}...", 15);
        }
        
        // 1. CRAWL ENTRY POINT (HOMEPAGE)
        $projectMap .= "- [HOME] {$entryUrl}\n";
        try {
            $homeData = $this->visitPage($entryUrl, $options, true); // true = extract links
            $evidencePacket .= "[URL: {$entryUrl}]\n" . $homeData['content'] . "\n\n";
            $networkLogs .= "[SOURCE: {$entryUrl}]\n" . $homeData['network_logs'];
            $allDiscoveredLinks = $homeData['links'] ?? [];
            
            // Broadcast: Homepage loaded
            if ($asset) {
                $this->broadcastProgress($asset, "Extracting DOM from homepage...", 20);
            }
        } catch (\Exception $e) {
            $evidencePacket .= "[URL: {$entryUrl}] [ERROR] Failed to crawl homepage: {$e->getMessage()}\n\n";
            // If homepage fails, we can't really discover much else, but we return what we have
             return $projectMap . "\n" . $evidencePacket;
        }
        
        // 2. DISCOVER & PRIORITIZE (The "Scout")
        // Priority Keywords for SaaS/App structures
        $criticalKeywords = ['/login', '/signin', '/register', '/signup', '/dashboard', '/admin', '/api', '/docs', '/pricing', '/about'];
        
        $uniqueLinks = array_unique(array_diff($allDiscoveredLinks, $crawledUrls));
        $prioritizedLinks = [];
        $otherLinks = [];

        foreach ($uniqueLinks as $link) {
            $isCritical = false;
            foreach ($criticalKeywords as $keyword) {
                if (str_contains(strtolower($link), $keyword)) {
                    $prioritizedLinks[] = $link;
                    $isCritical = true;
                    break;
                }
            }
            if (!$isCritical) {
                $otherLinks[] = $link;
            }
        }
        
        // Merge: Priority first, then others (randomized/slice/first)
        // Optimization: Take up to 4 more pages (total 5 including home) to prevent timeouts
        $targets = array_slice(array_merge($prioritizedLinks, $otherLinks), 0, 4);
        
        if (!empty($targets)) {
             $projectMap .= "--- Detected Targets ---\n";
             // Broadcast: Listing discovered routes
             if ($asset) {
                 $routeCount = count($targets);
                 $this->broadcastProgress($asset, "Discovered {$routeCount} internal routes...", 25);
             }
        }

        // 3. DEEP DIVE (Visit Targets)
        $targetIndex = 0;
        $totalTargets = count($targets);
        foreach ($targets as $url) {
            $targetIndex++;
            $projectMap .= "- {$url}\n";
            
            // Broadcast: Crawling specific page
            if ($asset) {
                $path = parse_url($url, PHP_URL_PATH) ?: '/';
                $progressPercent = 25 + (int)(($targetIndex / max($totalTargets, 1)) * 20); // 25-45%
                $this->broadcastProgress($asset, "Crawling {$path}...", $progressPercent);
            }
            
            try {
                // Short politeness delay
                sleep(1); 
                $pageData = $this->visitPage($url, $options, false);
                $evidencePacket .= "[URL: {$url}]\n" . $pageData['content'] . "\n\n";
                $networkLogs .= "[SOURCE: {$url}]\n" . $pageData['network_logs'];
                $crawledUrls[] = $url;
            } catch (\Exception $e) {
                $evidencePacket .= "[URL: {$url}] [ERROR] Broken Link or Timeout: {$e->getMessage()}\n\n";
                $projectMap .= "  -> [FAILED]\n";
            }
        }
        
        // Broadcast: Finished crawling, starting analysis
        if ($asset) {
            $this->broadcastProgress($asset, "Sniffing network traffic & headers...", 48);
        }

        // Combine Bundle
        return $projectMap . "\n\n" . $evidencePacket . "\n" . $networkLogs;
    }

    /**
     * Visits a single page, capturing Content and Network Traffic.
     */
    /**
     * Visits a single page, capturing Content and Network Traffic.
     */
    private function visitPage(string $url, array $options, bool $extractLinks = false): array 
    {
        // 1. DYNAMIC TIMEOUT (Fixes the 45s hard limit)
        // We use the timeout passed from the Job (120s), or default to 45s
        $timeoutSeconds = $options['timeout'] ?? 45; 
        $delay = 3000; // 3s delay for Hydration

        Log::info("Crawler: Visiting {$url} (Timeout: {$timeoutSeconds}s)");

        $browser = \Spatie\Browsershot\Browsershot::url($url)
            ->setChromePath('/usr/bin/google-chrome-stable')
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'])
            ->ignoreHttpsErrors()
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
            ->timeout($timeoutSeconds) // <--- FIXED: Uses dynamic timeout
            ->waitUntil('networkidle0')
            ->delay($delay)
            ->windowSize(1920, 1080);

        // 2. APPLY LOW-RAM OPTIMIZATIONS (Fixes the "Failed System" / Server Crash)
        if (!empty($options['disable_images'])) {
            $browser->disableImages(); 
        }

        if (!empty($options['block_urls'])) {
            $browser->blockUrls($options['block_urls']);
        }
            
        // NETWORK SNIFFER INJECTION
        // Note: The sniff script is embedded in the main evaluation below for efficiency.

        // Execute: Deep Discovery Expansion
        // Captures Headers (via self-fetch), Cookies, Head, and Expanded Body
        
        $evalResult = $browser->evaluate("
            (async () => {
                // 1. Network Logs (Resources)
                const logs = window.performance.getEntriesByType('resource')
                    .filter(r => r.initiatorType === 'xmlhttprequest' || r.initiatorType === 'fetch')
                    .map(r => r.name)
                    .filter(url => !url.includes('.css') && !url.includes('.png') && !url.includes('.jpg') && !url.includes('.woff'))
                    .join('\\n');

                // 2. Links (if requested)
                let links = [];
                if ({$extractLinks}) {
                    links = Array.from(document.querySelectorAll('a'))
                        .map(a => a.href)
                        .filter(href => href && href.startsWith(window.location.origin));
                }

                // 3. Network Sniffer (Headers & Cookies)
                const cookies = document.cookie; // Raw cookie string
                
                let headers = {};
                try {
                    // Self-request to capture response headers available to JS
                    const req = await fetch(window.location.href, { method: 'HEAD' });
                    req.headers.forEach((val, key) => headers[key] = val);
                } catch (e) {
                    headers['error'] = e.message;
                }

                // 4. Data Expansion (Head + 25k Body)
                const head = document.head.outerHTML;
                const body = document.body.outerHTML.substring(0, 25000); // Capture structure

                const result = {
                    head: head,
                    body: body,
                    logs: logs,
                    links: links,
                    cookies: cookies,
                    headers: headers
                };
                
                return JSON.stringify(result);
            })()
        ");

        // Parse Result
        $data = json_decode($evalResult, true);
        
        // Safety check
        if (!$data || !is_array($data)) {
             return [
                 'content' => "Error: JS Evaluation Failed or JSON Decode Error",
                 'network_logs' => "",
                 'links' => []
             ];
        }

        $head = $data['head'] ?? '';
        $body = $data['body'] ?? '';
        $cookies = $data['cookies'] ?? '';
        $headers = $data['headers'] ?? [];
        
        // FINGERPRINTING
        $fingerprints = "";
        
        // 1. Cookies (Supabase)
        if (str_contains($cookies, '_supabase')) $fingerprints .= "Database: Supabase (Cookie)\n";
        
        // 2. Headers (Vercel)
        // Headers keys are usually lowercase in fetch API
        if (isset($headers['x-vercel-cache']) || isset($headers['x-vercel-id'])) {
            $fingerprints .= "Hosting: Vercel (Header)\n";
        }
        
        // 3. Content Fingerprints (Next.js/Supabase traces in HTML)
        if (str_contains($body, 'id="__next"')) $fingerprints .= "Framework: Next.js\n";
        if (str_contains($body, 'supabase')) $fingerprints .= "Database: Supabase (Trace)\n";
        
        // Combine Head + Body for Context
        // Truncate safely but generously
        $fullContent = $fingerprints . "\n=== HEAD ===\n" . $head . "\n=== BODY (First 25k) ===\n" . $body;

        $result = [
            'content' => $fullContent,
            'network_logs' => ($data['logs'] ?? '') . "\n",
            'links' => $data['links'] ?? []
        ];
        
        // Memory Management
        unset($evalResult);
        unset($data);
        unset($browser);
        gc_collect_cycles();
        
        return $result;
    }
    
    /**
     * Legacy Wrapper for backward compatibility if needed, 
     * but strictly we are replacing it.
     */
    public function fetchLiveDOM(string $url, array $options = [], ?VaultAsset $vaultAsset = null): string
    {
         return $this->crawlForensicData($url, $options, $vaultAsset);
    }

    /**
     * Fallback evidence gatherer for URL-only scans without a ProjectAsset model
     */
    public function getRawWebsiteEvidence(string $websiteUrl, array $options = []): string
    {
        $evidence = "=== RAW EVIDENCE PACKET ===\n";
        $evidence .= $this->fetchLiveDOM($websiteUrl, $options);
        return $evidence . "\n[INFO] URL-Only Scan. No Code Repository Evidence Available.\n";
    }

    /**
     * Run design audit.
     * Fetches proof images linked to this asset and sends to AI for visual analysis.
     */
    protected function runDesignAudit(VaultAsset $asset): array
    {
        Log::info("VaultAuditService: Running DESIGN audit (visual proof analysis)");

        $this->broadcastProgress($asset, 'Initializing Design Auditor...', 10);

        $metadata = $asset->metadata ?? [];
        $projectAssetId = $metadata['project_asset_id'] ?? null;

        // Find linked proof images
        $proofs = VaultAsset::where('metadata->project_asset_id', $projectAssetId)
            ->where('metadata->is_proof', true)
            ->take(10) // Max 10 images
            ->get();

        if ($proofs->isEmpty()) {
            Log::warning("VaultAuditService: No proof images found for design audit.");
            return [
                'verdict' => 'flagged',
                'score' => 0,
                'message' => 'No visual proof images found for design verification.',
                'mobile_verified' => false,
                'is_marketplace_eligible' => false,
                'audit_type' => 'design',
            ];
        }

        $this->broadcastProgress($asset, 'Downloading Visual Proofs...', 30);

        // Download and Base64 encode all images
        // Download and Base64 encode images (Memory Optimized)
        $images = [];
        foreach ($proofs as $proof) {
            // Memory Check: Garbage collect before processing next image
            if (memory_get_usage() > 100 * 1024 * 1024) gc_collect_cycles(); // specific check if needed, or just standard loop

            $content = Storage::disk('r2')->get($proof->file_path);
            if ($content) {
                // Resize if too large (simple check by string length)
                // 1MB = ~1.37MB base64. Limit to ~3MB raw per image to be safe?
                // For now, relies on AI service handling, but we can resize if PHP has gd/imagick
                // Since user asked for explicit resize:
                if (strlen($content) > 2 * 1024 * 1024 && extension_loaded('gd')) {
                     try {
                         $img = imagecreatefromstring($content);
                         if ($img) {
                             $width = imagesx($img);
                             $height = imagesy($img);
                             if ($width > 1920) {
                                  $newWidth = 1920;
                                  $newHeight = floor($height * ($newWidth / $width));
                                  $resized = imagescale($img, $newWidth, $newHeight);
                                  ob_start();
                                  imagejpeg($resized); // Convert to compact JPEG
                                  $content = ob_get_clean();
                                  imagedestroy($resized);
                             }
                             imagedestroy($img);
                         }
                     } catch (\Throwable $e) {
                         // Fallback to original content
                     }
                }

                $images[] = [
                    'mime_type' => $proof->mime_type,
                    'data' => base64_encode($content)
                ];
                // Free memory immediately
                unset($content);
            }
        }

        if (count($images) < 5) {
            return [
                'verdict' => 'action_required',
                'score' => 30,
                'message' => 'Insufficient visual proofs. Minimum 5 required.',
                'mobile_verified' => false,
                'is_marketplace_eligible' => false,
                'audit_type' => 'design',
            ];
        }

        $this->broadcastProgress($asset, 'Analyzing Brand Consistency...', 50);
        $this->broadcastProgress($asset, 'Verifying Mobile Responsiveness...', 70);

        // Call ProjectAuditor (Lead UX Auditor) with images
        $projectAsset = ProjectAsset::find($projectAssetId);
        $result = $this->projectAuditor->analyzeDesign($images, [
            'name' => $projectAsset?->name ?? $asset->file_name,
            'url' => $projectAsset?->website_url ?? 'N/A'
        ]);

        $this->broadcastProgress($asset, 'Generating Design Score...', 90);

        return $result;
    }

    /**
     * Process the audit result and update the asset.
     * 
     * Status Logic:
     * - score >= 80 && is_marketplace_eligible = 'verified'
     * - score >= 80 && !is_marketplace_eligible = 'verified_private' (PII detected)
     * - score < 80 = 'action_required'
     * - malicious/non-professional = 'flagged'
     */
    protected function processAuditResult(VaultAsset $asset, array $result, string $auditType): void
    {
        $score = $result['score'] ?? 0;
        $isMarketplaceEligible = $result['is_marketplace_eligible'] ?? false;
        $isMalicious = ($result['verdict'] ?? '') === 'flagged' && $score < 40;

        // === MANDATORY REPO RULE ===
        // Logic Hallucination Fix: No Repo = No Sale
        // Even if AI gives 100% score, system MUST override if no github_repo_url
        if ($auditType === 'project' && empty($asset->github_repo_url)) {
            $isMarketplaceEligible = false;
            Log::info("VaultAuditService: Mandatory Repo Rule - Forcing is_marketplace_eligible=FALSE for asset {$asset->id} (No GitHub repo linked)");
        }

        // Determine status based on score and eligibility
        // Using Arjay Scale: 85+ for Verified, 80-84 for Action Required
        if ($isMalicious) {
            // Low score + flagged = malicious/totally non-professional
            $status = 'flagged';
            // REFUND RULE: Users generally expect refunds for failed/flagged scans in this specific business logic
             $this->ledgerService->refundAuditCredits($asset->user, $auditType, $asset->file_name ?? 'Asset');
        } elseif ($score >= 85) {
            // High quality - meets Arjay Scale for Verified
            if ($isMarketplaceEligible) {
                // Can be sold on marketplace
                $status = 'verified';
            } else {
                // PII detected OR no repo linked - private only
                $status = 'verified_private';
            }
        } elseif ($score >= 80) {
            // Good quality but needs minor improvements - Arjay Scale threshold
            $status = 'action_required';
        } else {
            // Needs significant improvement
            $status = 'action_required';
        }

        Log::info("VaultAuditService: Audit result - Score: {$score}, Eligible: " . ($isMarketplaceEligible ? 'yes' : 'no') . ", Status: {$status}");

        // Build metadata based on audit type
        if ($auditType === 'project') {
            $metadata = $this->buildProjectMetadata($result);
        } else {
            $metadata = $this->buildDocumentMetadata($result);
        }

        // Parse suggested value if present
        $suggestedValue = null;
        if (!empty($result['suggested_value'])) {
            $suggestedValue = (float) preg_replace('/[^0-9.]/', '', $result['suggested_value']);
        }

        // Update the asset
        // Prefer markdown_report for full_audit_report if available
        $fullReport = $result['markdown_report'] ?? json_encode($result);
        
        // Ensure radar_data has the correct structure
        $radarData = $result['breakdown'] ?? $result['radar_data'] ?? [
            'code_resilience' => 0,
            'security_perimeter' => 0,
            'deployment_maturity' => 0,
            'seo_authority' => 0,
            'database_architecture' => 0
        ];
        
        // === WEIGHTED SCORE CALCULATION ===
        // Weights: Security (35%), Code Resilience (25%), Deployment (20%), SEO (10%), Database (10%)
        $weightedScore = round(
            ($radarData['code_resilience'] ?? 0) * 0.25 +
            ($radarData['security_perimeter'] ?? 0) * 0.35 +
            ($radarData['deployment_maturity'] ?? 0) * 0.20 +
            ($radarData['seo_authority'] ?? 0) * 0.10 +
            ($radarData['database_architecture'] ?? 0) * 0.10
        );
        
        // Override the score in metadata with weighted average
        $metadata['score'] = $weightedScore;
        $metadata['confidence_score'] = $weightedScore;
        
        // For document audits, use the AI's score directly (not weighted)
        $finalScore = ($auditType === 'project') ? $weightedScore : ($result['score'] ?? $weightedScore);
        
        // Create Audit History Record (LUME Chronos)
        // This ensures historical tracking while the asset reflects the latest state
        $asset->auditHistory()->create([
            'score' => $finalScore,
            'status' => $status,
            'metadata' => $metadata,
        ]);

        $asset->update([
            'status' => $status,
            'score' => $finalScore, // Explicitly save score to $asset->score column
            'metadata' => $metadata,
            'suggested_value' => $suggestedValue,
            'is_for_sale' => $asset->is_for_sale && $isMarketplaceEligible && $status === 'verified',
            'full_audit_report' => $fullReport,
            'radar_data' => $radarData,
        ]);
    }

    /**
     * Build metadata for document audits.
     */
    protected function buildDocumentMetadata(array $result): array
    {
        $breakdown = $result['breakdown'] ?? [];
        
        return [
            'is_professional' => ($result['verdict'] ?? 'flagged') === 'verified',
            'document_type' => $result['document_type'] ?? 'Other',
            'category' => $result['category'] ?? 'Other',
            'confidence_score' => $result['score'] ?? 0,
            'breakdown' => [
                'formatting' => $breakdown['structure'] ?? 0,
                'content_quality' => $breakdown['integrity'] ?? 0,
                'industry_relevance' => $breakdown['expertise'] ?? 0,
            ],
            'recommendations' => $result['insights'] ?? [],
            'summary' => implode(' ', array_slice($result['insights'] ?? [], 0, 2)),
            'flag_reason' => implode(', ', $result['warning_flags'] ?? []),
            'is_marketplace_eligible' => $result['is_marketplace_eligible'] ?? false,
            'privacy_warning' => $result['privacy_warning'] ?? null,
            'pii_reason' => $result['pii_reason'] ?? null,
            'audit_type' => 'document',
            'suggested_value' => $result['suggested_value'] ?? null,
            '_raw_verdict' => $result['verdict'] ?? 'flagged',
        ];
    }

    /**
     * Build metadata for project audits.
     */
    protected function buildProjectMetadata(array $result): array
    {
        return [
            'is_professional' => ($result['verdict'] ?? 'flagged') === 'verified',
            'project_type' => $result['project_type'] ?? 'Web App', // Fallback
            'confidence_score' => $result['score'] ?? 0,
            
            // New Deep Context Fields
            'niche' => $result['niche'] ?? 'Uncategorized',
            'site_classification' => $result['site_classification'] ?? 'Unknown',
            'supply_chain_risk' => $result['supply_chain_risk'] ?? null,
            'compliance_check' => $result['compliance_check'] ?? null,
            'carbon_footprint' => $result['carbon_footprint'] ?? null,
            'accessibility' => $result['accessibility'] ?? null,
            'data_sovereignty' => $result['data_sovereignty'] ?? null,
            'business_summary' => $result['business_summary'] ?? '',
            'vector_details' => $result['vector_details'] ?? [],
            'risk_matrix' => $result['risk_matrix'] ?? [],
            'tech_footprint_explanations' => $result['tech_footprint_explanations'] ?? [],

            'breakdown' => $result['breakdown'] ?? $result['radar_data'] ?? [
                'code_resilience' => 0,
                'security_perimeter' => 0,
                'deployment_maturity' => 0,
                'seo_authority' => 0,
                'database_architecture' => 0
            ],
            'tech_assessment' => $result['tech_assessment'] ?? null,
            'security_assessment' => $result['security_assessment'] ?? null,
            'summary' => $result['summary'] ?? '', // Technical summary
            'insights' => $result['insights'] ?? [],
            'warning_flags' => $result['warning_flags'] ?? [],
            'is_marketplace_eligible' => $result['is_marketplace_eligible'] ?? false,
            'audit_type' => 'project',
            '_raw_verdict' => $result['verdict'] ?? 'flagged',
        ];
    }

    /**
     * Update asset status with metadata.
     */
    protected function updateAssetStatus(VaultAsset $asset, string $status, array $metadata): void
    {
        $existingMetadata = $asset->metadata ?? [];
        $asset->update([
            'status' => $status,
            'metadata' => array_merge($existingMetadata, $metadata),
        ]);
    }

    /**
     * Broadcast status update via Reverb for real-time UI updates.
     */
    protected function broadcastStatusUpdate(VaultAsset $asset): void
    {
        try {
            event(new AssetStatusUpdated($asset->fresh()));
            Log::info("VaultAuditService: Broadcast AssetStatusUpdated for asset {$asset->id}");
        } catch (\Throwable $e) {
            // Log but don't fail - the audit result is more important
            Log::warning("VaultAuditService: Failed to broadcast status update", [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast progress update via Reverb for real-time loading screen.
     */
    protected function broadcastProgress(VaultAsset $asset, string $step, int $progress): void
    {
        try {
            event(new AuditProgressUpdated($asset, $step, $progress));
            Log::debug("VaultAuditService: Progress {$progress}% - {$step}");
        } catch (\Throwable $e) {
            // Log but don't fail - progress updates are non-critical
            Log::warning("VaultAuditService: Failed to broadcast progress", [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    /**
     * Run the document audit logic (PDF/Images).
     */
    protected function runDocumentAudit(VaultAsset $asset): array
    {
        Log::info("VaultAuditService: Processing document content for asset {$asset->id}");

        $this->broadcastProgress($asset, 'Extracting Document Content...', 25);
        
        $mimeType = $asset->mime_type;
        $content = Storage::disk('r2')->get($asset->file_path);
        
        $aiContent = "";
        $aiMimeType = null;

        // HANDLE PDFS
        if ($mimeType === 'application/pdf') {
            try {
                // Use the PDF Parser
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($content);
                $aiContent = $pdf->getText();
                $aiMimeType = null; // Send as text

                // If text is empty (Scanned PDF?), we might want to send as image?
                // For now, let's stick to text-first as per existing logic, or if empty, maybe trigger failure?
                if (empty(trim($aiContent))) {
                     Log::warning("VaultAuditService: PDF parsed but no text found (Scanned PDF?) - Asset {$asset->id}");
                }
            } catch (\Throwable $e) {
                Log::warning("VaultAuditService: PDF Parse Failed: " . $e->getMessage());
                // Fallback or fail? continue with empty string to get "action_required"
            }
        } 
        // HANDLE IMAGES (Gemini Vision)
        elseif (Str::startsWith($mimeType, 'image/')) {
            $aiContent = base64_encode($content);
            $aiMimeType = $mimeType;
            Log::info("VaultAuditService: Image detected, sending as Vision payload. Mime: {$mimeType}");
        }
        // HANDLE OTHER TEXT (JSON, TXT)
        else {
             $aiContent = $content;
             $aiMimeType = null;
        }

        $this->broadcastProgress($asset, 'Analyzing Professional Integrity...', 60);

        // Call the AI Auditor
        // Call DocumentAuditor (Document Integrity Expert) with document content
        return $this->documentAuditor->analyzeDocument($aiContent, $aiMimeType);
    }

    /**
     * Get credit cost for an audit type.
     * Delegates to LedgerService for single source of truth.
     */
    public static function getCreditCost(string $auditType): float
    {
        return LedgerService::getAuditFee($auditType);
    }

    /**
     * Generate embeddings for RAG (Retrieval Augmented Generation).
     * Chunks the audit data and stores vector embeddings for semantic search.
     */
    protected function generateEmbeddings(VaultAsset $asset, array $auditResult): void
    {
        try {
            Log::info("VaultAuditService: Generating embeddings for asset {$asset->id}");
            
            // Clear existing embeddings for this asset
            AssetEmbedding::where('asset_id', $asset->id)->delete();
            
            // Build content to embed
            $contentParts = [];
            
            // Add summary
            if (!empty($auditResult['summary'])) {
                $contentParts[] = "SUMMARY: " . $auditResult['summary'];
            }
            
            // Add insights
            if (!empty($auditResult['insights'])) {
                $contentParts[] = "INSIGHTS:\n" . implode("\n", $auditResult['insights']);
            }
            
            // Add recommendations
            if (!empty($auditResult['recommendations'])) {
                $contentParts[] = "RECOMMENDATIONS:\n" . implode("\n", $auditResult['recommendations']);
            }
            
            // Add tech stack
            if (!empty($auditResult['tech_assessment']['stack'])) {
                $stackNames = array_map(fn($t) => $t['name'] ?? '', $auditResult['tech_assessment']['stack']);
                $contentParts[] = "TECH STACK: " . implode(', ', $stackNames);
            }
            
            // Add warning flags
            if (!empty($auditResult['warning_flags'])) {
                $contentParts[] = "WARNINGS:\n" . implode("\n", $auditResult['warning_flags']);
            }
            
            // Add the full report as a chunk (will be split by EmbeddingService)
            $fullReportJson = json_encode($auditResult, JSON_PRETTY_PRINT);
            $contentParts[] = "FULL FORENSIC REPORT:\n" . $fullReportJson;
            
            // Combine all parts
            $fullContent = implode("\n\n", $contentParts);
            
            // Chunk the content
            $chunks = $this->embeddingService->chunkText($fullContent, 1500, 150);
            
            Log::info("VaultAuditService: Created " . count($chunks) . " chunks for embedding");
            
            // Generate embeddings for each chunk
            $embeddedChunks = $this->embeddingService->embedChunks($chunks);
            
            // Save to database
            foreach ($embeddedChunks as $item) {
                AssetEmbedding::create([
                    'asset_id' => $asset->id,
                    'content' => $item['content'],
                    'embedding' => $item['embedding'],
                ]);
            }
            
            Log::info("VaultAuditService: Saved " . count($embeddedChunks) . " embeddings for asset {$asset->id}");
            
        } catch (\Throwable $e) {
            // Non-critical failure - log and continue
            Log::error("VaultAuditService: Failed to generate embeddings for asset {$asset->id}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
