<?php

namespace App\Jobs;

use App\Models\VaultAsset;
use App\Services\AI\SecurityAuditor;
use Spatie\Browsershot\Browsershot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use App\Services\Calculations\ForensicCalculator;

class PerformSecurityScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 Minutes for Deep Penetration

    public function __construct(
        public VaultAsset $asset,
        public ?string $customPrompt = null,
        public bool $isRescan = false,
        public ?int $userId = null,
        public ?string $auditType = null
    ) {
        // Auto-populate if not provided
        $this->userId = $userId ?? $asset->user_id;
        $this->auditType = $auditType ?? ($isRescan ? 'rescan' : 'pentest');
        
        Log::info("PerformSecurityScan Job Constructed for Asset: {$this->asset->id} (Rescan: " . ($this->isRescan ? 'Yes' : 'No') . ")");
    }

    public function handle(SecurityAuditor $auditor, ForensicCalculator $calculator)
    {
        Log::info("PerformSecurityScan Job Processing... ID: {$this->asset->id} with prompt: " . ($this->customPrompt ?? 'none'));
        
        // NOTE: Quota/Credits already locked by VaultUploadController via lockAuditCredits()
        // We don't increment usage here to avoid double charging

        // 0. INIT EVENT
        $this->updateProgress('step_init', 5, 'Initializing Deep Scan Protocol...');

        // CLEAR STALE DATA: Remove previous scan results to prevent UI confusion
        $cleanMetadata = $this->asset->metadata ?? [];
        if (isset($cleanMetadata['security_audit'])) unset($cleanMetadata['security_audit']);
        if (isset($cleanMetadata['specific_metadata'])) unset($cleanMetadata['specific_metadata']);
        $this->asset->update(['metadata' => $cleanMetadata]);

        // CRITICAL FIX: URL Resolution with Multiple Fallbacks
        $url = $this->asset->metadata['website_url'] 
            ?? $this->asset->website_url 
            ?? $this->asset->original_url 
            ?? $this->asset->file_name 
            ?? null;
            
        if (!$url) {
            Log::error("No URL found for security scan. Asset ID: {$this->asset->id}, Metadata: " . json_encode($this->asset->metadata));
            
            // Refund before failing
            $this->refundUser('No target URL configured');
            
            $this->updateProgress('step_failed', 0, 'ERROR: No target URL - quota refunded', 'failed');
            $this->asset->update(['status' => 'failed']);
            return;
        }

        // Normalize URL
        $baseUrl = rtrim($url, '/');

        try {
            $aggregatedData = [
                'sensitive_files' => [],
                'pages_scanned' => [],
                'network_intercepts' => [],
                'vulnerability_signatures' => []
            ];

            // ---------------------------------------------------------
            // PHASE 1: RECONNAISSANCE (Sensitive File Probing & Headers)
            // ---------------------------------------------------------
            $this->updateProgress('step_probe', 10, '[PROBE] Checking Sensitive File Exposure...');

            $sensitiveFiles = ['.env', 'wp-config.php', '.git/HEAD', '.git/config', '.ds_store', 'phpinfo.php'];
            
            foreach ($sensitiveFiles as $file) {
                $target = "$baseUrl/$file";
                $this->updateProgress('step_probe', 10, "[PROBE] Checking $file...");
                
                try {
                    $response = Http::timeout(10)->get($target);
                    if ($response->successful() && strlen($response->body()) > 0) {
                        // Basic heuristic: check if it looks like the file we asked for
                        $isExposed = false;
                        if ($file === '.env' && str_contains($response->body(), 'APP_KEY')) $isExposed = true;
                        if (($file === '.git/HEAD' || $file === '.git/config') && (str_contains($response->body(), 'ref:') || str_contains($response->body(), 'repositoryformatversion'))) $isExposed = true;
                        if ($file === 'wp-config.php' && str_contains($response->body(), 'DB_NAME')) $isExposed = true;
                        if ($file === 'phpinfo.php' && str_contains(strtolower($response->body()), 'php version')) $isExposed = true;
                        
                        if ($isExposed) {
                            $aggregatedData['sensitive_files'][] = [
                                'file' => $file,
                                'status' => 'EXPOSED', 
                                'snippet' => substr($response->body(), 0, 500) // Capture first 500 chars as Critical Evidence
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore timeouts/connection errors for probes
                }
            }

            // Capture Official Headers
            try {
                $headResponse = Http::timeout(10)->head($baseUrl);
                $aggregatedData['official_headers'] = $headResponse->headers();
                $this->updateProgress('step_probe', 15, "[PROBE] Captured " . count($aggregatedData['official_headers']) . " HTTP Headers...");
            } catch (\Exception $e) {
                $aggregatedData['official_headers'] = [];
            }

            // ---------------------------------------------------------
            // PHASE 2: CRAWL & HARVEST (Homepage)
            // ---------------------------------------------------------
            $this->updateProgress('step_scan', 20, "[SCAN] Initiating Deep Crawl at $baseUrl...");

            // Browser Config - TITAN V7.6: Stealth Mode
            $browserFactory = function ($targetUrl) {
                return Browsershot::url($targetUrl)
                    ->timeout(120)
                    ->ignoreHttpsErrors()
                    // Use a more generic user agent to avoid bot detection triggers
                    ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                    ->windowSize(1920, 1080)
                    ->noSandbox()
                    ->addArgs([
                        '--disable-blink-features=AutomationControlled',
                        '--disable-infobars',
                        '--no-first-run',
                        '--disable-setuid-sandbox',
                    ])
                    ->waitUntilNetworkIdle()
                    ->delay(5000);
            };

            $homepageData = null;
            try {
                // Analyze Homepage
                $homepageData = $this->analyzePage($browserFactory, $baseUrl);
                $aggregatedData['pages_scanned'][] = $homepageData;
            } catch (\Exception $e) {
                Log::warning("Pentest: Homepage crawl failed (Headless Block?). Falling back to Partial Forensic. Error: " . $e->getMessage());
                $this->updateProgress('step_scan', 25, "[WARNING] Headless access refused. Using Partial Forensic Evidence...");
                
                // Construct minimal homepage data from Phase 1 headers
                $homepageData = [
                    'url' => $baseUrl,
                    'title' => 'Partial/Restricted Access',
                    'forms' => [],
                    'scripts' => [],
                    'internal_links' => [],
                    'dom' => ''
                ];
            }

            // ---------------------------------------------------------
            // PHASE 3: RECURSIVE CRAWL (Internal Routes)
            // ---------------------------------------------------------
            $internalLinks = $homepageData['internal_links'] ?? [];
            
            // Filter prioritized routes
            $priorityTerms = ['login', 'register', 'signin', 'signup', 'dashboard', 'admin', 'profile', 'api'];
            $targets = collect($internalLinks)
                ->filter(function ($link) use ($baseUrl) {
                    return str_starts_with($link, $baseUrl) && $link !== $baseUrl;
                })
                ->sortByDesc(function ($link) use ($priorityTerms) {
                    foreach ($priorityTerms as $term) {
                        if (str_contains(strtolower($link), $term)) return 10;
                    }
                    return 0;
                })
                ->unique()
                ->take(15); // TITAN V7.5: Increase crawl depth for better AI context

            $this->updateProgress('step_scan', 40, "[SCAN] Identified " . $targets->count() . " critical internal targets...");

            foreach ($targets as $index => $targetUrl) {
                // Ensure hydration message is shown
                $path = parse_url($targetUrl, PHP_URL_PATH);
                $this->updateProgress('step_scan', 40 + ($index * 5), "[CRAWL] Navigating to $path... [HYDRATING]");
                
                try {
                    $pageData = $this->analyzePage($browserFactory, $targetUrl);
                    $aggregatedData['pages_scanned'][] = $pageData;
                    
                    // Log Network Sniffing Stats
                    $scriptsCount = count($pageData['scripts'] ?? []);
                    $this->updateProgress('step_scan', 40 + ($index * 5) + 2, "[SNIFF] Network traffic analyzed... [$scriptsCount ASSETS FOUND]");
                    
                } catch (\Exception $e) {
                    Log::warning("Failed to analyze internal target: $targetUrl");
                }
            }

            // ---------------------------------------------------------
            // PHASE 4: ACTIVE SURFACE TESTING (Python Engine)
            // ---------------------------------------------------------
            $this->updateProgress('step_active', 70, '[ACTIVE] Executing Surface-Level Tests...');
            
            $activeResults = $this->executeSurfaceTests($baseUrl, $aggregatedData);
            $aggregatedData['active_test_results'] = $activeResults;

            // ---------------------------------------------------------
            // PHASE 5: AGGREGATION & AI ANALYSIS
            // ---------------------------------------------------------
            $this->updateProgress('step_analyze', 85, '[AI] Correlating Vectors & Penetration Testing...');

            // Get previous findings for re-scan validation
            $previousFindings = null;
            if ($this->isRescan) {
                $previousScan = $this->asset->metadata['security_audit'] ?? null;
                $previousFindings = $previousScan['vulnerabilities'] ?? [];
                
                // Filter out already-fixed items from previous scan to avoid cluttering the AI prompt
                $previousFindings = array_filter($previousFindings, function($v) {
                    return ($v['status'] ?? 'OPEN') !== 'FIXED';
                });
                
                Log::info("[RE-SCAN] Passing {count} previous findings to AI for validation", [
                    'count' => count($previousFindings)
                ]);
            }

            $scanResult = $auditor->performPentest($aggregatedData, $homepageData['dom'] ?? '', $this->customPrompt, $previousFindings);

            // ---------------------------------------------------------
            // PHASE 6: DETERMINISTIC SCORING & DIFFING
            // ---------------------------------------------------------
            
            // 1. Calculate Score Deterministically
            $vulnerabilities = $scanResult['vulnerabilities'] ?? [];
            
            if (count($vulnerabilities) > 0) {
                // Calculate from vulnerabilities if we have them
                $calcResult = $calculator->calculatePentestScore($vulnerabilities);
                $finalScore = $calcResult['score'];
                $scanResult['score_breakdown'] = $calcResult['breakdown'];
            } else {
                // Use AI's provided score (including fallback score of 75)
                $finalScore = $scanResult['security_score'] ?? 75;
                // Provide default breakdown if not already present
                if (!isset($scanResult['score_breakdown'])) {
                    $scanResult['score_breakdown'] = [
                        'injection_security' => 15,
                        'auth_privacy' => 15,
                        'access_control' => 15,
                        'configuration' => 15,
                        'data_exposure' => 15,
                        'ui_stability' => 14
                    ];
                }
            }
            
            $scanResult['security_score'] = $finalScore; // Always set this
            Log::info("DEBUG: Final score calculated", ['finalScore' => $finalScore, 'vulnerabilities_count' => count($vulnerabilities), 'scanResult_security_score' => $scanResult['security_score'] ?? 'not set']);

            // 2. Calculate Remediation Progress Scores (0-100 for each vector)
            $remediationProgress = $this->calculateRemediationProgress($vulnerabilities);
            $scanResult['remediation_progress'] = $remediationProgress;

            // CRITICAL FIX: Merge the AI analysis with the raw surface test results.
            // This ensures QAPenetrationResultsModal.vue has access to xss_tests, directory_fuzzing, etc.
            
            // Initialize metadata from existing asset data to prevent wiping other fields
            // Note: We DON'T refresh() here because this is a single long-running job.
            // Refreshing would load the DB state from BEFORE this job started, losing any
            // in-memory updates we've accumulated. For parallel jobs, see PerformProjectScan.
            $metadata = $this->asset->metadata ?? [];
            
            $metadata['security_audit'] = array_merge($scanResult, [
                'active_test_results' => $aggregatedData['active_test_results'] ?? []
            ]);
            
            $hasCritical = collect($vulnerabilities)->contains('severity', 'CRITICAL');
            
            $this->updateProgress('step_vectors', 90, '[VECTORS] Mapping Attack Surface...');

            // --- DATABASE STORAGE (For Robust History & Evidence) ---
            try {
                // store in dedicated tables
                $audit = \App\Models\SecurityAudit::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'vault_asset_id' => $this->asset->id,
                    'security_score' => $finalScore,
                    'qa_score' => $scanResult['qa_score'] ?? 0,
                    'status' => 'completed',
                    'summary' => $scanResult['executive_summary'] ?? 'Audit Complete',
                    'raw_results' => $scanResult,
                    'specific_metadata' => $this->customPrompt ? [
                        'prompt' => $this->customPrompt,
                        'ai_analysis' => $scanResult['specific_instructions_analysis'] ?? null,
                        'surface_test_output' => $aggregatedData['active_test_results']['custom_prompt_output'] ?? null
                    ] : null,
                    'audit_type' => $this->isRescan ? 'rescan' : 'pentest'
                ]);

                // SYNC TO ASSET METADATA (For UI "Sovereign Results")
                if ($this->customPrompt) {
                    $metadata['specific_metadata'] = [
                        'prompt' => $this->customPrompt,
                        'ai_analysis' => $scanResult['specific_instructions_analysis'] ?? null,
                        'surface_test_output' => $aggregatedData['active_test_results']['custom_prompt_output'] ?? null
                    ];
                }

                // Debug: Verify audit was created with correct ID
                Log::info("[AUDIT CREATED] ID: {$audit->id}, Type: " . gettype($audit->id) . ", Length: " . strlen($audit->id));

                // Store the audit ID to ensure it's properly referenced
                $auditId = $audit->id;
                if (empty($auditId) || $auditId === '0' || $auditId === 0) {
                    throw new \Exception("Audit created but ID is invalid: " . var_export($auditId, true));
                }

                foreach ($vulnerabilities as $vuln) {
                    // Normalize Status
                    $kbStatus = match($vuln['status'] ?? 'OPEN') {
                        'FIXED' => 'FIXED', 
                        'IGNORED' => 'IGNORED',
                        default => 'OPEN'
                    };

                    // Explicitly set security_audit_id instead of relying on relationship
                    \App\Models\AuditFinding::create([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'security_audit_id' => $auditId, // Explicit foreign key
                        'severity' => strtoupper($vuln['severity'] ?? 'LOW'),
                        'type' => $vuln['type'] ?? 'Unknown',
                        'location' => $vuln['location'] ?? 'Unknown',
                        'description' => $vuln['description'] ?? '',
                        'remediation' => is_array($vuln['remediation_steps'] ?? []) ? implode("\n", $vuln['remediation_steps'] ?? []) : ($vuln['remediation_steps'] ?? ''),
                        'evidence' => $vuln['evidence'] ?? null, // THIS IS THE PROOF
                        'status' => $kbStatus
                    ]);
                }
                Log::info("Security Audit Saved to Dedicated DB: {$audit->id}, Findings: " . count($vulnerabilities));
            } catch (\Exception $dbEx) {
                Log::error("Failed to save Security Audit to DB: " . $dbEx->getMessage());
                // Continue, as metadata storage is fallback
            }
            // -----------------------------------------------------

            $this->asset->update([
                'score' => $finalScore, // Explicitly save score
                'metadata' => $metadata,
                'status' => $hasCritical ? 'flagged' : ($finalScore < 85 ? 'action_required' : 'verified')
            ]);
            Log::info("DEBUG: Asset updated with score", ['asset_id' => $this->asset->id, 'score_saved' => $finalScore]);

            $this->updateProgress('step_report', 100, 'Report Generated. Security Assessment Complete.');

        } catch (\Exception $e) {
            Log::error("Security Scan Failed: " . $e->getMessage());
            
            // Refund before failing
            $this->refundUser('Scan crashed: ' . $e->getMessage());
            
            $this->updateProgress('step_failed', 0, 'CRITICAL ERROR - quota refunded: ' . $e->getMessage(), 'failed');
            $this->asset->update(['status' => 'failed']);
        }
    }

    private function updateProgress($step, $progress, $message = null, $status = null) {
        event(new \App\Events\AuditProgressUpdated(
            $this->asset,
            $message ?? $step,
            $progress,
            $status ?? 'processing'
        ));
    }

    /**
     * Call Python SurfaceTester Engine
     */
    private function executeSurfaceTests(string $baseUrl, array $aggregatedData): array
    {
        try {
            // Find local python path (prefer config, fallback to common)
            $pythonPath = config('services.python.path', 'python3');
            
            // DEFAULT ENGINE: TitanSurface (Static)
            $scriptPath = app_path('Services/Python/TitanSurface.py'); 
            $isAgentic = false;

            // DYNAMIC ENGINE: Pre-built Agentic Script Selection
            // If the user provided a custom prompt, select the appropriate pre-built scanner
            // Only do this if the prompt is substantial (> 10 chars)
            if ($this->customPrompt && strlen($this->customPrompt) > 10) {
                try {
                    Log::info("Agentic Mode: Selecting script for prompt: " . substr($this->customPrompt, 0, 80));
                    
                    $selector = new \App\Services\AI\ScriptGeneratorService();
                    $agentScript = $selector->generateScript($this->customPrompt, $baseUrl);
                    
                    if ($agentScript && file_exists($agentScript)) {
                        $scriptPath = $agentScript;
                        $isAgentic = true;
                        Log::info("Agentic Mode: Selected script: " . basename($scriptPath));
                    } else {
                         Log::warning("Agentic Mode: Failed to generate script. Falling back to TitanSurface.");
                    }
                } catch (\Exception $genEx) {
                    Log::error("Agentic Mode Exception: " . $genEx->getMessage());
                }
            }
            
            if (!file_exists($scriptPath)) {
                Log::error("Engine script missing: $scriptPath");
                return ['status' => 'error', 'message' => 'Engine missing'];
            }

            // Prepare Input
            // TitanSurface expects key 'custom_prompt' in the JSON
            // TitanAgent (new) expects args via __init__ or just runs logic
            // We'll standardise by passing the SAME JSON structure to both.
            // TitanBase-derived scripts should read this if needed, or we just pass URL as arg?
            // TitanBase currently expects "python MyScript.py <URL>" in __main__
            // TitanSurface expects "python TitanSurface.py <JSON_FILE>" structure
            
            // To support both, we need to adapt the command execution:
            $command = "";
            $tempFile = "";

            if ($isAgentic) {
                // Agentic Script: python script.py <target_url>
                $pythonDir = app_path('Services/Python');
                $stderrFile = tempnam(sys_get_temp_dir(), 'lume_stderr_');
                $command = "cd " . escapeshellarg($pythonDir) . " && $pythonPath " . escapeshellarg($scriptPath) . " " . escapeshellarg($baseUrl) . " 2>" . escapeshellarg($stderrFile);
            } else {
                // TitanSurface: python script.py <input_json_file>
                $input = json_encode([
                    'target_url' => $baseUrl,
                    'forms' => $aggregatedData['pages_scanned'][0]['forms'] ?? [],
                    'custom_prompt' => $this->customPrompt 
                ]);
                $tempFile = tempnam(sys_get_temp_dir(), 'lume_scan_');
                file_put_contents($tempFile, $input);
                $stderrFile = tempnam(sys_get_temp_dir(), 'lume_stderr_');
                $command = "$pythonPath " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFile) . " 2>" . escapeshellarg($stderrFile);
            }

            // Execute
            $output = shell_exec($command);
            
            // Capture stderr for debugging
            $stderr = '';
            if (!empty($stderrFile) && file_exists($stderrFile)) {
                $stderr = file_get_contents($stderrFile);
                unlink($stderrFile);
                if (!empty(trim($stderr))) {
                    Log::warning("Python stderr output", ['stderr' => substr($stderr, 0, 500)]);
                }
            }
            
            // Cleanup
            if (!empty($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            // NOTE: Pre-built agentic scripts are NOT deleted (they are permanent files)

            Log::info("Script stdout output", ['output' => substr($output ?? '', 0, 500)]);

            // Attempt to extract JSON from output
            $results = null;
            if (!empty($output)) {
                $jsonStart = strpos($output, '{');
                $jsonEnd = strrpos($output, '}');
                
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonString = substr($output, $jsonStart, ($jsonEnd - $jsonStart) + 1);
                    $results = json_decode($jsonString, true);
                }
                
                if (!$results) {
                    // Try array JSON
                    $arrayStart = strpos($output, '[');
                    $arrayEnd = strrpos($output, ']');
                    if ($arrayStart !== false && $arrayEnd !== false) {
                        $jsonString = substr($output, $arrayStart, ($arrayEnd - $arrayStart) + 1);
                        $results = json_decode($jsonString, true);
                    }
                }
            }

            // FALLBACK: Parse text-based [FINDING] output from misbehaving agents
            if (!$results && $isAgentic && !empty($output)) {
                Log::info("Agentic: JSON parse failed. Attempting text-based fallback parse.");
                $results = $this->parseTextFindings($output);
            }

            if (!$results) {
                Log::warning("Surface tests output could not be parsed.", [
                    'stdout' => substr($output ?? '', 0, 500),
                    'stderr' => substr($stderr, 0, 500)
                ]);
                return ['status' => 'error', 'message' => 'Parsing failed', 'raw_output' => $output ?? $stderr];
            }
            
            // If Agentic, we need to map 'findings' to the structure expected by performPentest
            // TitanBase returns { findings: [], logs: [] }
            // TitanSurface returns { active_test_results: ..., custom_prompt_output: ... }
            if ($isAgentic) {
                // Map Agent findings to 'custom_prompt_output' so it shows up in UI
                $mappedResults = [
                   'status' => 'completed',
                   'agent_logs' => $results['logs'] ?? [],
                   'custom_prompt_output' => []
                ];
                
                foreach ($results['findings'] ?? [] as $finding) {
                     $mappedResults['custom_prompt_output'][] = [
                         'check' => $finding['title'] ?? 'Agent Check',
                         'status' => str_contains(strtoupper($finding['severity'] ?? ''), 'INFO') ? 'SECURE' : 'VULNERABLE',
                         'details' => ($finding['description'] ?? '') . " - " . ($finding['details'] ?? ''),
                         'severity' => $finding['severity'] ?? 'MEDIUM'
                     ];
                }
                return $mappedResults;
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("Python Execution Failed: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function analyzePage($browserFactory, $url) {
        $browser = $browserFactory($url);
        
        $evaluation = $browser->evaluate(<<<JS
            (() => {
                // 1. Harvest Forms (Injection Vectors)
                const forms = Array.from(document.forms).map(f => ({
                    action: f.action,
                    method: f.method,
                    inputs: Array.from(f.elements).map(i => ({
                        name: i.name,
                        type: i.type,
                        id: i.id
                    }))
                }));

                // 2. Cookies
                const cookies = document.cookie.split(';').map(c => c.trim());

                // 3. LocalStorage Keys
                const storage = Object.keys({...localStorage});

                // 4. Scripts
                const scripts = Array.from(document.scripts)
                    .map(s => s.src)
                    .filter(s => s);

                // 5. Internal Links
                const links = Array.from(document.links)
                    .map(l => l.href)
                    .filter(h => h.startsWith(window.location.origin));

                return JSON.stringify({
                    url: window.location.href,
                    title: document.title,
                    forms: forms,
                    cookies: cookies,
                    storage_keys: storage,
                    scripts: scripts,
                    internal_links: links
                });
            })()
        JS);

        $data = json_decode($evaluation, true);
        $data['dom'] = $browser->bodyHtml(); // Capture raw DOM
        
        return $data;
    }
    /**
     * Handle job failure - refund quota/credits automatically.
     * Called by Laravel queue when job fails.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("PerformSecurityScan Job Failed - Refunding quota/credits", [
            'asset_id' => $this->asset->id,
            'user_id' => $this->userId,
            'audit_type' => $this->auditType,
            'error' => $exception->getMessage()
        ]);
        
        $this->refundUser('Job failed: ' . $exception->getMessage());
        $this->updateProgress('step_failed', 0, 'Scan failed - quota refunded', 'failed');
        $this->asset->update(['status' => 'failed']);
    }
    
    /**
     * Refund quota/credits to user when scan fails.
     */
    private function refundUser(string $reason): void
    {
        try {
            $ledgerService = app(\App\Services\LedgerService::class);
            $user = \App\Models\User::find($this->userId);
            
            if ($user) {
                $assetName = $this->asset->file_name ?? 'Asset';
                $amount = \App\Services\LedgerService::getAuditFee($this->auditType);
                $ledgerService->refundAuditCredits($user, $this->auditType, $assetName, $amount);
                
                Log::info("Refunded quota/credits to user {$user->id} for {$this->auditType} scan failure: {$reason}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to refund user: " . $e->getMessage());
        }
    }

    /**
     * Calculate remediation progress scores (0-100) for each attack vector.
     * Higher scores = better security posture.
     */
    private function calculateRemediationProgress(array $vulnerabilities): array
    {
        // Vector mapping - which vulnerability types belong to which remediation category
        $vectorMapping = [
            'injection' => ['xss', 'sqli', 'injection', 'script', 'csp', 'content-security'],
            'auth' => ['auth', 'session', 'csrf', 'token', 'login', 'password', 'credential', 'mapbox'],
            'privacy' => ['hsts', 'ssl', 'tls', 'header', 'x-frame', 'x-content', 'referrer', 'permissions', 'powered-by', 'transport-security'],
            'access' => ['admin', 'access', 'control', 'rbac', 'authorization', 'endpoint', 'directory'],
            'ui_stability' => ['console', 'error', 'stability', 'ui', 'ux', 'performance']
        ];

        // Initialize scores at 100 (perfect)
        $scores = [
            'injection' => 100,
            'auth' => 100,
            'privacy' => 100,
            'access' => 100,
            'ui_stability' => 100
        ];

        // Count vulnerabilities per vector with severity weighting
        $vectorCounts = array_fill_keys(array_keys($vectorMapping), 0);

        foreach ($vulnerabilities as $vuln) {
            $severity = strtoupper($vuln['severity'] ?? 'LOW');
            if ($severity === 'FIXED') continue; // Skip fixed vulns

            $type = strtolower($vuln['type'] ?? '');
            $location = strtolower($vuln['location'] ?? '');
            $combined = "$type $location";

            // Severity impact weights
            $weight = match($severity) {
                'CRITICAL' => 40,
                'HIGH' => 25,
                'MEDIUM' => 15,
                'LOW' => 5,
                default => 5
            };

            // Map to vectors
            foreach ($vectorMapping as $vector => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($combined, $keyword)) {
                        $vectorCounts[$vector] += $weight;
                        break 2; // Found a match, move to next vulnerability
                    }
                }
            }
        }

        // Calculate final scores (inverse of vulnerability impact)
        foreach ($vectorCounts as $vector => $impactScore) {
            if ($impactScore > 0) {
                // Cap max deduction at 100 points
                $scores[$vector] = max(0, 100 - min(100, $impactScore));
            }
        }

        Log::info("[REMEDIATION PROGRESS] Calculated scores", $scores);
        return $scores;
    }

    /**
     * Fallback parser for text-based agent output.
     * Extracts [FINDING] lines when the agent fails to produce JSON.
     */
    private function parseTextFindings(string $output): ?array
    {
        $findings = [];
        $logs = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parse [FINDING] Severity: Title...
            if (preg_match('/\[FINDING\]\s*(Critical|High|Medium|Low|Info):\s*(.+)/i', $line, $m)) {
                $findings[] = [
                    'title' => trim($m[2]),
                    'severity' => strtoupper(trim($m[1])),
                    'description' => trim($m[2]),
                    'details' => '',
                    'location' => 'Agent Text Output'
                ];
            }
            // Parse [LOG] entries
            elseif (preg_match('/\[LOG\]\s*(.+)/i', $line, $m)) {
                $logs[] = trim($m[1]);
            }
            // Continuation lines: append to last finding's details
            elseif (!empty($findings) && (str_starts_with($line, 'Risk:') || str_starts_with($line, 'Fix:'))) {
                $lastIdx = count($findings) - 1;
                $findings[$lastIdx]['details'] .= " " . $line;
            }
        }

        if (empty($findings)) {
            return null; // Could not parse anything useful
        }

        Log::info("Text fallback parser extracted " . count($findings) . " findings");

        return [
            'findings' => $findings,
            'logs' => $logs,
            'meta' => ['parser' => 'text_fallback']
        ];
    }
}