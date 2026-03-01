<?php

namespace App\Services\AI;

use App\Models\VaultAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

/**
 * DeepAuditService - Performs comprehensive 10-page crawl with AI analysis.
 * 
 * Features:
 * - Multi-page harvesting (up to 10 pages)
 * - Lighthouse-style performance checks
 * - Security header analysis
 * - Few-shot prompting for elite architecture detection
 */
class DeepAuditService
{
    protected string $chromePath = '/usr/bin/chromium-browser';
    protected string $npmPath = '/usr/bin/npm';

    /**
     * Run a deep forensic audit on a project.
     *
     * @param VaultAsset $asset
     * @param string $websiteUrl
     * @param string|null $customPrompt Optional user-specified focus area
     * @return array The deep audit result with radar_data and markdown report
     */
    public function runDeepAudit(VaultAsset $asset, string $websiteUrl, ?string $customPrompt = null): array
    {
        Log::info("DeepAuditService: Starting deep audit for {$websiteUrl}", [
            'custom_prompt' => $customPrompt ? 'provided' : 'none'
        ]);

        // 1. Harvest 10 pages
        $pages = $this->harvestPages($websiteUrl, 10);
        Log::info("DeepAuditService: Harvested " . count($pages) . " pages");

        // 2. Analyze security headers
        $securityHeaders = $this->analyzeSecurityHeaders($websiteUrl);

        // 3. Analyze performance (simplified Lighthouse-style)
        $performanceData = $this->analyzePerformance($websiteUrl);

        // 4. Build context for AI
        $context = $this->buildAuditContext($pages, $securityHeaders, $performanceData);

        // 5. Call AI with few-shot prompting and optional custom focus
        $result = $this->analyzeWithAI($context, $websiteUrl, $customPrompt);

        // 6. Ensure radar_data is properly formatted
        $result['radar_data'] = $result['breakdown'] ?? [
            'code_resilience' => 0,
            'security_perimeter' => 0,
            'deployment_maturity' => 0,
            'seo_authority' => 0,
            'database_architecture' => 0
        ];

        Log::info("DeepAuditService: Completed deep audit for {$websiteUrl}");

        return $result;
    }

