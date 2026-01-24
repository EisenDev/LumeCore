<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HealthCheckService
{
    /**
     * Check if a website URL is alive and responding.
     *
     * @param string $url The website URL to check
     * @return array{alive: bool, status_code: int|null, response_time_ms: int|null, ssl_valid: bool|null, error: string|null}
     */
    public function checkWebsite(string $url): array
    {
        $startTime = microtime(true);
        
        try {
            // Ensure URL has protocol
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . $url;
            }

            $response = Http::timeout(10)
                ->withOptions([
                    'verify' => true,
                    'allow_redirects' => true,
                ])
                ->get($url);

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'alive' => $response->successful() || $response->redirect(),
                'status_code' => $response->status(),
                'response_time_ms' => $responseTime,
                'ssl_valid' => true,
                'error' => null,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning("HealthCheck: Connection failed for {$url}", ['error' => $e->getMessage()]);
            
            return [
                'alive' => false,
                'status_code' => null,
                'response_time_ms' => null,
                'ssl_valid' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'alive' => false,
                'status_code' => null,
                'response_time_ms' => null,
                'ssl_valid' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check domain WHOIS information including expiration date.
     * Uses whoisjson.com API (free tier available).
     *
     * @param string $domain The domain to check (e.g., 'example.com')
     * @return array{registered: bool, expiration_date: string|null, registrar: string|null, days_until_expiry: int|null, error: string|null}
     */
    public function checkDomainWhois(string $domain): array
    {
        try {
            // Extract domain from URL if needed
            $domain = $this->extractDomain($domain);
            
            // Use whoisjson.com API (or similar)
            $response = Http::timeout(15)
                ->get("https://whoisjson.com/api/v1/whois", [
                    'domain' => $domain,
                ]);

            if ($response->failed()) {
                // Fallback: Just check if domain resolves
                $ip = gethostbyname($domain);
                
                return [
                    'registered' => $ip !== $domain,
                    'expiration_date' => null,
                    'registrar' => null,
                    'days_until_expiry' => null,
                    'error' => 'WHOIS lookup unavailable, DNS check only',
                ];
            }

            $data = $response->json();
            
            $expirationDate = $data['expires'] ?? $data['expiration_date'] ?? null;
            $daysUntilExpiry = null;
            
            if ($expirationDate) {
                try {
                    $expiry = new \DateTime($expirationDate);
                    $now = new \DateTime();
                    $daysUntilExpiry = $now->diff($expiry)->days;
                    if ($expiry < $now) {
                        $daysUntilExpiry = -$daysUntilExpiry;
                    }
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }

            return [
                'registered' => !empty($data['domain']),
                'expiration_date' => $expirationDate,
                'registrar' => $data['registrar'] ?? null,
                'days_until_expiry' => $daysUntilExpiry,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::warning("HealthCheck: WHOIS failed for {$domain}", ['error' => $e->getMessage()]);
            
            return [
                'registered' => null,
                'expiration_date' => null,
                'registrar' => null,
                'days_until_expiry' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a GitHub repository exists and is accessible.
     *
     * @param string $repoUrl The GitHub repository URL
     * @param string|null $token Optional GitHub Personal Access Token
     * @return array{exists: bool, private: bool|null, owner: string|null, repo: string|null, stars: int|null, last_push: string|null, error: string|null}
     */
    public function checkGitHubRepo(string $repoUrl, ?string $token = null): array
    {
        try {
            // Parse GitHub URL
            preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $repoUrl, $matches);
            
            if (count($matches) < 3) {
                return [
                    'exists' => false,
                    'private' => null,
                    'owner' => null,
                    'repo' => null,
                    'stars' => null,
                    'last_push' => null,
                    'error' => 'Invalid GitHub URL format',
                ];
            }

            $owner = $matches[1];
            $repo = rtrim($matches[2], '.git');

            $request = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LUME-Platform',
                ]);

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->get("https://api.github.com/repos/{$owner}/{$repo}");

            if ($response->status() === 404) {
                return [
                    'exists' => false,
                    'private' => null,
                    'owner' => $owner,
                    'repo' => $repo,
                    'stars' => null,
                    'last_push' => null,
                    'error' => 'Repository not found or private (token may be required)',
                ];
            }

            if ($response->failed()) {
                return [
                    'exists' => false,
                    'private' => null,
                    'owner' => $owner,
                    'repo' => $repo,
                    'stars' => null,
                    'last_push' => null,
                    'error' => 'GitHub API error: ' . $response->status(),
                ];
            }

            $data = $response->json();

            return [
                'exists' => true,
                'private' => $data['private'] ?? false,
                'owner' => $data['owner']['login'] ?? $owner,
                'repo' => $data['name'] ?? $repo,
                'stars' => $data['stargazers_count'] ?? 0,
                'last_push' => $data['pushed_at'] ?? null,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'exists' => false,
                'private' => null,
                'owner' => null,
                'repo' => null,
                'stars' => null,
                'last_push' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a unique verification UUID for domain ownership verification.
     *
     * @return string The UUID to be placed in meta tag
     */
    public function generateVerificationUuid(): string
    {
        return 'lume-' . Str::uuid()->toString();
    }

    /**
     * Verify that a website has the LUME verification meta tag.
     *
     * @param string $url The website URL
     * @param string $expectedUuid The expected verification UUID
     * @return array{verified: bool, found_value: string|null, error: string|null}
     */
    public function verifySiteOwnership(string $url, string $expectedUuid): array
    {
        try {
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . $url;
            }

            $response = Http::timeout(10)->get($url);

            if ($response->failed()) {
                return [
                    'verified' => false,
                    'found_value' => null,
                    'error' => 'Could not fetch website: HTTP ' . $response->status(),
                ];
            }

            $html = $response->body();

            // Look for <meta name="lume-verification" content="...">
            preg_match('/<meta\s+name=["\']lume-verification["\']\s+content=["\']([^"\']+)["\']/', $html, $matches);
            
            if (empty($matches)) {
                // Try alternate order
                preg_match('/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']lume-verification["\']/', $html, $matches);
            }

            $foundValue = $matches[1] ?? null;

            return [
                'verified' => $foundValue === $expectedUuid,
                'found_value' => $foundValue,
                'error' => $foundValue ? null : 'Verification meta tag not found',
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'found_value' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract domain from URL.
     */
    private function extractDomain(string $input): string
    {
        // Remove protocol
        $input = preg_replace('/^https?:\/\//', '', $input);
        // Remove www
        $input = preg_replace('/^www\./', '', $input);
        // Remove path
        $input = explode('/', $input)[0];
        // Remove port
        $input = explode(':', $input)[0];
        
        return $input;
    }
}
