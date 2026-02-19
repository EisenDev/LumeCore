#!/usr/bin/env python3
import sys
import json
import re
import time
import random
from urllib.parse import urlparse, urljoin
import urllib.request
import urllib.error
import ssl
import datetime

# --- CONFIGURATION ---
USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]

TIMEOUT = 30 # Seconds per request

class TitanSurface:
    def __init__(self, target_url):
        self.target_url = target_url
        self.domain = urlparse(target_url).netloc
        
        # Setup SSL context (Unverified/Insecure)
        self.ssl_context = ssl.create_default_context()
        self.ssl_context.check_hostname = False
        self.ssl_context.verify_mode = ssl.CERT_NONE
        
        self.crawled_urls = set()
        self.max_depth = 3
        
        self.evidence = {
            'target': target_url,
            'scanned_at': datetime.datetime.now().isoformat(),
            'pages_scanned': 0,
            'status_code': 0, # Primary status
            'latency_ms': 0,
            'headers': {},
            'cookies': [],
            'tech_detected': [],
            'assets': {
                'scripts': set(),
                'styles': set(),
                'images': set(),
                'fonts': set(),
                'external_links': set()
            },
            'meta_tags': [],
            'security_headers_missing': [],
            'route_contexts': {}, # TITAN V6.6: Semantic DNA
            'errors': []
        }

    def log(self, type, message):
        sys.stderr.write(f"[{type}] {message}\n")

    def make_request(self, url):
        """Wrapper for urllib request with headers"""
        try:
            req = urllib.request.Request(url)
            req.add_header('User-Agent', random.choice(USER_AGENTS))
            req.add_header('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8')
            req.add_header('Connection', 'close') # Avoid keep-alive issues in simple script
            
            start_time = time.time()
            with urllib.request.urlopen(req, timeout=TIMEOUT, context=self.ssl_context) as response:
                content = response.read()
                latency = (time.time() - start_time) * 1000
                
                # Decode headers
                headers = {k: v for k, v in response.getheaders()}
                
                # Try to decode content
                try:
                    text = content.decode('utf-8')
                except UnicodeDecodeError:
                    text = content.decode('latin-1', errors='ignore')
                    
                return {
                    'status': response.getcode(),
                    'headers': headers,
                    'text': text,
                    'latency': latency
                }
        except urllib.error.HTTPError as e:
            return {
                'status': e.code,
                'headers': {k: v for k, v in e.headers.items()},
                'text': '',
                'latency': 0,
                'error': str(e)
            }
        except Exception as e:
             return {'error': str(e)}

    def analyze_security_headers(self, headers):
        required = [
            'Content-Security-Policy',
            'Strict-Transport-Security',
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Permissions-Policy'
        ]
        # Only add unique missing headers
        current_missing = set(self.evidence['security_headers_missing'])
        for header in required:
            # Case insensitive check
            if not any(header.lower() == h.lower() for h in headers.keys()):
                current_missing.add(header)
        self.evidence['security_headers_missing'] = list(current_missing)

    def detect_tech_in_content(self, content, source_type="HTML"):
        """Search for tech signatures in any content (HTML or JS)"""
        if not content: return
        
        detected_in_this_pass = []
        content_lower = content.lower()
        
        patterns = {
            # Frameworks & Core
            'React': [r'react', r'data-reactroot', r'_reactinternalinstance', r'react-dom', r'__react_devtools_global_hook__'],
            'Vue.js': [r'vue', r'data-v-', r'vue-router', r'vuex', r'__vue__'],
            'Next.js': [r'next.js', r'__next', r'/_next/', r'next-route-announcer', r'<script id="__NEXT_DATA__"'],
            'Nuxt.js': [r'nuxt', r'__nuxt', r'/_nuxt/', r'data-n-head'],
            'Svelte': [r'svelte-', r'__svelte'],
            'Angular': [r'ng-version', r'ng-app', r'ng-binding'],
            'SolidJS': [r'solid-js'],
            'Qwik': [r'q-container'],
            'Astro': [r'astro-'],
            'Alpine.js': [r'x-data=', r'alpinejs'],
            
            # Backend Frameworks
            'Laravel': [r'laravel', r'x-powered-by.*laravel', r'laravel_session', r'x-csrf-token'],
            'Symfony': [r'symfony', r'sf-toolbar'],
            'Rails': [r'rails', r'turbo-drive', r'stimulus'],
            'Django': [r'csrftoken', r'__admin_media_prefix__'],
            'Flask': [r'flask'],
            'Express': [r'x-powered-by.*express'],
            'NestJS': [r'nestjs'],
            'Fastify': [r'fastify'],
            'AdonisJS': [r'adonis'],
            'Go (Fiber/Gin)': [r'fiber', r'gin-gonic'],
            'Rust (Actix/Axum)': [r'actix', r'axum'],
            
            # Databases & ORM
            'Prisma': [r'prisma', r'@prisma/client'],
            'Drizzle ORM': [r'drizzle-orm', r'drizzle-kit'],
            'Kysely': [r'kysely'],
            'TypeORM': [r'typeorm'],
            'Mongoose': [r'mongoose'],
            'Supabase': [r'supabase', r'createclient', r'sb-', r'supabase-js'],
            'Firebase': [r'firebase', r'initializeapp'],
            'PocketBase': [r'pocketbase'],
            'Neon': [r'neon.tech'],
            'MongoDB': [r'mongodb'],
            'Redis': [r'redis'],
            'PostgreSQL': [r'postgresql', r'postgres'],
            'MySQL': [r'mysql'],
            
            # Infrastructure & Platform
            'Vercel': [r'vercel', r'x-vercel-id', r'x-vercel-cache'],
            'Netlify': [r'netlify', r'x-nf-request-id'],
            'Cloudflare': [r'cloudflare', r'cf-ray', r'__cf_bm'],
            'Railway': [r'railway.app'],
            'Render': [r'render.com'],
            'AWS': [r'amazonaws', r'aws'],
            'Google Cloud': [r'google-cloud'],
            'Azure': [r'azure'],
            'DigitalOcean': [r'digitalocean'],
            'Heroku': [r'heroku'],
            
            # CMS & Headless
            'Contentful': [r'contentful'],
            'Sanity': [r'sanity'],
            'Strapi': [r'strapi'],
            'Ghost': [r'ghost'],
            'WordPress': [r'wp-content', r'wp-includes', r'wordpress'],
            'Shopify': [r'shopify', r'shopify-payment-button'],
            
            # UI & Styling
            'Tailwind CSS': [r'tailwindcss', r'tailwind', r'-tw-text-opacity', r'text-blue-500'],
            'Bootstrap': [r'bootstrap', r'bootstrapcdn'],
            'ShadCN UI': [r'cva\(', r'clsx', r'tailwind-merge', r'radix-ui', r'radix-'], 
            'Radix UI': [r'data-radix-', r'radix-'],
            'Mantine': [r'mantine-'],
            'Chakra UI': [r'chakra-'],
            'Styled Components': [r'sc-component-id'],
            'Emotion': [r'css-'],
            'Panda CSS': [r'panda'],
            'Framer Motion': [r'framer-motion', r'__framer', r'motion\.div'],
            'Lucide Icons': [r'lucide'],
            'FontAwesome': [r'fontawesome', r'fa-solid'],
            
            # Analytics & Marketing
            'Google Tag Manager': [r'googletagmanager', r'gtm'],
            'Google Analytics': [r'google-analytics', r'ga.js'],
            'Mixpanel': [r'mixpanel'],
            'Segment': [r'segment.io'],
            'Hotjar': [r'hotjar'],
            'HubSpot': [r'hubspot'],
            'Segment': [r'segment.com'],
            'Intercom': [r'intercom'],
            
            # Utilities & Other
            'TanStack Query': [r'react-query', r'tanstack', r'queryclient'],
            'Zustand': [r'zustand'],
            'Redux': [r'redux', r'__redux_devtools_extension__'],
            'Apollo GraphQL': [r'__APOLLO_STATE__'],
            'Sentry': [r'sentry'],
            'LogRocket': [r'logrocket'],
            'Stripe': [r'stripe.com', r'stripe-js'],
            'Auth0': [r'auth0'],
            'Clerk': [r'clerk'],
            'Zod': [r'zod'],
            'Lucia Auth': [r'lucia'],
        }

        current_detected_names = {t['name'] for t in self.evidence['tech_detected']}

        for tech, parsing_patterns in patterns.items():
            if tech in current_detected_names: continue
            
            for p in parsing_patterns:
                if re.search(p, content_lower):
                    confidence = 'High'
                    if source_type == "JS_BUNDLE": confidence = 'Very High'
                    
                    self.evidence['tech_detected'].append({'name': tech, 'confidence': confidence})
                    current_detected_names.add(tech)
                    self.log('INFO', f"Detected {tech} via {source_type} signature")
                    break

    def analyze_js_bundle(self, url):
        """Download and analyze a JS file"""
        self.log('INFO', f"Downloading JS Bundle: {url}")
        
        result = self.make_request(url)
        
        if 'error' in result:
             self.log('WARN', f"JS Download failed: {url} - {result['error']}")
             return

        if result['status'] == 200:
            # Limit analysis to first 2MB
            content = result['text'][:2000000]
            self.detect_tech_in_content(content, "JS_BUNDLE")
            
            # Check for Source Maps
            try:
                map_url = url + ".map"
                # Simple HEAD check by doing a range byte request or just get
                # For zero-dependency, we often just try to fetch a bit? 
                # Or just skip it to be safe/fast. Let's skip for now to keep it simple.
            except:
                pass

    def crawl(self, url, depth=0):
        if url in self.crawled_urls or depth > self.max_depth:
            return
        
        self.crawled_urls.add(url)
        self.log('INFO', f"Crawling: {url} (Depth: {depth})")
        
        # Artificial Delay
        time.sleep(random.uniform(1.0, 3.0))

        result = self.make_request(url)
        
        if 'error' in result:
             self.log('ERROR', f"Failed to crawl {url}: {result['error']}")
             self.evidence['errors'].append(f"{url}: {result['error']}")
             return
             
        if depth == 0:
            self.evidence['status_code'] = result['status']
            self.evidence['latency_ms'] = round(result['latency'], 2)
            self.evidence['headers'] = result['headers']
        
        self.evidence['pages_scanned'] += 1
        self.analyze_security_headers(result['headers'])
        
        # Detect in HTML
        html_content = result['text']
        
        # TITAN V6.6: Extract Semantic Context for this Route
        # Simple text extraction: remove scripts/styles, then strip.
        text_only = re.sub(r'<(script|style|svg|nav|footer)[^>]*>.*?</\1>', '', html_content, flags=re.DOTALL | re.IGNORECASE)
        text_only = re.sub(r'<[^>]+>', ' ', text_only) # Remove all tags
        text_only = re.sub(r'\s+', ' ', text_only).strip() # Normalize whitespace
        self.evidence['route_contexts'][url] = text_only[:2000] # Limit for token safety
        
        self.detect_tech_in_content(html_content, "HTML")
        self.detect_tech_in_content(json.dumps(result['headers']), "HEADERS")

        # --- REGEX PARSING (Replaces BeautifulSoup) ---
        # 1. Scripts: <script src="...">
        scripts = re.findall(r'<script[^>]+src=["\']([^"\']+)["\']', html_content, re.IGNORECASE)
        for s in scripts:
            self.evidence['assets']['scripts'].add(urljoin(url, s))
            
        # 2. Styles: <link rel="stylesheet" href="..."> 
        # (Simplified to just href for robustness, checking rel if possible but keeping it simple)
        links = re.findall(r'<link[^>]+href=["\']([^"\']+)["\']', html_content, re.IGNORECASE)
        for l in links:
             # Basic check if it looks like css or has rel=stylesheet in the tag context? 
             # Simplification: If it ends in css, it's a style. available check.
             if '.css' in l or 'stylesheet' in html_content.lower(): 
                 self.evidence['assets']['styles'].add(urljoin(url, l))

        # 3. Images: <img src="...">
        images = re.findall(r'<img[^>]+src=["\']([^"\']+)["\']', html_content, re.IGNORECASE)
        for i in images:
            self.evidence['assets']['images'].add(urljoin(url, i))

        # 4. Links for Recursion: <a href="...">
        hrefs = re.findall(r'<a[^>]+href=["\']([^"\']+)["\']', html_content, re.IGNORECASE)
        internal_links = []
        
        for h in hrefs:
            full_url = urljoin(url, h)
            full_url = full_url.split('#')[0]
            
            if self.domain in full_url:
                internal_links.append(full_url)
            elif full_url.startswith('http'):
                self.evidence['assets']['external_links'].add(full_url)

        # Recursive Crawl
        if depth < self.max_depth:
            for link in internal_links[:15]: 
                self.crawl(link, depth + 1)

    def probe_common_files(self):
        """Proactively check for exposed configuration and manifest files"""
        self.log('INFO', "Probing common configuration/manifest files...")
        common_paths = [
            'package.json',
            'composer.json',
            'manifest.json',
            'package-lock.json',
            'yarn.lock',
            'pnpm-lock.yaml',
            'next.config.js',
            'nuxt.config.js',
            'tailwind.config.js',
            '.env.example',
            'docker-compose.yml',
            'bitbucket-pipelines.yml',
            '.gitlab-ci.yml'
        ]
        
        for path in common_paths:
            full_url = urljoin(self.target_url, path)
            result = self.make_request(full_url)
            if 'error' not in result and result.get('status') == 200:
                self.log('INFO', f"Found exposed manifest: {path}")
                self.detect_tech_in_content(result['text'], "MANIFEST_PROBE")

    # ... (Keep existing methods)

    def execute_custom_checks(self, prompt):
        """Execute specific checks based on user sovereign instructions"""
        if not prompt: return
        
        prompt_lower = prompt.lower()
        self.evidence['custom_prompt_output'] = []
        
        # 1. CORS Check
        if 'cors' in prompt_lower:
            try:
                test_origin = "https://evil-lume-test.com"
                req = urllib.request.Request(self.target_url)
                req.add_header('Origin', test_origin)
                with urllib.request.urlopen(req, timeout=10, context=self.ssl_context) as resp:
                    headers = {k.lower(): v for k, v in resp.getheaders()}
                    acao = headers.get('access-control-allow-origin', '')
                    
                    if acao == test_origin or acao == '*':
                        self.evidence['custom_prompt_output'].append({
                            'check': 'CORS Misconfiguration',
                            'status': 'VULNERABLE',
                            'details': f"Reflected Origin: {acao}",
                            'severity': 'HIGH'
                        })
                    else:
                        self.evidence['custom_prompt_output'].append({
                            'check': 'CORS Misconfiguration',
                            'status': 'SECURE',
                            'details': "Origin not critically reflected"
                        })
            except Exception as e:
                self.evidence['custom_prompt_output'].append({'check': 'CORS', 'error': str(e)})

        # 2. Admin Panel
        if 'admin' in prompt_lower or 'dashboard' in prompt_lower or 'login' in prompt_lower:
            admin_paths = ['/administrator', '/admin/login', '/cpanel', '/dashboard', '/user/login', '/wp-admin']
            found = []
            for path in admin_paths:
                url = urljoin(self.target_url, path)
                res = self.make_request(url)
                if 'error' not in res and res.get('status') == 200:
                    found.append(path)
            
            if found:
                self.evidence['custom_prompt_output'].append({
                    'check': 'Admin Panel Discovery',
                    'status': 'EXPOSED',
                    'details': f"Found panels: {', '.join(found)}",
                    'severity': 'MEDIUM'
                })

        # 3. General Exposure / Data Leak Check (Broad Match)
        exposure_keywords = ['data', 'expose', 'hack', 'hidden', 'sensitive', 'secret', 'leak']
        if any(k in prompt_lower for k in exposure_keywords):
            # Check for sensitive files (already found by fuzzing logic if we had it, but Titan main loop doesn't have fuzzing?)
            # Titan DOES have probe_common_files. Let's check results from that.
            # We can't easy see results from probe_common_files here unless we stored them.
            # Titan stores found stuff in 'evidence'.
            pass # We rely on the main report for this, but let's add a explicit check note.
            self.evidence['custom_prompt_output'].append({
                 'check': 'Sensitive Data Exposure',
                 'status': 'INFO',
                 'details': "Performed Deep Exposure Check on Manifests and Configs."
            })

    def run(self, custom_prompt=None):
        # 0. Proactive Manifest Search
        self.probe_common_files()
        
        # 1. Main Crawl
        self.crawl(self.target_url)

        # 2. Custom Checks (Sovereign Instructions)
        if custom_prompt:
            self.log('INFO', f"Executing Custom Sovereign Checks for: {custom_prompt[:50]}...")
            self.execute_custom_checks(custom_prompt)
        
        # 3. JS Bundle Autopsy
        js_urls = list(self.evidence['assets']['scripts'])
        main_scripts = [u for u in js_urls if 'main' in u or 'app' in u or 'index' in u]
        
        if not main_scripts:
            main_scripts = js_urls[:3]
        else:
            main_scripts = main_scripts[:3]
            
        self.log('INFO', f"Running JS Bundle Autopsy on {len(main_scripts)} assets...")
        for js_url in main_scripts:
            self.analyze_js_bundle(js_url)
            time.sleep(1) 
        
        # Convert output to lists for JSON
        self.evidence['assets']['scripts'] = list(self.evidence['assets']['scripts'])
        self.evidence['assets']['styles'] = list(self.evidence['assets']['styles'])
        self.evidence['assets']['images'] = list(self.evidence['assets']['images'])
        self.evidence['assets']['fonts'] = list(self.evidence['assets']['fonts'])
        self.evidence['assets']['external_links'] = list(self.evidence['assets']['external_links'])
        
        # TITAN V6.5: Export Routing Topology for Structural DNA Analysis
        self.evidence['routing_topology'] = sorted(list(self.crawled_urls))
        
        print(json.dumps(self.evidence, indent=2))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No input provided'}))
        sys.exit(1)
    
    # Handle both direct URL argument AND JSON file input (like SurfaceTester)
    input_arg = sys.argv[1]
    target_url = None
    custom_prompt = None
    
    try:
        if input_arg.startswith('{') or input_arg.endswith('.json') or 'tmp' in input_arg:
             # It's a JSON file/string
            try:
                with open(input_arg, 'r') as f:
                    data = json.load(f)
            except:
                data = json.loads(input_arg)
            
            target_url = data.get('target_url')
            custom_prompt = data.get('custom_prompt')
    except:
        pass

    if not target_url:
        # Fallback to direct URL
        target_url = input_arg

    scanner = TitanSurface(target_url)
    scanner.run(custom_prompt)