    /**
     * Harvest multiple pages from a website.
     */
    protected function harvestPages(string $baseUrl, int $maxPages = 10): array
    {
        $pages = [];
        $visited = [];
        $toVisit = [$baseUrl];
        
        while (count($pages) < $maxPages && !empty($toVisit)) {
            $url = array_shift($toVisit);
            
            if (in_array($url, $visited)) {
                continue;
            }
            
            $visited[] = $url;
            
            try {
                $pageData = $this->fetchPage($url);
                if ($pageData) {
                    $pages[] = $pageData;
                    
                    // Extract internal links
                    $links = $this->extractInternalLinks($pageData['html'], $baseUrl);
                    foreach ($links as $link) {
                        if (!in_array($link, $visited) && !in_array($link, $toVisit)) {
                            $toVisit[] = $link;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("DeepAuditService: Failed to fetch {$url}: " . $e->getMessage());
            }
        }
        
        return $pages;
    }

    /**
     * Fetch a single page.
     */
    protected function fetchPage(string $url): ?array
    {
        try {
            $html = Browsershot::url($url)
                ->setChromePath($this->chromePath)
                ->setNpmBinary($this->npmPath)
                ->noSandbox() // Required for Docker/WSL environments
                ->waitUntilNetworkIdle()
                ->timeout(30)
                ->bodyHtml();

            return [
                'url' => $url,
                'html' => mb_substr($html, 0, 50000), // Limit size
                'title' => $this->extractTitle($html)
            ];
        } catch (\Throwable $e) {
            Log::warning("DeepAuditService: Page fetch failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract page title.
     */
    protected function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return 'Untitled';
    }

    /**
     * Extract internal links from HTML.
     */
    protected function extractInternalLinks(string $html, string $baseUrl): array
    {
        $links = [];
        $parsedBase = parse_url($baseUrl);
        $baseHost = $parsedBase['host'] ?? '';

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);

        foreach ($matches[1] as $href) {
            // Skip anchors, javascript, mailto
            if (str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            // Handle relative URLs
            if (str_starts_with($href, '/')) {
                $href = rtrim($baseUrl, '/') . $href;
            }

            // Only include same-domain links
            $parsedHref = parse_url($href);
            if (($parsedHref['host'] ?? '') === $baseHost) {
                $links[] = strtok($href, '#'); // Remove fragments
            }
        }

        return array_unique($links);
    }

    /**
     * Analyze security headers.
     */
    protected function analyzeSecurityHeaders(string $url): array
    {
        try {
            $response = Http::timeout(10)->head($url);
            $headers = $response->headers();

            $securityScore = 0;
            $findings = [];

            // Check key security headers
            $securityHeaders = [
                'Strict-Transport-Security' => 'HSTS',
                'Content-Security-Policy' => 'CSP',
                'X-Content-Type-Options' => 'X-Content-Type',
                'X-Frame-Options' => 'X-Frame-Options',
                'X-XSS-Protection' => 'XSS Protection',
                'Referrer-Policy' => 'Referrer Policy'
            ];

            foreach ($securityHeaders as $header => $name) {
                if (isset($headers[$header]) || isset($headers[strtolower($header)])) {
                    $securityScore += 15;
                    $findings[] = "✓ {$name} header present";
                } else {
                    $findings[] = "✗ Missing {$name} header";
                }
            }

            return [
                'score' => min(100, $securityScore),
                'findings' => $findings,
                'https' => str_starts_with($url, 'https://')
            ];
        } catch (\Throwable $e) {
            return [
                'score' => 0,
                'findings' => ['Failed to analyze headers: ' . $e->getMessage()],
                'https' => str_starts_with($url, 'https://')
            ];
        }
    }

    /**
     * Analyze performance (simplified).
     */
    protected function analyzePerformance(string $url): array
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(15)->get($url);
            $loadTime = round((microtime(true) - $start) * 1000);
            
            $size = strlen($response->body());
            
            // Score based on load time and size
            $timeScore = $loadTime < 1000 ? 100 : ($loadTime < 3000 ? 70 : ($loadTime < 5000 ? 40 : 20));
            $sizeScore = $size < 100000 ? 100 : ($size < 500000 ? 70 : ($size < 1000000 ? 40 : 20));
            
            return [
                'load_time_ms' => $loadTime,
                'page_size_bytes' => $size,
                'time_score' => $timeScore,
                'size_score' => $sizeScore,
                'overall_score' => round(($timeScore + $sizeScore) / 2)
            ];
        } catch (\Throwable $e) {
            return [
                'load_time_ms' => 0,
                'page_size_bytes' => 0,
                'time_score' => 0,
                'size_score' => 0,
                'overall_score' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build context for AI analysis.
     */
    protected function buildAuditContext(array $pages, array $security, array $performance): string
    {
        $context = "=== DEEP FORENSIC AUDIT DATA ===\n\n";
        
        $context .= "## PAGES ANALYZED (" . count($pages) . " pages)\n\n";
        foreach ($pages as $i => $page) {
            $context .= "### Page " . ($i + 1) . ": {$page['title']}\n";
            $context .= "URL: {$page['url']}\n";
            $context .= "HTML Snippet (first 5000 chars):\n```html\n" . mb_substr($page['html'], 0, 5000) . "\n```\n\n";
        }

        $context .= "## SECURITY HEADERS ANALYSIS\n";
        $context .= "Security Score: {$security['score']}/100\n";
        $context .= "HTTPS: " . ($security['https'] ? 'Yes' : 'No') . "\n";
        $context .= "Findings:\n" . implode("\n", $security['findings']) . "\n\n";

        $context .= "## PERFORMANCE METRICS\n";
        $context .= "Load Time: {$performance['load_time_ms']}ms\n";
        $context .= "Page Size: " . round($performance['page_size_bytes'] / 1024) . "KB\n";
        $context .= "Performance Score: {$performance['overall_score']}/100\n\n";

        return $context;
    }

    /**
     * Analyze with AI using few-shot prompting.
     */
    protected function analyzeWithAI(string $context, string $url, ?string $customPrompt = null): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";

        $fewShotPrompt = $this->getFewShotPrompt();
        
        // Prepend custom user focus if provided
        $customFocus = '';
        if ($customPrompt) {
            $customFocus = "\n\n=== USER SPECIFIC FOCUS ===\nThe user has requested you focus on: {$customPrompt}\nPlease give extra attention to this area in your analysis.\n";
        }
        
        $fullPrompt = $fewShotPrompt . $customFocus . "\n\n" . $context . "\n\nNow analyze this website: {$url}";

        $response = Http::timeout(90)->post($apiUrl, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ]
        ]);

        if ($response->failed()) {
            Log::error("DeepAuditService: AI API failed", ['status' => $response->status()]);
            throw new \Exception('AI analysis failed');
        }

        $data = $response->json();
        $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $responseText = preg_replace('/^```json\s*|\s*```$/', '', trim($responseText));

        try {
            return json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error("DeepAuditService: Failed to parse AI response", ['error' => $e->getMessage()]);
            throw new \Exception('Invalid AI response format');
        }
    }

    /**
     * Get the few-shot prompt with elite vs spam architecture examples.
     */
    protected function getFewShotPrompt(): string
    {
        return <<<'PROMPT'
You are the LUME Sovereign Intelligence performing a Deep Forensic Audit.
You analyze websites with ZERO BIAS, only EVIDENCE.

=== FEW-SHOT EXAMPLES ===

**EXAMPLE 1: ELITE ARCHITECTURE (Score: 95)**
Website: vercel.com
Analysis:
- Code Resilience: 98 - Uses TypeScript, comprehensive error boundaries, graceful degradation
- Security Perimeter: 95 - HSTS, CSP, all security headers, no leaked keys
- Deployment Maturity: 100 - Edge deployment, zero-downtime updates, multi-region
- SEO Authority: 90 - Perfect meta tags, structured data, sitemap
- Database Architecture: 92 - Serverless Postgres, connection pooling, read replicas

**EXAMPLE 2: SPAM ARCHITECTURE (Score: 25)**
Website: cheap-knockoff-site.xyz
Analysis:
- Code Resilience: 15 - No error handling, jQuery spaghetti, global variables
- Security Perimeter: 10 - No HTTPS, exposed API keys in source, no CSP
- Deployment Maturity: 20 - Single server, no CDN, frequent 502 errors
- SEO Authority: 35 - Missing meta tags, no sitemap, duplicate content
- Database Architecture: 30 - Raw SQL queries, no ORM, SQL injection vulnerabilities

**EXAMPLE 3: ELITE ARCHITECTURE (Score: 88)**
Website: linear.app
Analysis:
- Code Resilience: 90 - React with TypeScript, atomic design, comprehensive testing
- Security Perimeter: 88 - Strong headers, OAuth implementation, rate limiting
- Deployment Maturity: 92 - Kubernetes, canary deployments, A/B testing
- SEO Authority: 80 - Good meta, could improve structured data
- Database Architecture: 90 - PostgreSQL, proper indexing, query optimization

=== YOUR TASK ===

Analyze the provided website data and return a JSON object with:

{
  "verdict": "verified" | "action_required" | "flagged",
  "score": 0-100,
  "summary": "Executive summary in 2-3 sentences",
  "breakdown": {
    "code_resilience": 0-100,
    "security_perimeter": 0-100,
    "deployment_maturity": 0-100,
    "seo_authority": 0-100,
    "database_architecture": 0-100
  },
  "tech_assessment": {
    "architecture": "Monolithic | SPA | Static | SSR",
    "stack": [{"name": "string", "type": "string", "confidence": "High|Medium|Low"}]
  },
  "security_assessment": {
    "score": 0-100,
    "https_valid": boolean,
    "vulnerabilities": ["string"]
  },
  "insights": ["5 key findings"],
  "recommendations": ["3 prioritized remediation steps"],
  "warning_flags": ["any critical issues"],
  "is_marketplace_eligible": boolean,
  "markdown_report": "A detailed markdown report with sections for each audit vector"
}

CRITICAL RULES:
1. Return ONLY valid JSON
2. All breakdown scores MUST be integers 0-100
3. The markdown_report should be a professional-grade technical audit
4. Be specific and evidence-based in your analysis
PROMPT;
    }
}
