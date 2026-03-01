<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class ScriptGeneratorService
{
    /**
     * Map of scan categories to their pre-built Python scripts.
     */
    private const SCRIPT_MAP = [
        'headers'   => 'TitanHeaders.py',
        'leaks'     => 'TitanLeaks.py',
        'sqli'      => 'TitanSQLi.py',
        'xss'       => 'TitanXSS.py',
        'dirfuzz'   => 'TitanDirFuzz.py',
        'auth'      => 'TitanAuth.py',
        'idor'      => 'TitanIDOR.py',
        'full'      => 'TitanFullScan.py',
    ];

    /**
     * Keyword patterns that map user prompts to scan types.
     * Checked in order — first match wins.
     */
    private const KEYWORD_MAP = [
        'sqli' => [
            'sql injection', 'sqli', 'sql inject', 'database injection', 'parameterized',
            'sql query', 'sql attack', 'bobby tables', 'union select', 'orm injection',
            'query injection',
        ],
        'xss' => [
            'xss', 'cross-site scripting', 'cross site scripting', 'script injection',
            'reflected xss', 'dom xss', 'stored xss', 'javascript injection',
            'html injection', 'content injection',
        ],
        'leaks' => [
            'sensitive data', 'data leak', 'data exposure', 'api key', 'credential',
            'password exposure', '.env', 'secret', 'leaked', 'exposed', 'pii',
            'private key', 'token leak', 'information disclosure', 'hardcoded',
        ],
        'headers' => [
            'header', 'security header', 'csp', 'content-security-policy', 'hsts',
            'x-frame', 'clickjacking', 'mime sniffing', 'referrer policy',
            'strict-transport', 'http header', 'cors',
        ],
        'dirfuzz' => [
            'directory', 'dir scan', 'path discovery', 'hidden file', 'backup file',
            'admin panel', 'admin page', 'hidden endpoint', 'file discovery',
            'brute force directory', 'fuzzing', 'wordlist', 'enumeration',
            'robots.txt', 'sitemap',
        ],
        'auth' => [
            'authentication', 'login security', 'brute force', 'session',
            'cookie security', 'csrf', 'password', 'auth bypass',
            'session hijack', 'account takeover', 'credential stuffing',
            'rate limit', 'lockout',
        ],
        'idor' => [
            'idor', 'insecure direct object', 'access control', 'authorization',
            'privilege escalation', 'horizontal escalation', 'object reference',
            'user enumeration', 'resource access',
        ],
    ];

    /**
     * Determine which script(s) to run based on the user's prompt.
     * Returns an array of absolute script paths.
     */
    public function selectScripts(string $userPrompt): array
    {
        $prompt = strtolower($userPrompt);
        $matched = [];

        // Check keyword matches
        foreach (self::KEYWORD_MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($prompt, $keyword)) {
                    $matched[$category] = true;
                    break;
                }
            }
        }

        // If nothing matched, or the prompt is very broad, run the full scan
        $broadIndicators = [
            'full scan', 'comprehensive', 'everything', 'all vulnerabilities',
            'complete audit', 'full assessment', 'full test', 'pentest',
            'penetration test', 'penetration testing', 'security audit',
            'security assessment', 'all checks', 'deep scan', 'high-intensity',
            'vulnerability assessment', 'vulnerability scan', 'full vulnerability',
            'audit', 'analyze', 'analyse', 'test everything', 'check everything',
        ];

        $isBroad = false;
        foreach ($broadIndicators as $indicator) {
            if (str_contains($prompt, $indicator)) {
                $isBroad = true;
                break;
            }
        }

        if (empty($matched) || $isBroad) {
            $matched = ['full' => true];
        }

        // Convert to script paths
        $pythonDir = app_path('Services/Python');
        $scripts = [];
        
        foreach (array_keys($matched) as $category) {
            $scriptFile = self::SCRIPT_MAP[$category] ?? null;
            if ($scriptFile) {
                $fullPath = $pythonDir . '/' . $scriptFile;
                if (file_exists($fullPath)) {
                    $scripts[] = $fullPath;
                } else {
                    Log::warning("Agentic script not found: {$fullPath}");
                }
            }
        }

        // Fallback to full scan if nothing resolved
        if (empty($scripts)) {
            $fullPath = $pythonDir . '/TitanFullScan.py';
            if (file_exists($fullPath)) {
                $scripts[] = $fullPath;
            }
        }

        Log::info("Agentic Script Selection", [
            'prompt_preview' => substr($userPrompt, 0, 100),
            'matched_categories' => array_keys($matched),
            'scripts' => array_map('basename', $scripts),
        ]);

        return $scripts;
    }

    /**
     * Legacy method — kept for backward compatibility.
     * Now just selects the first matching pre-built script.
     */
    public function generateScript(string $userPrompt, string $targetUrl): ?string
    {
        $scripts = $this->selectScripts($userPrompt);
        return $scripts[0] ?? null;
    }
}
