#!/usr/bin/env python3
"""
TitanDirFuzz - Directory and File Discovery Scanner
Discovers hidden directories, backup files, admin panels, and debug endpoints.
"""
import sys
import os
import re
import time
from urllib.parse import urljoin
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # Directory/file wordlist organized by category
    WORDLIST = {
        'Admin Panels': [
            'admin', 'admin/', 'administrator', 'admin/login', 'admin.php',
            'wp-admin', 'wp-login.php', 'cpanel', 'dashboard', 'manage',
            'panel', 'control', 'backend', 'admin/dashboard', 'login',
            'auth/login', 'signin', 'auth/signin', 'cms', 'console',
            'webadmin', 'sysadmin', 'admin-panel',
        ],
        'API Endpoints': [
            'api', 'api/', 'api/v1', 'api/v2', 'api/v3',
            'api/users', 'api/config', 'api/debug',
            'api/health', 'api/status', 'api/docs',
            'graphql', 'graphiql', 'playground',
            'swagger', 'swagger-ui', 'api-docs',
            'openapi.json', 'swagger.json', 'swagger.yaml',
        ],
        'Debug/Dev': [
            'debug', 'debug/', 'phpinfo.php', 'info.php',
            'test', 'test.php', 'test.html', 'testing',
            '_debug', '__debug__', 'debug/default/view',
            'elmah.axd', 'trace.axd',
            'actuator', 'actuator/env', 'actuator/health',
            'telescope', 'horizon', '_profiler',
            'server-status', 'server-info',
            'metrics', 'prometheus', 'health',
        ],
        'Backup Files': [
            'backup', 'backups', 'backup.zip', 'backup.tar.gz',
            'site.zip', 'site.tar.gz', 'www.zip',
            'db.sql', 'database.sql', 'dump.sql', 'backup.sql',
            'old', 'archive', 'temp', 'tmp',
            'index.php.bak', 'index.php~', 'index.php.old',
            'web.config.bak', '.DS_Store',
        ],
        'Version Control': [
            '.git', '.git/', '.git/HEAD', '.git/config',
            '.svn', '.svn/', '.svn/entries',
            '.hg', '.hg/', '.bzr',
            '.gitignore', '.gitattributes',
        ],
        'Config Files': [
            '.env', '.env.backup', '.env.local', '.env.production',
            'config.php', 'settings.php', 'database.yml',
            'wp-config.php', 'configuration.php', 'LocalSettings.php',
            'Web.config', 'web.config', 'app.config',
        ],
        'Common Frameworks': [
            'readme.txt', 'README.md', 'CHANGELOG.md', 'LICENSE',
            'package.json', 'composer.json', 'Gemfile',
            'requirements.txt', 'Pipfile',
            'artisan', 'manage.py', 'Rakefile',
        ],
    }

    def run(self):
        self.log(f"Starting directory/file discovery on {self.target_url}")
        
        # First get a baseline 404 response to avoid false positives
        resp_404 = self.request(f"{self.target_url.rstrip('/')}/lume_nonexistent_path_xyz_123")
        baseline_404_length = len(resp_404.get('body', ''))
        baseline_404_status = resp_404.get('status', 0)
        
        self.log(f"404 baseline: HTTP {baseline_404_status}, {baseline_404_length} chars")
        
        total_probed = 0
        discovered = []

        for category, paths in self.WORDLIST.items():
            self.log(f"Scanning category: {category} ({len(paths)} paths)...")
            
            for path in paths:
                url = f"{self.target_url.rstrip('/')}/{path}"
                
                try:
                    resp = self.request(url)
                    if 'error' in resp and 'status' not in resp:
                        continue
                    
                    status = resp.get('status', 0)
                    body = resp.get('body', '')
                    body_length = len(body)
                    total_probed += 1
                    
                    # Skip obvious 404s
                    if status == 404:
                        continue
                    
                    # Skip soft 404s (same content as our known 404)
                    if baseline_404_length > 0 and abs(body_length - baseline_404_length) < 50:
                        continue
                    
                    # Interesting findings: 200, 301, 302, 403
                    if status in (200, 301, 302, 403):
                        severity = self._assess_severity(path, category, status, body)
                        details = f"HTTP {status} | Size: {body_length} bytes"
                        
                        if status == 403:
                            description = f"Access denied to /{path}. The path EXISTS but is restricted (HTTP 403). An attacker knows this path is real and may attempt to bypass access controls."
                            details += " | Status: Access Denied (exists but restricted)"
                        elif status in (301, 302):
                            redirect_to = resp.get('headers', {}).get('Location', resp.get('headers', {}).get('location', 'unknown'))
                            description = f"The path /{path} redirects (HTTP {status}), indicating it exists. Redirect target: {redirect_to}"
                            details += f" | Redirects to: {redirect_to}"
                        else:
                            description = f"The path /{path} is publicly accessible (HTTP 200). Category: {category}."
                            # Add content preview for critical files
                            if severity in ('CRITICAL', 'HIGH'):
                                preview = body[:100].replace('\n', ' ').strip()
                                details += f" | Preview: {preview}"
                        
                        self.add_finding(
                            f"Discovered: /{path}",
                            severity,
                            description,
                            details
                        )
                        discovered.append(f"/{path} ({status})")
                
                except Exception as e:
                    self.log(f"Error probing {path}: {str(e)}")
                
                time.sleep(0.15)  # Rate limiting
        
        if not discovered:
            self.add_finding(
                "No Hidden Paths Discovered",
                "INFO",
                f"Probed {total_probed} common paths but found no accessible endpoints beyond the main page.",
                "The server has good path security or uses non-standard paths."
            )
        
        self.log(f"Directory scan complete. Probed {total_probed} paths. Discovered: {len(discovered)}. Findings: {len(self.results['findings'])}")
        self.finalize()

    def _assess_severity(self, path, category, status, body):
        """Determine the severity based on what was found"""
        path_lower = path.lower()
        
        # Critical: exposed configs, databases, version control
        if any(x in path_lower for x in ['.env', '.git/config', 'wp-config', 'database.sql', 'dump.sql', 'backup.sql', '.htpasswd']):
            return 'CRITICAL'
        
        # High: admin panels, debug endpoints, API docs
        if category in ('Admin Panels', 'Debug/Dev'):
            if status == 200:
                return 'HIGH'
            return 'MEDIUM'
        
        if category == 'API Endpoints':
            if 'swagger' in path_lower or 'graphiql' in path_lower or 'playground' in path_lower or 'api-docs' in path_lower:
                return 'HIGH'
            return 'MEDIUM'
        
        # Medium: backup files, version control markers
        if category in ('Backup Files', 'Version Control'):
            return 'HIGH' if status == 200 else 'MEDIUM'
        
        # Low: readme, package.json, etc
        if category == 'Common Frameworks':
            return 'LOW'
        
        return 'MEDIUM'


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanDirFuzz.py <target_url>", file=sys.stderr)
