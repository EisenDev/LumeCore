<?php

namespace App\Services;

use App\Models\ProjectAsset;
use App\Services\AI\DocumentAuditor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class InfrastructureScannerService
{
    /**
     * Known tech stack patterns for identification.
     */
    protected array $techPatterns = [
        // PHP Frameworks
        'laravel/framework' => ['name' => 'Laravel', 'category' => 'Backend Framework', 'icon' => '🔴'],
        'symfony/symfony' => ['name' => 'Symfony', 'category' => 'Backend Framework', 'icon' => '⚫'],
        'slim/slim' => ['name' => 'Slim', 'category' => 'Backend Framework', 'icon' => '🟢'],
        'yiisoft/yii2' => ['name' => 'Yii2', 'category' => 'Backend Framework', 'icon' => '🔵'],
        
        // PHP Packages
        'stripe/stripe-php' => ['name' => 'Stripe', 'category' => 'Payments', 'icon' => '💳'],
        'paypal/paypal-checkout-sdk' => ['name' => 'PayPal', 'category' => 'Payments', 'icon' => '💳'],
        'braintree/braintree_php' => ['name' => 'Braintree', 'category' => 'Payments', 'icon' => '💳'],
        'aws/aws-sdk-php' => ['name' => 'AWS SDK', 'category' => 'Cloud Services', 'icon' => '☁️'],
        'google/cloud' => ['name' => 'Google Cloud', 'category' => 'Cloud Services', 'icon' => '☁️'],
        'twilio/sdk' => ['name' => 'Twilio', 'category' => 'Communications', 'icon' => '📱'],
        'sendgrid/sendgrid' => ['name' => 'SendGrid', 'category' => 'Email', 'icon' => '📧'],
        'mailchimp/marketing' => ['name' => 'Mailchimp', 'category' => 'Email Marketing', 'icon' => '📧'],
        'pusher/pusher-php-server' => ['name' => 'Pusher', 'category' => 'Real-time', 'icon' => '⚡'],
        'predis/predis' => ['name' => 'Redis', 'category' => 'Cache/Database', 'icon' => '🔴'],
        'mongodb/mongodb' => ['name' => 'MongoDB', 'category' => 'Database', 'icon' => '🍃'],
        'elasticsearch/elasticsearch' => ['name' => 'Elasticsearch', 'category' => 'Search', 'icon' => '🔍'],
        'algolia/algoliasearch-client-php' => ['name' => 'Algolia', 'category' => 'Search', 'icon' => '🔍'],
        'sentry/sentry-laravel' => ['name' => 'Sentry', 'category' => 'Error Tracking', 'icon' => '🐛'],
        'bugsnag/bugsnag-laravel' => ['name' => 'Bugsnag', 'category' => 'Error Tracking', 'icon' => '🐛'],
        
        // JS Frameworks
        'react' => ['name' => 'React', 'category' => 'Frontend Framework', 'icon' => '⚛️'],
        'vue' => ['name' => 'Vue.js', 'category' => 'Frontend Framework', 'icon' => '💚'],
        'next' => ['name' => 'Next.js', 'category' => 'Frontend Framework', 'icon' => '▲'],
        'nuxt' => ['name' => 'Nuxt', 'category' => 'Frontend Framework', 'icon' => '💚'],
        'svelte' => ['name' => 'Svelte', 'category' => 'Frontend Framework', 'icon' => '🧡'],
        '@angular/core' => ['name' => 'Angular', 'category' => 'Frontend Framework', 'icon' => '🅰️'],
        'tailwindcss' => ['name' => 'Tailwind CSS', 'category' => 'CSS Framework', 'icon' => '🎨'],
        'bootstrap' => ['name' => 'Bootstrap', 'category' => 'CSS Framework', 'icon' => '🅱️'],
        
        // JS Services
        '@stripe/stripe-js' => ['name' => 'Stripe.js', 'category' => 'Payments', 'icon' => '💳'],
        'firebase' => ['name' => 'Firebase', 'category' => 'Backend-as-Service', 'icon' => '🔥'],
        '@supabase/supabase-js' => ['name' => 'Supabase', 'category' => 'Backend-as-Service', 'icon' => '⚡'],
        '@prisma/client' => ['name' => 'Prisma', 'category' => 'ORM', 'icon' => '🔷'],
        '@sentry/browser' => ['name' => 'Sentry', 'category' => 'Error Tracking', 'icon' => '🐛'],
        'axios' => ['name' => 'Axios', 'category' => 'HTTP Client', 'icon' => '🌐'],
    ];

    /**
     * Known DNS providers by nameserver patterns.
     */
    protected array $dnsProviders = [
        'cloudflare' => ['pattern' => '/\.ns\.cloudflare\.com$/i', 'name' => 'Cloudflare', 'icon' => '🔶'],
        'route53' => ['pattern' => '/\.awsdns-/i', 'name' => 'AWS Route 53', 'icon' => '☁️'],
        'google' => ['pattern' => '/\.googledomains\.com$/i', 'name' => 'Google Domains', 'icon' => '🔵'],
        'godaddy' => ['pattern' => '/\.domaincontrol\.com$/i', 'name' => 'GoDaddy', 'icon' => '🟢'],
        'namecheap' => ['pattern' => '/\.registrar-servers\.com$/i', 'name' => 'Namecheap', 'icon' => '🔴'],
        'digitalocean' => ['pattern' => '/\.digitalocean\.com$/i', 'name' => 'DigitalOcean', 'icon' => '🔵'],
        'netlify' => ['pattern' => '/\.netlify\.com$/i', 'name' => 'Netlify', 'icon' => '🌐'],
        'vercel' => ['pattern' => '/\.vercel-dns\.com$/i', 'name' => 'Vercel', 'icon' => '▲'],
    ];

    public function __construct(
        private DocumentAuditor $aiAuditor,
        private HealthCheckService $healthCheck
    ) {}

    /**
     * Perform a full infrastructure scan on a project.
     */
    public function scanProject(ProjectAsset $project, ?string $token = null, ?callable $onProgress = null): array
    {
        $results = [
            'tech_footprint' => [],
            'dns_provider' => null,
            'env_variables' => [],
            'config_files' => [],
            'dependency_files' => [],
            'ssl_forensics' => [],
            'strategic_context' => null,
            'scan_timestamp' => now()->toIso8601String(),
        ];

        // 1. REPO SCAN (The DNA)
        if ($project->github_repo_url) {
            if ($onProgress) $onProgress("Scanning Code Repository: {$project->github_repo_url}", 26);
            $authToken = $token ?? $project->getGithubToken();
            $results['tech_footprint'] = $this->scanTechStack($project->github_repo_url, $authToken);
            
            if ($onProgress) $onProgress("Analyzing Configuration Files...", 30);
            $results['config_files'] = $this->scanConfigFiles($project->github_repo_url, $authToken);
            $results['env_variables'] = $this->analyzeEnvRequirements($results['config_files'], $results['tech_footprint']);

            // Harvest Raw Truth Files
            preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $project->github_repo_url, $matches);
            if (count($matches) >= 3) {
                $owner = $matches[1];
                $owner = $matches[1];
                // FIX: rtrim removes characters from the mask, potentially corrupting repo names ending in g, i, or t.
                $repo = preg_replace('/\.git$/', '', $matches[2]);
                $filesToHunt = ['composer.json', 'package.json', 'go.mod', 'requirements.txt', 'Dockerfile', 'docker-compose.yml', 'Gemfile'];
                
                foreach ($filesToHunt as $file) {
                    $content = $this->fetchGitHubFile($owner, $repo, $file, $authToken);
                    if ($content) {
                        $results['dependency_files'][$file] = $content;
                        // Manual Docker Check (if not caught by Regex)
                        if (str_contains($file, 'docker') || str_contains($file, 'Dockerfile')) {
                             $results['tech_footprint'][] = ['name' => 'Docker', 'category' => 'Infrastructure', 'icon' => '🐳'];
                        }
                    }
                }
            }
        }

        // 2. WEB SCAN (The Eyes)
        if ($project->website_url) {
            if ($onProgress) $onProgress("Verifying Live Deployment...", 35);
            // A. Basic Health & DNS
            $results['dns_provider'] = $this->scanDnsProvider($project->website_url);
            $results['live_verification'] = $this->verifyLiveStatus($project->website_url);
            
            // B. Titan Hard Truths (SSL)
            if ($onProgress) $onProgress("Auditing SSL Certificates...", 38);
            $results['ssl_forensics'] = $this->getSSLForensics($project->website_url);
            
            // C. Browser Forensics (Headers, DB Sniffing, Deep Context)
            if ($onProgress) $onProgress("Initializing Deep Browser Scan...", 40);
            $browserData = $this->captureBrowserForensics($project->website_url, $onProgress);
            $results['strategic_context'] = $browserData['strategic_context'] ?? null;
            
            // Merge discovered DBs (e.g. Supabase) into Tech Footprint
            if (!empty($browserData['detected_tech'])) {
                $results['tech_footprint'] = array_merge($results['tech_footprint'], $browserData['detected_tech']);
            }
            
            // D. Security Headers Check (X-Vercel, CSP)
            if (!empty($browserData['headers'])) {
                $results['server_headers'] = $browserData['headers'];
            }
        }

        return $results;
    }
    
    /**
     * Titan v5.0: Browser Forensics (Headers, DB Sniffer, Deep Context)
     */
    protected function captureBrowserForensics(string $url, ?callable $onProgress = null): array
    {
        $payload = ['strategic_context' => null, 'detected_tech' => [], 'headers' => []];
        
        try {
            $browser = Browsershot::url($url)
                ->setChromePath('/usr/bin/google-chrome-stable')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'])
                ->ignoreHttpsErrors()
                ->timeout(60)
                ->userAgent('Mozilla/5.0 (compatible; LumeTitanBot/5.0)');
                
            // Inject Sniffer Script (Network & Headers)
            $eval = $browser->evaluate("(() => {
                let tech = [];
                // 1. DB Sniffer (Network)
                const resources = performance.getEntriesByType('resource').map(r => r.name);
                if (resources.some(r => r.includes('supabase.co'))) tech.push({name: 'Supabase', category: 'Database', icon: '⚡'});
                if (resources.some(r => r.includes('firestore.googleapis.com'))) tech.push({name: 'Firebase', category: 'Database', icon: '🔥'});
                
                // 2. Headers (Self-Fetch)
                // Note: Real headers are best captured via PHP HEAD request or DevTools protocol, 
                // but we attempt a fetch here for visible ones.
                
                return JSON.stringify({ tech: tech }); 
            })()");
            
            // Decode the string result (avoids Browsershot TypeError on array return)
            $eval = json_decode($eval, true);
            
            if (is_array($eval) && !empty($eval['tech'])) {
                $payload['detected_tech'] = $eval['tech'];
            }
            
            // Capture Headers via PHP (More reliable than JS)
            try {
                $headers = Http::timeout(5)->head($url)->headers();
                $cleanHeaders = [];
                foreach(['X-Powered-By', 'X-Vercel-Cache', 'Content-Security-Policy', 'Server', 'Via'] as $key) {
                    // Start Case normalization
                    foreach($headers as $hKey => $hVal) {
                        if (strtolower($hKey) === strtolower($key)) {
                            $cleanHeaders[$key] = is_array($hVal) ? $hVal[0] : $hVal;
                        }
                    }
                }
                $payload['headers'] = $cleanHeaders;
            } catch (\Exception $e) {}

            // Deep Context (Reuse the crawler logic or call it here)
            $deepContext = $this->crawlDeepContext($url, $onProgress);
            $payload['strategic_context'] = $deepContext['deep_context'] ?? null;
            
        } catch (\Exception $e) {
            Log::warning("Browser Forensics Failed: " . $e->getMessage());
        }
        
        return $payload;
    }

    /**
     * Perform live verification (Live Handshake, Whois, Server Health).
     */
    public function verifyLiveStatus(string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?? $url;
        
        // 1. Whois & Expiration
        $whois = $this->healthCheck->checkDomainWhois($domain);
        
        // 2. Server Health (HEAD Request as requested)
        $serverResponsive = false;
        try {
            $headResponse = Http::timeout(5)->head($url);
            $serverResponsive = $headResponse->successful();
        } catch (\Exception $e) {
            $serverResponsive = false;
        }
        
        // 3. LUME Handshake (Deprecated)
        $handshakeVerified = false;

        return [
            'is_alive' => $serverResponsive,
            'handshake_passed' => $handshakeVerified,
            'domain_data' => $whois,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Scan composer.json and package.json to identify tech stack.
     */
    public function scanTechStack(string $repoUrl, ?string $token = null): array
    {
        $techs = [];

        // Parse GitHub URL
        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $repoUrl, $matches);
        if (count($matches) < 3) {
            return $techs;
        }

        $owner = $matches[1];
        $owner = $matches[1];
        // FIX: rtrim bug
        $repo = preg_replace('/\.git$/', '', $matches[2]);

        // Scan composer.json
        $composerData = $this->fetchGitHubFile($owner, $repo, 'composer.json', $token);
        if ($composerData) {
            $techs = array_merge($techs, $this->parseComposerJson($composerData));
        }

        // Scan package.json
        $packageData = $this->fetchGitHubFile($owner, $repo, 'package.json', $token);
        if ($packageData) {
            $techs = array_merge($techs, $this->parsePackageJson($packageData));
        }
        
        // Titan v5.0: Docker Detection (Tier 1)
        $dockerfile = $this->fetchGitHubFile($owner, $repo, 'Dockerfile', $token);
        if ($dockerfile) {
             $techs[] = ['name' => 'Docker', 'category' => 'Infrastructure', 'icon' => '🐳', 'source' => 'Dockerfile'];
        }
        $dockerCompose = $this->fetchGitHubFile($owner, $repo, 'docker-compose.yml', $token);
        if ($dockerCompose) {
             $techs[] = ['name' => 'Docker Compose', 'category' => 'Infrastructure', 'icon' => '🐳', 'source' => 'docker-compose.yml'];
        }

        // Deduplicate
        return array_values(array_unique($techs, SORT_REGULAR));
    }

    /**
     * Parse composer.json to identify PHP dependencies.
     */
    protected function parseComposerJson(string $content): array
    {
        $techs = [];
        
        try {
            $data = json_decode($content, true);
            $dependencies = array_merge(
                $data['require'] ?? [],
                $data['require-dev'] ?? []
            );

            foreach ($dependencies as $package => $version) {
                $packageLower = strtolower($package);
                if (isset($this->techPatterns[$packageLower])) {
                    $techs[] = array_merge(
                        $this->techPatterns[$packageLower],
                        ['package' => $package, 'version' => $version, 'source' => 'composer.json']
                    );
                }
            }

            // Detect PHP version
            if (isset($dependencies['php'])) {
                $techs[] = [
                    'name' => 'PHP',
                    'category' => 'Runtime',
                    'icon' => '🐘',
                    'package' => 'php',
                    'version' => $dependencies['php'],
                    'source' => 'composer.json',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse composer.json', ['error' => $e->getMessage()]);
        }

        return $techs;
    }

    /**
     * Parse package.json to identify JS dependencies.
     */
    protected function parsePackageJson(string $content): array
    {
        $techs = [];
        
        try {
            $data = json_decode($content, true);
            $dependencies = array_merge(
                $data['dependencies'] ?? [],
                $data['devDependencies'] ?? []
            );

            foreach ($dependencies as $package => $version) {
                $packageLower = strtolower($package);
                if (isset($this->techPatterns[$packageLower])) {
                    $techs[] = array_merge(
                        $this->techPatterns[$packageLower],
                        ['package' => $package, 'version' => $version, 'source' => 'package.json']
                    );
                }
            }

            // Detect Node version
            if (isset($data['engines']['node'])) {
                $techs[] = [
                    'name' => 'Node.js',
                    'category' => 'Runtime',
                    'icon' => '💚',
                    'package' => 'node',
                    'version' => $data['engines']['node'],
                    'source' => 'package.json',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse package.json', ['error' => $e->getMessage()]);
        }

        return $techs;
    }

    /**
     * Scan config files from the repository.
     */
    public function scanConfigFiles(string $repoUrl, ?string $token = null): array
    {
        $configFiles = [];

        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/', $repoUrl, $matches);
        if (count($matches) < 3) {
            return $configFiles;
        }

        $owner = $matches[1];
        $owner = $matches[1];
        // FIX: rtrim bug
        $repo = preg_replace('/\.git$/', '', $matches[2]);

        // List of config files to scan
        $filesToScan = [
            '.env.example',
            'config/app.php',
            'config/database.php',
            'config/services.php',
            'config/mail.php',
            'config/filesystems.php',
            'config/broadcasting.php',
            'config/queue.php',
            'config/cache.php',
        ];

        foreach ($filesToScan as $file) {
            $content = $this->fetchGitHubFile($owner, $repo, $file, $token);
            if ($content) {
                $configFiles[] = [
                    'path' => $file,
                    'content' => $content,
                    'size' => strlen($content),
                ];
            }
        }

        return $configFiles;
    }

    /**
     * Scan repository for routing structure (Routes/Pages).
     */
    public function scanRouteStructure(string $repoUrl, ?string $token = null): array
    {
        $routes = [];

        preg_match('/github\\.com\\/([^\\/]+)\\/([^\\/\\?#]+)/', $repoUrl, $matches);
        if (count($matches) < 3) return $routes;

        $owner = $matches[1];
        $repo = preg_replace('/\\.git$/', '', $matches[2]);
        
        Log::info("TITAN DNA: Scanning Routes for {$owner}/{$repo}");

        // 1. Laravel Routes
        $webRoutes = $this->fetchGitHubFile($owner, $repo, 'routes/web.php', $token);
        if ($webRoutes) {
            $routes['laravel_web'] = $webRoutes;
            Log::info("TITAN DNA: Found laravel_web");
        }

        $apiRoutes = $this->fetchGitHubFile($owner, $repo, 'routes/api.php', $token);
        if ($apiRoutes) {
            $routes['laravel_api'] = $apiRoutes;
            Log::info("TITAN DNA: Found laravel_api");
        }

        // 2. Next.js / Nuxt / File-based Routing
        $searchDirs = ['pages', 'app', 'src/pages', 'src/app'];
        
        foreach ($searchDirs as $dir) {
            $files = $this->fetchGitHubDirectory($owner, $repo, $dir, $token);
            if (!empty($files)) {
                $routes["nextjs_{$dir}"] = $files;
                Log::info("TITAN DNA: Found {$dir} structure (count: " . count($files) . ")");
                
                // TITAN V6.7: Crawl one level deeper for sub-routes
                foreach ($files as $file) {
                    if ($file['type'] === 'dir') {
                        $subFiles = $this->fetchGitHubDirectory($owner, $repo, $file['path'], $token);
                        if (!empty($subFiles)) {
                             $routes["nextjs_{$dir}_{$file['name']}"] = $subFiles;
                        }
                    }
                }
            }
        }

        return $routes;
    }

    /**
     * Fetch directory listing from GitHub.
     */
    protected function fetchGitHubDirectory(string $owner, string $repo, string $path, ?string $token = null): array
    {
        $authToken = $token ?? config('services.github.system_token') ?? env('GITHUB_SYSTEM_TOKEN');
        
        try {
            $request = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'LUME-Core-Auditor']);

            if ($authToken) $request = $request->withToken($authToken);

            $response = $request->get("https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}");

            if ($response->successful() && is_array($response->json())) {
                // Map to simple list of filenames/types
                return array_map(function($item) {
                    return [
                        'name' => $item['name'],
                        'type' => $item['type'], // file or dir
                        'path' => $item['path'],
                        'download_url' => $item['download_url'] ?? null
                    ];
                }, $response->json());
            } else {
                // Scoped Silence: 404s are expected during recursive probing (e.g. searching for 'pages' vs 'app' dir)
                if ($response->status() === 404) {
                    Log::info("TITAN DNA: Probe Miss (404) - Path not found: {$path}");
                } else {
                    Log::warning("TITAN DNA: fetchGitHubDirectory non-success", [
                        'status' => $response->status(),
                        'path' => $path,
                        'body' => $response->body()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Directory might not exist, which is fine
        }

        return [];
    }

    /**
     * Use AI to analyze config files and determine required .env variables.
     */
    public function analyzeEnvRequirements(array $configFiles, array $techStack): array
    {
        $envVariables = [];

        // First, extract from .env.example if available
        foreach ($configFiles as $file) {
            if ($file['path'] === '.env.example') {
                $envVariables = array_merge($envVariables, $this->parseEnvExample($file['content']));
            }
        }

        // Build context for AI analysis
        $techNames = array_column($techStack, 'name');
        $configContents = '';
        foreach ($configFiles as $file) {
            if (str_starts_with($file['path'], 'config/')) {
                $configContents .= "=== {$file['path']} ===\n{$file['content']}\n\n";
            }
        }

        // Use AI to analyze if we have config files
        if (!empty($configContents)) {
            try {
                $aiResult = $this->analyzeWithAI($configContents, $techNames);
                $envVariables = array_merge($envVariables, $aiResult);
            } catch (\Exception $e) {
                Log::warning('AI analysis failed', ['error' => $e->getMessage()]);
            }
        }

        // Deduplicate by variable name
        $unique = [];
        foreach ($envVariables as $var) {
            $unique[$var['name']] = $var;
        }

        return array_values($unique);
    }

    /**
     * Parse .env.example file for variable definitions.
     */
    protected function parseEnvExample(string $content): array
    {
        $variables = [];
        $lines = explode("\n", $content);
        $currentGroup = 'General';

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check for group comments
            if (str_starts_with($line, '#')) {
                $comment = trim(substr($line, 1));
                if (!empty($comment) && strlen($comment) < 50) {
                    $currentGroup = $comment;
                }
                continue;
            }

            // Parse variable
            if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                $name = $matches[1];
                $defaultValue = $matches[2];
                
                // Determine if required (empty default = required)
                $isRequired = empty($defaultValue) || 
                              in_array($defaultValue, ['null', 'your-key-here', 'your-secret-here']);

                // Determine category based on name
                $category = $this->categorizeEnvVariable($name);

                $variables[] = [
                    'name' => $name,
                    'default' => $defaultValue,
                    'required' => $isRequired,
                    'category' => $category,
                    'group' => $currentGroup,
                    'source' => '.env.example',
                ];
            }
        }

        return $variables;
    }

    /**
     * Categorize an environment variable by its name.
     */
    protected function categorizeEnvVariable(string $name): string
    {
        $categories = [
            'DB_' => 'Database',
            'REDIS_' => 'Cache',
            'MAIL_' => 'Email',
            'AWS_' => 'AWS',
            'STRIPE_' => 'Payments',
            'PAYPAL_' => 'Payments',
            'PUSHER_' => 'Real-time',
            'BROADCAST_' => 'Real-time',
            'QUEUE_' => 'Queue',
            'CACHE_' => 'Cache',
            'SESSION_' => 'Session',
            'LOG_' => 'Logging',
            'SENTRY_' => 'Error Tracking',
            'GOOGLE_' => 'Google Services',
            'FACEBOOK_' => 'Social Auth',
            'GITHUB_' => 'GitHub',
            'ALGOLIA_' => 'Search',
            'SCOUT_' => 'Search',
            'FILESYSTEM_' => 'Storage',
            'CLOUDFLARE_' => 'CDN/DNS',
        ];

        foreach ($categories as $prefix => $category) {
            if (str_starts_with($name, $prefix)) {
                return $category;
            }
        }

        return 'General';
    }

    /**
     * Use Gemini AI to analyze config files for env requirements.
     */
    protected function analyzeWithAI(string $configContents, array $techNames): array
    {
        $prompt = "Analyze these Laravel config files and identify ALL environment variables that would need to be configured for a full application transfer.\n\n";
        $prompt .= "Tech Stack detected: " . implode(', ', $techNames) . "\n\n";
        $prompt .= "Config Files:\n{$configContents}\n\n";
        $prompt .= "Return a JSON array of objects with these fields:\n";
        $prompt .= "- name: The ENV variable name (e.g., 'STRIPE_KEY')\n";
        $prompt .= "- required: boolean (true if essential for app to run)\n";
        $prompt .= "- category: string (e.g., 'Payments', 'Database', 'Email')\n";
        $prompt .= "- description: Brief description of what this variable configures\n";
        $prompt .= "- service: The service/integration this relates to (e.g., 'Stripe', 'SendGrid')\n\n";
        $prompt .= "Focus on third-party service credentials that need to be transferred. Respond with ONLY the JSON array.";

        try {
            $response = Http::timeout(30)
                ->retry(3, 2000) // Titan v5.0: Retry logic
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model') . ':generateContent?key=' . config('services.gemini.key'), [ // Titan v5.0: Stable Model
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                
                // Extract JSON from response
                preg_match('/\[[\s\S]*\]/', $text, $matches);
                if (!empty($matches[0])) {
                    $parsed = json_decode($matches[0], true);
                    if (is_array($parsed)) {
                        return array_map(fn($v) => array_merge($v, ['source' => 'AI Analysis']), $parsed);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('AI config analysis failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Scan DNS records to identify the nameserver provider.
     */
    public function scanDnsProvider(string $websiteUrl): ?array
    {
        try {
            // Extract domain from URL
            $domain = $this->extractDomain($websiteUrl);
            
            // Get NS records
            $nsRecords = dns_get_record($domain, DNS_NS);
            
            if (empty($nsRecords)) {
                return null;
            }

            $nameservers = array_column($nsRecords, 'target');
            
            // Identify provider
            foreach ($this->dnsProviders as $key => $provider) {
                foreach ($nameservers as $ns) {
                    if (preg_match($provider['pattern'], $ns)) {
                        return [
                            'provider' => $provider['name'],
                            'icon' => $provider['icon'],
                            'nameservers' => $nameservers,
                        ];
                    }
                }
            }

            // Unknown provider
            return [
                'provider' => 'Unknown',
                'icon' => '❓',
                'nameservers' => $nameservers,
            ];

        } catch (\Exception $e) {
            Log::warning('DNS scan failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch a raw file from GitHub (Public Wrapper).
     */
    public function fetchRawFile(string $owner, string $repo, string $path, ?string $token = null): ?string
    {
        return $this->fetchGitHubFile($owner, $repo, $path, $token);
    }

    /**
     * Fetch a file from GitHub repository with Resilience Layer.
     */
    protected function fetchGitHubFile(string $owner, string $repo, string $path, ?string $token = null): ?string
    {
        // 1. Token Fallback Logic
        $authToken = $token;
        if (empty($authToken)) {
            $authToken = config('services.github.system_token') ?? env('GITHUB_SYSTEM_TOKEN');
        }

        try {
            $request = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3.raw',
                    // 2. User-Agent Header
                    'User-Agent' => 'LUME-Core-Auditor',
                ]);

            if ($authToken) {
                $request = $request->withToken($authToken);
            }

            $response = $request->get("https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}");

            // 3. Error Handling for 401/403
            if ($response->status() === 401 || $response->status() === 403) {
                 Log::warning('GitHub API Authentication Failure', [
                     'repo' => "{$owner}/{$repo}",
                     'path' => $path,
                     'status' => $response->status()
                 ]);
                 return null; // Return null instead of crashing, allows graceful degradation
            }

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Exception $e) {
            Log::error('GitHub API Request Failed', [
                'error' => $e->getMessage(),
                'repo' => "{$owner}/{$repo}"
            ]);
        }

        return null;
    }

    /**
     * Titan v5.0: Hard Truth SSL Collection.
     */
    public function getSSLForensics(string $url): array
    {
        try {
            $domain = $this->extractDomain($url);
            
            $streamContext = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $read = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $streamContext);
            $cert = stream_context_get_params($read);
            $certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);

            return [
                'issuer' => $certinfo['issuer']['O'] ?? $certinfo['issuer']['CN'] ?? 'Unknown',
                'valid_from' => date('Y-m-d', $certinfo['validFrom_time_t']),
                'valid_to' => date('Y-m-d', $certinfo['validTo_time_t']),
                'days_remaining' => floor(($certinfo['validTo_time_t'] - time()) / 86400),
                'protocol' => 'TLS 1.3' // PHP stream wrapper usually negotiates highest, assuming 1.3 for modern sites
            ];
        } catch (\Exception $e) {
            return ['error' => 'SSL Handshake Failed: ' . $e->getMessage()];
        }
    }

    /**
     * Titan v5.0: Deep Crawler for Strategic Context.
     * Visits Home + Top 3 Links to extract raw text for AI summary.
     */
    protected function crawlDeepContext(string $baseUrl, ?callable $onProgress = null): array
    {
        // 1. Visit Homepage & extract links
        // We use Browsershot to get the rendered DOM
        try {
            $browser = Browsershot::url($baseUrl)
                ->setChromePath('/usr/bin/google-chrome-stable')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'])
                ->ignoreHttpsErrors()
                ->timeout(60)
                ->userAgent('Mozilla/5.0 (compatible; LumeTitanBot/5.0)');
            
            $html = $browser->bodyHtml();
            $homeText = $browser->evaluate("document.body.innerText");
            
            // Extract internal links
            // Simple regex to find top 3 relevant internal pages (About, Pricing, Features)
            preg_match_all('/href=["\']((?:\/|' . preg_quote($baseUrl, '/') . ')[^"\']+)["\']/', $html, $matches);
            $links = array_unique($matches[1]);
            
            $interestingPaths = [];
            $keywords = ['about', 'pricing', 'product', 'features', 'docs', 'mission'];
            
            foreach ($links as $link) {
                foreach ($keywords as $kw) {
                    if (str_contains(strtolower($link), $kw)) {
                        $interestingPaths[] = $link;
                        break;
                    }
                }
                if (count($interestingPaths) >= 10) break;
            }
            
            $context = "=== STRATEGIC CONTEXT (HOMEPAGE) ===\n" . substr($homeText, 0, 5000) . "\n\n";
            
            // 2. Visit Top 10 Links
            foreach ($interestingPaths as $path) {
                // Normalize URL
                $targetUrl = str_starts_with($path, 'http') ? $path : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
                try {
                    if ($onProgress) {
                        $onProgress("Scanning internal page: {$path}...", 30);
                    }
                    $pageText = Browsershot::url($targetUrl)->timeout(30)->evaluate("document.body.innerText");
                    $context .= "=== STRATEGIC CONTEXT ({$path}) ===\n" . substr($pageText, 0, 3000) . "\n\n";
                } catch (\Exception $e) {
                    // Ignore fail
                }
            }
            
            return ['deep_context' => $context];
            
        } catch (\Exception $e) {
            Log::warning("Deep Crawl Failed: " . $e->getMessage());
            return ['deep_context' => "Context Unavailable: " . $e->getMessage()];
        }
    }

    /**
     * Extract domain from URL.
     */
    protected function extractDomain(string $url): string
    {
        $url = preg_replace('/^https?:\/\//', '', $url);
        $url = preg_replace('/^www\./', '', $url);
        $url = explode('/', $url)[0];
        $url = explode(':', $url)[0];
        
        // Titan v5.0 Fix: 'z' Bug / DNS Integrity Check
        // Use A record instead of ANY (ANY is often blocked)
        if (!checkdnsrr($url, "A")) {
            throw new \Exception("CRITICAL_DNS_FAILURE: Domain {$url} does not resolve.");
        }
        
        return $url;
    }
}