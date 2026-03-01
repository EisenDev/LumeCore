#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.parse
import urllib.error
import re
import socket

# Set global timeout
socket.setdefaulttimeout(20)

class SurfaceTester:
    def __init__(self, target_url):
        self.target_url = target_url
        self.parsed_url = urllib.parse.urlparse(target_url)
        self.domain = self.parsed_url.netloc
        self.headers = {
            'User-Agent': 'LUME-SurfaceScanner/2.0',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        }
        self.results = {
            'xss_tests': [],
            'subdomain_enum': [],
            'directory_fuzzing': [],
            'tech_fingerprint': {},
            'csrf_validation': [],
            'custom_prompt_output': []
        }

    def _request(self, url, method='GET', data=None, headers=None):
        try:
            req_headers = self.headers.copy()
            if headers:
                req_headers.update(headers)
            
            req_data = None
            if data:
                req_data = urllib.parse.urlencode(data).encode('utf-8')

            req = urllib.request.Request(url, data=req_data, headers=req_headers, method=method)
            with urllib.request.urlopen(req, timeout=10) as response:
                return {
                    'status_code': response.getcode(),
                    'body': response.read().decode('utf-8', errors='ignore'),
                    'headers': dict(response.info())
                }
        except urllib.error.HTTPError as e:
            return {
                'status_code': e.code,
                'body': e.read().decode('utf-8', errors='ignore'),
                'headers': dict(e.headers)
            }
        except Exception:
            return None

    def test_reflected_xss(self, forms):
        payload = "<script>alert('LUME')</script>"
        
        # Test 1: URL Parameters
        if self.parsed_url.query:
            qs = urllib.parse.parse_qs(self.parsed_url.query)
            for key in qs:
                qs_copy = qs.copy()
                qs_copy[key] = [payload]
                new_query = urllib.parse.urlencode(qs_copy, doseq=True)
                test_url = self.parsed_url._replace(query=new_query).geturl()
                
                resp = self._request(test_url)
                if resp and payload in resp['body']:
                    self.results['xss_tests'].append({
                        'location': f"URL Parameter: {key}",
                        'payload': payload,
                        'status': 'VULNERABLE',
                        'severity': 'HIGH'
                    })

    def enumerate_subdomains(self):
        subdomains = ['admin', 'api', 'dev', 'staging', 'test', 'app', 'dashboard', 'secure']
        scheme = self.parsed_url.scheme
        base_domain = self.domain
        
        if base_domain.startswith('www.'):
            base_domain = base_domain[4:]
            
        for sub in subdomains:
            url = f"{scheme}://{sub}.{base_domain}"
            try:
                resp = self._request(url)
                if resp and resp['status_code'] < 400:
                    self.results['subdomain_enum'].append({
                        'subdomain': url,
                        'status': 'FOUND',
                        'risk': 'POTENTIAL_EXPOSURE'
                    })
            except:
                continue

    def fuzz_directories(self):
        paths = [
            '/.git/HEAD', '/.env', '/wp-config.php', '/config.php', 
            '/admin', '/backup', '/db.sql', '/server-status', '/dashboard'
        ]
        
        for path in paths:
            url = urllib.parse.urljoin(self.target_url, path)
            resp = self._request(url)
            
            if resp and resp['status_code'] == 200:
                body_sample = resp['body'][:500]
                if 'GIT' in body_sample or 'DB_HOST' in body_sample or 'dashboard' in path:
                    self.results['directory_fuzzing'].append({
                        'path': path,
                        'status': 'EXPOSED',
                        'severity': 'CRITICAL' if 'env' in path or 'git' in path else 'MEDIUM'
                    })

    def fingerprint_technology(self):
        resp = self._request(self.target_url)
        if not resp:
            return

        body = resp['body'].lower()
        headers = str(resp['headers']).lower()
        
        techs = {
            'Laravel': ['laravel_session', 'x-csrf-token', '_token'],
            'React': ['react-dom', 'data-reactroot'],
            'Vue': ['vue-server-renderer', 'data-v-'],
            'WordPress': ['wp-content', 'wp-includes'],
            'Apache': ['apache'],
            'Nginx': ['nginx'],
            'Bootstrap': ['bootstrap'],
            'Tailwind': ['tailwind']
        }
        
        detected = []
        for tech, signatures in techs.items():
            for sig in signatures:
                if sig in body or sig in headers:
                    detected.append(tech)
                    break
        
        self.results['tech_fingerprint'] = {'detected': detected}

    def validate_csrf_tokens(self, forms):
        if not forms:
            return

        for form in forms:
            inputs = form.get('inputs', [])
            # Heuristic check for CSRF token
            has_csrf = any(x for x in inputs if 'csrf' in x.lower() or 'token' in x.lower())
            
            if not has_csrf:
                self.results['csrf_validation'].append({
                    'form_action': form.get('action', 'unknown'),
                    'status': 'MISSING_TOKEN',
                    'severity': 'MEDIUM'
                })

    def execute_custom_checks(self, prompt, forms):
        if not prompt:
            return

        prompt_lower = prompt.lower()
        
        # 1. CORS Check
        if 'cors' in prompt_lower:
            test_origin = "https://evil-lume-test.com"
            resp = self._request(self.target_url, headers={'Origin': test_origin})
            if resp:
                acao = resp['headers'].get('Access-Control-Allow-Origin', '')
                if acao == test_origin or acao == '*':
                    self.results['custom_prompt_output'].append({
                        'check': 'CORS Misconfiguration',
                        'status': 'VULNERABLE',
                        'details': f"Reflected Origin: {acao}",
                        'severity': 'HIGH'
                    })
                else:
                    self.results['custom_prompt_output'].append({
                        'check': 'CORS Misconfiguration',
                        'status': 'SECURE',
                        'details': "Origin not critically reflected"
                    })

        # 2. Admin Panel
        if 'admin' in prompt_lower or 'dashboard' in prompt_lower:
            admin_paths = ['/administrator', '/admin/login', '/cpanel', '/dashboard', '/user/login']
            found = []
            for path in admin_paths:
                url = urllib.parse.urljoin(self.target_url, path)
                resp = self._request(url)
                if resp and resp['status_code'] == 200:
                    found.append(path)
            
            if found:
                self.results['custom_prompt_output'].append({
                    'check': 'Admin Panel Discovery',
                    'status': 'EXPOSED',
                    'details': f"Found panels: {', '.join(found)}",
                    'severity': 'MEDIUM'
                })

        # 3. General Exposure / Data Leak Check (Broad Match)
        exposure_keywords = ['data', 'expose', 'hack', 'hidden', 'sensitive', 'secret', 'leak']
        if any(k in prompt_lower for k in exposure_keywords):
            # Check for sensitive files (already found by fuzz_directories)
            exposed_files = [res['path'] for res in self.results.get('directory_fuzzing', []) if res['status'] == 'EXPOSED']
            
            if exposed_files:
                self.results['custom_prompt_output'].append({
                    'check': 'Sensitive Data Exposure',
                    'status': 'CRITICAL',
                    'details': f"Exposed files found: {', '.join(exposed_files)}",
                    'severity': 'CRITICAL'
                })
            else:
                self.results['custom_prompt_output'].append({
                    'check': 'Sensitive Data Exposure',
                    'status': 'SECURE',
                    'details': "No common sensitive files (env, git, sql) exposed via public directory fuzzing."
                })

    def run_all_tests(self, forms, custom_prompt=None):
        try:
            self.test_reflected_xss(forms)
            self.enumerate_subdomains()
            self.fuzz_directories()
            self.fingerprint_technology()
            self.validate_csrf_tokens(forms)
            
            if custom_prompt:
                self.execute_custom_checks(custom_prompt, forms)
        except Exception as e:
            # Continue even if one test fails
            pass
        
        return self.results

import json
import sys
import urllib.parse
import urllib.request

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No input provided'}))
        sys.exit(1)

    input_arg = sys.argv[1]
    input_data = {}

    try:
        # Try to treat argument as a file path first
        with open(input_arg, 'r') as f:
            input_data = json.load(f)
    except (FileNotFoundError, OSError):
        # Fallback to treating it as a raw JSON string
        try:
            input_data = json.loads(input_arg)
        except json.JSONDecodeError:
            print(json.dumps({'error': 'Invalid JSON input or file path'}))
            sys.exit(1)

    target_url = input_data.get('target_url')
    # Use empty list/None as safe defaults
    forms = input_data.get('forms', []) 
    # Ensure forms is a list
    if not isinstance(forms, list):
        forms = []
        
    custom_prompt = input_data.get('custom_prompt', None)
    
    if not target_url:
        print(json.dumps({'error': 'No target_url provided'}))
        sys.exit(1)

    tester = SurfaceTester(target_url)
    results = tester.run_all_tests(forms, custom_prompt)
    
    print(json.dumps(results))

if __name__ == '__main__':
    main()
