<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CredentialValidationService
{
    /**
     * Validate GitHub Personal Access Token.
     * Performs a dry-run to check token validity and permissions.
     *
     * @param string $token The GitHub PAT
     * @param string|null $requiredRepo Optional repo to check access for
     * @return array{valid: bool, username: string|null, scopes: array, has_repo_access: bool, error: string|null, error_type: string|null}
     */
    public function validateGitHubToken(string $token, ?string $requiredRepo = null): array
    {
        try {
            // Check token validity
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LUME-Platform',
                ])
                ->withToken($token)
                ->get('https://api.github.com/user');

            if ($response->status() === 401) {
                return [
                    'valid' => false,
                    'username' => null,
                    'scopes' => [],
                    'has_repo_access' => false,
                    'error' => 'Invalid token: Authentication failed',
                    'error_type' => 'auth_failed',
                ];
            }

            if ($response->failed()) {
                return [
                    'valid' => false,
                    'username' => null,
                    'scopes' => [],
                    'has_repo_access' => false,
                    'error' => 'GitHub API error: HTTP ' . $response->status(),
                    'error_type' => 'api_error',
                ];
            }

            $userData = $response->json();
            $username = $userData['login'] ?? null;

            // Check scopes from response headers
            $scopesHeader = $response->header('X-OAuth-Scopes') ?? '';
            $scopes = array_map('trim', explode(',', $scopesHeader));
            $scopes = array_filter($scopes);

            // Check required permissions
            $hasRepoScope = in_array('repo', $scopes) || in_array('public_repo', $scopes);
            $hasAdminScope = in_array('admin:org', $scopes) || in_array('delete_repo', $scopes);

            // If repo access is required, check specific repo
            $hasRepoAccess = $hasRepoScope;
            if ($requiredRepo && $hasRepoScope) {
                $repoCheck = $this->checkRepoAccess($token, $requiredRepo);
                $hasRepoAccess = $repoCheck['has_access'];
            }

            // Build error message if permissions are missing
            $error = null;
            $errorType = null;
            
            if (!$hasRepoScope) {
                $error = 'Token missing "repo" scope. Please generate a new token with repository access.';
                $errorType = 'missing_scope_repo';
            } elseif ($requiredRepo && !$hasRepoAccess) {
                $error = 'Token does not have access to the specified repository.';
                $errorType = 'no_repo_access';
            }

            return [
                'valid' => true,
                'username' => $username,
                'scopes' => $scopes,
                'has_repo_access' => $hasRepoAccess,
                'can_transfer' => $hasAdminScope || $hasRepoScope,
                'error' => $error,
                'error_type' => $errorType,
            ];
        } catch (\Exception $e) {
            Log::error('GitHub token validation failed', ['error' => $e->getMessage()]);
            
            return [
                'valid' => false,
                'username' => null,
                'scopes' => [],
                'has_repo_access' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => 'connection_error',
            ];
        }
    }

    /**
     * Check if token has access to a specific repository.
     */
    private function checkRepoAccess(string $token, string $repoUrl): array
    {
        try {
            preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $repoUrl, $matches);
            
            if (count($matches) < 3) {
                return ['has_access' => false, 'error' => 'Invalid repo URL'];
            }

            $owner = $matches[1];
            $repo = rtrim($matches[2], '.git');

            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'LUME-Platform',
                ])
                ->withToken($token)
                ->get("https://api.github.com/repos/{$owner}/{$repo}");

            return [
                'has_access' => $response->successful(),
                'error' => $response->failed() ? 'Cannot access repository' : null,
            ];
        } catch (\Exception $e) {
            return ['has_access' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate Cloudflare API Key/Token.
     * Performs a dry-run to check validity.
     *
     * @param string $apiKey The Cloudflare API key or token
     * @param string|null $email Optional email (required for Global API Key)
     * @param string|null $zoneId Optional Zone ID to check access
     * @return array{valid: bool, type: string|null, account_name: string|null, zones: array, error: string|null, error_type: string|null}
     */
    public function validateCloudflareCredentials(string $apiKey, ?string $email = null, ?string $zoneId = null): array
    {
        try {
            // Determine if this is an API Token or Global API Key
            $isApiToken = str_starts_with($apiKey, 'Bearer ') || strlen($apiKey) > 40;
            
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($isApiToken) {
                // API Token format
                $token = str_replace('Bearer ', '', $apiKey);
                $headers['Authorization'] = 'Bearer ' . $token;
            } else {
                // Global API Key format (requires email)
                if (!$email) {
                    return [
                        'valid' => false,
                        'type' => 'global_key',
                        'account_name' => null,
                        'zones' => [],
                        'error' => 'Email is required for Global API Key authentication',
                        'error_type' => 'missing_email',
                    ];
                }
                $headers['X-Auth-Email'] = $email;
                $headers['X-Auth-Key'] = $apiKey;
            }

            // Verify token
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get('https://api.cloudflare.com/client/v4/user/tokens/verify');

            // If token verification fails, try user endpoint
            if ($response->failed() || !($response->json()['success'] ?? false)) {
                $response = Http::timeout(10)
                    ->withHeaders($headers)
                    ->get('https://api.cloudflare.com/client/v4/user');
            }

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                $errorMsg = $data['errors'][0]['message'] ?? 'Authentication failed';
                $errorCode = $data['errors'][0]['code'] ?? 0;
                
                return [
                    'valid' => false,
                    'type' => $isApiToken ? 'api_token' : 'global_key',
                    'account_name' => null,
                    'zones' => [],
                    'error' => $errorMsg,
                    'error_type' => $errorCode == 10000 ? 'invalid_token' : 'auth_failed',
                ];
            }

            // Get account info
            $accountName = $data['result']['email'] ?? $data['result']['id'] ?? 'Unknown';

            // List zones if access exists
            $zones = [];
            $zonesResponse = Http::timeout(10)
                ->withHeaders($headers)
                ->get('https://api.cloudflare.com/client/v4/zones', [
                    'per_page' => 10,
                ]);

            if ($zonesResponse->successful() && ($zonesResponse->json()['success'] ?? false)) {
                $zones = collect($zonesResponse->json()['result'] ?? [])
                    ->map(fn($z) => [
                        'id' => $z['id'],
                        'name' => $z['name'],
                        'status' => $z['status'],
                    ])
                    ->toArray();
            }

            // Check specific zone access if provided
            $zoneError = null;
            if ($zoneId) {
                $zoneCheck = Http::timeout(10)
                    ->withHeaders($headers)
                    ->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}");
                
                if ($zoneCheck->failed() || !($zoneCheck->json()['success'] ?? false)) {
                    $zoneError = 'Cannot access specified zone';
                }
            }

            return [
                'valid' => true,
                'type' => $isApiToken ? 'api_token' : 'global_key',
                'account_name' => $accountName,
                'zones' => $zones,
                'error' => $zoneError,
                'error_type' => $zoneError ? 'zone_access_denied' : null,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudflare validation failed', ['error' => $e->getMessage()]);
            
            return [
                'valid' => false,
                'type' => null,
                'account_name' => null,
                'zones' => [],
                'error' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => 'connection_error',
            ];
        }
    }

    /**
     * Perform a full dry-run validation of all credentials for a project listing.
     *
     * @param array $credentials {github_token: string, cloudflare_key: string|null, cloudflare_email: string|null, repo_url: string}
     * @return array{success: bool, github: array, cloudflare: array|null, errors: array}
     */
    public function dryRunValidation(array $credentials): array
    {
        $errors = [];
        
        // Validate GitHub Token
        $githubResult = $this->validateGitHubToken(
            $credentials['github_token'] ?? '',
            $credentials['repo_url'] ?? null
        );
        
        if (!$githubResult['valid'] || $githubResult['error']) {
            $errors['github'] = $githubResult['error'] ?? 'GitHub validation failed';
        }

        // Validate Cloudflare if provided
        $cloudflareResult = null;
        if (!empty($credentials['cloudflare_key'])) {
            $cloudflareResult = $this->validateCloudflareCredentials(
                $credentials['cloudflare_key'],
                $credentials['cloudflare_email'] ?? null
            );
            
            if (!$cloudflareResult['valid'] || $cloudflareResult['error']) {
                $errors['cloudflare'] = $cloudflareResult['error'] ?? 'Cloudflare validation failed';
            }
        }

        return [
            'success' => empty($errors),
            'github' => $githubResult,
            'cloudflare' => $cloudflareResult,
            'errors' => $errors,
        ];
    }
}
