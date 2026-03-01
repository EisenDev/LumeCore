#!/usr/bin/env python3
"""
TitanLeaks - Sensitive Data Exposure Scanner
Scans for leaked credentials, API keys, internal IPs, and sensitive files.
"""
import sys
import os
import re
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # Sensitive files to probe
    SENSITIVE_PATHS = [
        '.env', '.env.backup', '.env.local', '.env.production',
        'wp-config.php', 'wp-config.php.bak', 'config.php',
        '.git/HEAD', '.git/config',
        '.svn/entries', '.hg/hgrc',
        'backup.sql', 'dump.sql', 'database.sql', 'db.sql',
        '.htaccess', '.htpasswd',
        'phpinfo.php', 'info.php',
        'server-status', 'server-info',
        'robots.txt', 'sitemap.xml',
        'crossdomain.xml', 'clientaccesspolicy.xml',
        'package.json', 'composer.json',
        'web.config', 'Gruntfile.js',
        'elmah.axd', 'trace.axd',
    ]

    # Regex patterns for sensitive data in HTML/JS source
    LEAK_PATTERNS = {
        'AWS Access Key': r'AKIA[0-9A-Z]{16}',
        'AWS Secret Key': r'(?:aws_secret_access_key|AWS_SECRET)\s*[:=]\s*["\']?([A-Za-z0-9/+=]{40})',
        'Google API Key': r'AIza[0-9A-Za-z_-]{35}',
        'Stripe Secret Key': r'sk_live_[0-9a-zA-Z]{24,}',
        'Stripe Publishable Key': r'pk_live_[0-9a-zA-Z]{24,}',
        'Firebase URL': r'https://[a-z0-9-]+\.firebaseio\.com',
        'Supabase URL': r'https://[a-z0-9]+\.supabase\.(co|io)',
        'Database URL': r'(?:mysql|postgres|mongodb|redis)://[^\s<>"\']+',
        'Hardcoded Password': r'(?:password|passwd|pwd)\s*[:=]\s*["\']([^"\']{3,})["\']',
        'Private Key': r'-----BEGIN (?:RSA |EC )?PRIVATE KEY-----',
        'JWT Token': r'eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}',
        'Internal IP Address': r'\b(?:10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})\b',
        'Email Address in Source': r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
        'Slack Webhook': r'https://hooks\.slack\.com/services/[A-Za-z0-9/]+',
        'GitHub Token': r'gh[pousr]_[A-Za-z0-9_]{36,}',
        'Mapbox Token': r'pk\.eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+',
        'Localhost Reference': r'(?:https?://)?(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/[^\s<>"\']*)?',
    }

    # Verification heuristics for sensitive file content
    FILE_SIGNATURES = {
        '.env': ['APP_KEY', 'DB_PASSWORD', 'APP_ENV', 'MAIL_PASSWORD'],
        '.git/HEAD': ['ref:', 'refs/heads/'],
        '.git/config': ['repositoryformatversion', '[remote'],
        'wp-config.php': ['DB_NAME', 'DB_USER', 'DB_PASSWORD'],
        'phpinfo.php': ['PHP Version', 'phpinfo()'],
        'backup.sql': ['INSERT INTO', 'CREATE TABLE', 'DROP TABLE'],
        'dump.sql': ['INSERT INTO', 'CREATE TABLE', 'DROP TABLE'],
        'database.sql': ['INSERT INTO', 'CREATE TABLE', 'DROP TABLE'],
        'db.sql': ['INSERT INTO', 'CREATE TABLE'],
        '.htpasswd': [':$apr1$', ':$2y$', ':{SHA}'],
        'package.json': ['"dependencies"', '"scripts"'],
        'composer.json': ['"require"', '"autoload"'],
    }

    def run(self):
        self.log(f"Starting sensitive data exposure scan on {self.target_url}")
        
        # PHASE 1: Probe for sensitive files
        self.log("Phase 1: Probing for exposed sensitive files...")
        for path in self.SENSITIVE_PATHS:
            url = f"{self.target_url.rstrip('/')}/{path}"
            resp = self.request(url)
            
            if 'error' in resp and 'status' not in resp:
                continue
            
            status = resp.get('status', 0)
            body = resp.get('body', '')
            
            if status == 200 and len(body) > 10:
                # Verify it's actually the sensitive file (not a custom 404 page)
                is_real = False
                base_path = path.split('/')[-1] if '/' in path else path
                
                if base_path in self.FILE_SIGNATURES:
                    for sig in self.FILE_SIGNATURES[base_path]:
                        if sig in body:
                            is_real = True
                            break
                elif path in ('robots.txt', 'sitemap.xml', 'crossdomain.xml'):
                    is_real = True  # These are expected but worth noting
                    
                if is_real:
                    severity = 'CRITICAL' if base_path in ('.env', '.htpasswd', 'wp-config.php', 'backup.sql', 'dump.sql', 'database.sql', 'db.sql') else 'HIGH' if '.git' in path else 'MEDIUM'
                    
                    # Sanitize preview (never show full credentials)
                    preview = body[:200].replace('\n', ' ').strip()
                    
                    self.add_finding(
                        f"Exposed Sensitive File: {path}",
                        severity,
                        f"The file '{path}' is publicly accessible at {url}. This file may contain credentials, configuration data, or other sensitive information.",
                        f"Preview: {preview}..."
                    )
                    self.log(f"[EXPOSED] {path} (HTTP {status}, {len(body)} bytes)")
                else:
                    self.log(f"[OK] {path} returned 200 but appears to be a custom error page")
            else:
                self.log(f"[OK] {path} returned HTTP {status}")

        # PHASE 2: Scan page source for leaked secrets
        self.log("Phase 2: Scanning homepage source for leaked secrets...")
        resp = self.request(self.target_url)
        if 'error' not in resp or 'status' in resp:
            body = resp.get('body', '')
            
            for leak_name, pattern in self.LEAK_PATTERNS.items():
                matches = re.findall(pattern, body, re.IGNORECASE)
                if matches:
                    # Don't flag common false positives
                    if leak_name == 'Email Address in Source' and len(matches) <= 2:
                        severity = 'INFO'
                    elif leak_name == 'Internal IP Address':
                        severity = 'MEDIUM'
                    elif leak_name == 'Localhost Reference':
                        severity = 'HIGH'
                    elif leak_name in ('AWS Access Key', 'AWS Secret Key', 'Stripe Secret Key', 'Private Key', 'Database URL', 'Hardcoded Password'):
                        severity = 'CRITICAL'
                    elif leak_name in ('Google API Key', 'Firebase URL', 'Supabase URL', 'JWT Token', 'GitHub Token'):
                        severity = 'HIGH'
                    else:
                        severity = 'MEDIUM'

                    # Truncate match for safety
                    sample = str(matches[0])[:60] + '...' if len(str(matches[0])) > 60 else str(matches[0])
                    
                    self.add_finding(
                        f"Sensitive Data Leak: {leak_name}",
                        severity,
                        f"Found {len(matches)} instance(s) of '{leak_name}' pattern in the page source code.",
                        f"Sample match: {sample}"
                    )

        # PHASE 3: Check HTML comments for developer notes
        self.log("Phase 3: Scanning HTML comments for sensitive information...")
        if body:
            comments = re.findall(r'<!--(.*?)-->', body, re.DOTALL)
            sensitive_comment_patterns = [
                r'(?:password|passwd|pwd|secret|key|token|api[_-]?key|credentials)',
                r'(?:TODO|FIXME|HACK|BUG|XXX)',
                r'(?:admin|root|debug|test)',
                r'(?:database|db_|mysql|postgres)',
            ]
            for comment in comments:
                comment_text = comment.strip()
                if len(comment_text) < 5:
                    continue
                for pattern in sensitive_comment_patterns:
                    if re.search(pattern, comment_text, re.IGNORECASE):
                        self.add_finding(
                            "Sensitive HTML Comment",
                            "LOW",
                            f"An HTML comment contains potentially sensitive information.",
                            f"Comment: {comment_text[:150]}..."
                        )
                        break

        self.log(f"Sensitive data scan complete. {len(self.results['findings'])} issues found.")
        self.finalize()


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanLeaks.py <target_url>", file=sys.stderr)
