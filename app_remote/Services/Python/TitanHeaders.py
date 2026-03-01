#!/usr/bin/env python3
"""
TitanHeaders - Security Header Analyzer
Checks for missing or misconfigured HTTP security headers.
"""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):
    
    REQUIRED_HEADERS = {
        'Content-Security-Policy': {
            'severity': 'MEDIUM',
            'description': 'Content-Security-Policy (CSP) header is missing. This allows attackers to inject malicious scripts via XSS.',
            'fix': "Implement a strict CSP header. Example: Content-Security-Policy: default-src 'self'; script-src 'self'"
        },
        'Strict-Transport-Security': {
            'severity': 'HIGH',
            'description': 'HSTS header is missing. The site may be vulnerable to SSL stripping attacks (downgrade from HTTPS to HTTP).',
            'fix': 'Add header: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload'
        },
        'X-Frame-Options': {
            'severity': 'MEDIUM',
            'description': 'X-Frame-Options header is missing. The site may be vulnerable to clickjacking attacks via iframe embedding.',
            'fix': "Add header: X-Frame-Options: DENY or SAMEORIGIN"
        },
        'X-Content-Type-Options': {
            'severity': 'LOW',
            'description': 'X-Content-Type-Options header is missing. Browsers may MIME-sniff responses, leading to XSS via content type confusion.',
            'fix': 'Add header: X-Content-Type-Options: nosniff'
        },
        'Referrer-Policy': {
            'severity': 'LOW',
            'description': 'Referrer-Policy header is missing. Sensitive URL parameters may leak to third-party sites via the Referer header.',
            'fix': 'Add header: Referrer-Policy: strict-origin-when-cross-origin'
        },
        'Permissions-Policy': {
            'severity': 'LOW',
            'description': 'Permissions-Policy (formerly Feature-Policy) header is missing. Browser features like camera, microphone, geolocation are unrestricted.',
            'fix': 'Add header: Permissions-Policy: camera=(), microphone=(), geolocation=()'
        },
    }

    DANGEROUS_HEADERS = {
        'X-Powered-By': {
            'severity': 'LOW',
            'description': 'X-Powered-By header exposes the backend technology. Attackers use this for targeted exploits.',
        },
        'Server': {
            'severity': 'LOW',
            'check_version': True,
            'description': 'Server header reveals the web server software and version. This aids reconnaissance.',
        },
        'X-AspNet-Version': {
            'severity': 'MEDIUM',
            'description': 'X-AspNet-Version header exposes the .NET framework version, enabling version-specific attacks.',
        },
    }

    def run(self):
        self.log(f"Starting security header analysis on {self.target_url}")
        
        # Fetch the target
        resp = self.request(self.target_url)
        if 'error' in resp and 'status' not in resp:
            self.add_finding(
                "Target Unreachable",
                "CRITICAL",
                f"Could not connect to {self.target_url}: {resp.get('error', 'Unknown error')}",
                "Ensure the target URL is accessible."
            )
            self.finalize()
            return

        headers = resp.get('headers', {})
        status = resp.get('status', 0)
        self.log(f"Received HTTP {status} with {len(headers)} headers")

        # Normalize header keys to title case for comparison
        normalized = {k.lower(): v for k, v in headers.items()}

        # Check for MISSING security headers
        for header_name, info in self.REQUIRED_HEADERS.items():
            if header_name.lower() not in normalized:
                self.add_finding(
                    f"Missing {header_name}",
                    info['severity'],
                    info['description'],
                    info['fix']
                )
            else:
                self.log(f"[OK] {header_name} is present: {normalized[header_name.lower()]}")

        # Check for DANGEROUS information leakage headers
        for header_name, info in self.DANGEROUS_HEADERS.items():
            if header_name.lower() in normalized:
                value = normalized[header_name.lower()]
                self.add_finding(
                    f"Information Leakage ({header_name})",
                    info['severity'],
                    f"{info['description']} Value: {value}",
                    f"Remove or obfuscate the {header_name} header in your web server configuration."
                )

        # Check HTTPS
        if self.target_url.startswith('http://'):
            self.add_finding(
                "No HTTPS",
                "HIGH",
                "The target URL uses HTTP instead of HTTPS. All traffic is transmitted in cleartext.",
                "Enable HTTPS with a valid TLS certificate (e.g., Let's Encrypt)."
            )

        # Check for insecure cookie flags
        set_cookie = normalized.get('set-cookie', '')
        if set_cookie:
            if 'secure' not in set_cookie.lower():
                self.add_finding(
                    "Cookie Missing Secure Flag",
                    "MEDIUM",
                    "Cookies are set without the 'Secure' flag. They can be transmitted over unencrypted HTTP connections.",
                    "Add the 'Secure' flag to all Set-Cookie headers."
                )
            if 'httponly' not in set_cookie.lower():
                self.add_finding(
                    "Cookie Missing HttpOnly Flag",
                    "MEDIUM",
                    "Cookies are set without the 'HttpOnly' flag. JavaScript can access these cookies, enabling XSS-based session theft.",
                    "Add the 'HttpOnly' flag to all session cookies."
                )
            if 'samesite' not in set_cookie.lower():
                self.add_finding(
                    "Cookie Missing SameSite Flag",
                    "LOW",
                    "Cookies are set without the 'SameSite' attribute. This may allow CSRF attacks.",
                    "Add SameSite=Lax or SameSite=Strict to all cookies."
                )

        self.log(f"Header analysis complete. {len(self.results['findings'])} issues found.")
        self.finalize()


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanHeaders.py <target_url>", file=sys.stderr)
