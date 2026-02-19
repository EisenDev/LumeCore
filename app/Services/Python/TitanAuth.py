#!/usr/bin/env python3
"""
TitanAuth - Authentication & Session Security Scanner
Tests for weak authentication, session management, and access control issues.
"""
import sys
import os
import re
import time
from urllib.parse import urljoin, urlparse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # Common login paths to discover
    LOGIN_PATHS = [
        'login', 'signin', 'sign-in', 'auth/login', 'auth/signin',
        'admin/login', 'wp-login.php', 'user/login', 'account/login',
        'api/auth/login', 'api/login',
    ]

    # Common default credential pairs
    DEFAULT_CREDS = [
        ('admin', 'admin'),
        ('admin', 'password'),
        ('admin', '123456'),
        ('root', 'root'),
        ('test', 'test'),
        ('admin', 'admin123'),
        ('administrator', 'administrator'),
    ]

    def run(self):
        self.log(f"Starting authentication & session analysis on {self.target_url}")
        
        # PHASE 1: Discover login pages
        self.log("Phase 1: Discovering authentication endpoints...")
        login_url = None
        
        for path in self.LOGIN_PATHS:
            url = f"{self.target_url.rstrip('/')}/{path}"
            resp = self.request(url)
            
            if 'error' in resp and 'status' not in resp:
                continue
                
            status = resp.get('status', 0)
            body = resp.get('body', '')
            
            if status == 200 and self._looks_like_login(body):
                login_url = url
                self.log(f"Found login page at: {url}")
                break
            elif status in (301, 302):
                redirect = resp.get('headers', {}).get('Location', resp.get('headers', {}).get('location', ''))
                if redirect and ('login' in redirect.lower() or 'signin' in redirect.lower()):
                    login_url = urljoin(url, redirect)
                    self.log(f"Found login redirect to: {login_url}")
                    break
        
        # Also check from the homepage
        if not login_url:
            resp = self.request(self.target_url)
            body = resp.get('body', '') if 'status' in resp else ''
            login_links = re.findall(r'href=["\']([^"\']*(?:login|signin|sign-in|auth)[^"\']*)["\']', body, re.IGNORECASE)
            if login_links:
                login_url = urljoin(self.target_url, login_links[0])
                self.log(f"Found login link in homepage: {login_url}")

        # PHASE 2: Analyze login page security
        if login_url:
            self.log(f"Phase 2: Analyzing login page: {login_url}")
            resp = self.request(login_url)
            
            if 'status' in resp:
                body = resp.get('body', '')
                headers = resp.get('headers', {})
                
                # Check HTTPS
                if login_url.startswith('http://'):
                    self.add_finding(
                        "Login Page Over HTTP",
                        "CRITICAL",
                        "The login page is served over unencrypted HTTP. Credentials are transmitted in plaintext and can be intercepted.",
                        f"Login URL: {login_url}"
                    )
                
                # Check for CSRF protection
                has_csrf = bool(re.search(r'(?:csrf|_token|authenticity_token|__RequestVerificationToken)', body, re.IGNORECASE))
                if not has_csrf:
                    self.add_finding(
                        "No CSRF Protection on Login",
                        "HIGH",
                        "The login form does not appear to have CSRF token protection. This may allow cross-site login attacks.",
                        "No hidden input with csrf/token name found in the form."
                    )
                
                # Check for autocomplete
                if not re.search(r'autocomplete\s*=\s*["\']off["\']', body, re.IGNORECASE):
                    self.add_finding(
                        "Password Autocomplete Enabled",
                        "LOW",
                        "The login form does not disable autocomplete for password fields. Saved credentials could be accessed by anyone with physical access.",
                        "Add autocomplete='off' to the password input field."
                    )
                
                # Check for rate limiting headers
                rate_limit_headers = ['x-ratelimit-limit', 'x-ratelimit-remaining', 'retry-after', 'x-rate-limit']
                has_rate_limit = any(h.lower() in [k.lower() for k in headers] for h in rate_limit_headers)
                
                # PHASE 3: Test for brute force protection
                self.log("Phase 3: Testing brute force protection...")
                self._test_brute_force_protection(login_url, body)
                
                # PHASE 4: Test for username enumeration
                self.log("Phase 4: Testing for username enumeration...")
                self._test_username_enumeration(login_url, body)
                
        else:
            self.log("No login page discovered. Skipping authentication-specific tests.")
            self.add_finding(
                "No Login Page Found",
                "INFO",
                "Could not discover a login page. Authentication testing was limited.",
                "Manually provide the login URL for deeper testing."
            )
        
        # PHASE 5: Session & Cookie security (applies to whole site)
        self.log("Phase 5: Analyzing session and cookie security...")
        resp = self.request(self.target_url)
        if 'status' in resp:
            headers = resp.get('headers', {})
            self._analyze_cookies(headers)
        
        # PHASE 6: Check for common auth bypasses
        self.log("Phase 6: Testing common authentication bypasses...")
        self._test_auth_bypass()
        
        self.log(f"Authentication scan complete. {len(self.results['findings'])} issues found.")
        self.finalize()

    def _looks_like_login(self, body):
        """Check if a page looks like a login form"""
        indicators = [
            r'<input[^>]*type=["\']password["\']',
            r'(?:sign\s*in|log\s*in|login|authenticate)',
            r'<form[^>]*>.*?password.*?</form>',
        ]
        score = sum(1 for pattern in indicators if re.search(pattern, body, re.IGNORECASE | re.DOTALL))
        return score >= 2

    def _test_brute_force_protection(self, login_url, form_body):
        """Test if the login allows rapid repeated attempts"""
        # Find form fields
        password_field = re.search(r'<input[^>]*type=["\']password["\'][^>]*name=["\']([^"\']+)', form_body, re.IGNORECASE)
        username_field = re.search(r'<input[^>]*(?:type=["\'](?:text|email)["\'])[^>]*name=["\']([^"\']+)', form_body, re.IGNORECASE)
        
        if not password_field or not username_field:
            # Try reverse attribute order
            username_field = re.search(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*type=["\'](?:text|email)["\']', form_body, re.IGNORECASE)
            password_field = re.search(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*type=["\']password["\']', form_body, re.IGNORECASE)
        
        if not password_field or not username_field:
            self.log("Could not identify login form fields for brute force test")
            return
        
        uname = username_field.group(1)
        pword = password_field.group(1)
        
        # Extract CSRF token if present
        csrf = re.search(r'<input[^>]*name=["\']([^"\']*(?:csrf|_token|token)[^"\']*)["\'][^>]*value=["\']([^"\']*)["\']', form_body, re.IGNORECASE)
        
        # Send 5 rapid login attempts
        blocked = False
        for i in range(5):
            data = {uname: 'lume_test_nonexistent_user', pword: 'wrong_password_test'}
            if csrf:
                data[csrf.group(1)] = csrf.group(2)
            
            from urllib.parse import urlencode
            encoded = urlencode(data).encode('utf-8')
            resp = self.request(login_url, method='POST', data=encoded,
                              headers={'Content-Type': 'application/x-www-form-urlencoded'})
            
            if 'status' in resp:
                status = resp.get('status', 200)
                if status == 429 or status == 403:
                    blocked = True
                    break
                    
                body = resp.get('body', '')
                if re.search(r'(?:too many|rate limit|locked|blocked|captcha|recaptcha)', body, re.IGNORECASE):
                    blocked = True
                    break
            
            time.sleep(0.3)
        
        if not blocked:
            self.add_finding(
                "No Brute Force Protection",
                "HIGH",
                "The login endpoint allowed 5 rapid failed login attempts without any rate limiting, account lockout, or CAPTCHA challenge.",
                f"Login URL: {login_url} | 5 consecutive failed attempts were accepted"
            )

    def _test_username_enumeration(self, login_url, form_body):
        """Test if the login reveals whether a username exists"""
        password_field = re.search(r'<input[^>]*(?:type=["\']password["\'])[^>]*name=["\']([^"\']+)', form_body, re.IGNORECASE)
        username_field = re.search(r'<input[^>]*(?:type=["\'](?:text|email)["\'])[^>]*name=["\']([^"\']+)', form_body, re.IGNORECASE)
        
        if not password_field or not username_field:
            password_field = re.search(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*type=["\']password["\']', form_body, re.IGNORECASE)
            username_field = re.search(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*type=["\'](?:text|email)["\']', form_body, re.IGNORECASE)
        
        if not password_field or not username_field:
            return
            
        uname = username_field.group(1)
        pword = password_field.group(1)
        
        from urllib.parse import urlencode
        
        # Try with likely-invalid username
        data1 = urlencode({uname: 'lume_definitely_not_a_real_user_xyz', pword: 'wrong'}).encode('utf-8')
        resp1 = self.request(login_url, method='POST', data=data1,
                            headers={'Content-Type': 'application/x-www-form-urlencoded'})
        
        # Try with common username
        data2 = urlencode({uname: 'admin', pword: 'wrong_password_lume_test'}).encode('utf-8')
        resp2 = self.request(login_url, method='POST', data=data2,
                            headers={'Content-Type': 'application/x-www-form-urlencoded'})
        
        if 'status' in resp1 and 'status' in resp2:
            body1 = resp1.get('body', '')
            body2 = resp2.get('body', '')
            
            # Different error messages = username enumeration
            if abs(len(body1) - len(body2)) > 20:
                self.add_finding(
                    "Username Enumeration Possible",
                    "MEDIUM",
                    "The login page returns different responses for valid vs. invalid usernames, allowing attackers to enumerate existing accounts.",
                    f"Response size difference: {abs(len(body1) - len(body2))} bytes between known-bad and 'admin' username"
                )

    def _analyze_cookies(self, headers):
        """Analyze cookie security settings"""
        cookie_headers = []
        for k, v in headers.items():
            if k.lower() == 'set-cookie':
                cookie_headers.append(v)
        
        if not cookie_headers:
            return
        
        for cookie in cookie_headers:
            cookie_name = cookie.split('=')[0].strip() if '=' in cookie else 'unknown'
            cookie_lower = cookie.lower()
            
            if 'secure' not in cookie_lower:
                self.add_finding(
                    f"Cookie Missing Secure Flag: {cookie_name}",
                    "MEDIUM",
                    f"Cookie '{cookie_name}' is set without the Secure flag. It can be transmitted over unencrypted HTTP.",
                    "Add the 'Secure' flag to this cookie."
                )
            
            if 'httponly' not in cookie_lower and any(x in cookie_name.lower() for x in ['session', 'sess', 'sid', 'token', 'auth']):
                self.add_finding(
                    f"Session Cookie Missing HttpOnly: {cookie_name}",
                    "HIGH",
                    f"Session cookie '{cookie_name}' is missing the HttpOnly flag. JavaScript (and XSS) can access this cookie.",
                    "Add HttpOnly flag to session cookies."
                )
            
            if 'samesite' not in cookie_lower:
                self.add_finding(
                    f"Cookie Missing SameSite: {cookie_name}",
                    "LOW",
                    f"Cookie '{cookie_name}' has no SameSite attribute. May be vulnerable to CSRF.",
                    "Add SameSite=Lax or SameSite=Strict."
                )

    def _test_auth_bypass(self):
        """Test for common authentication bypass paths"""
        bypass_paths = [
            ('admin', 'Admin Panel Direct Access'),
            ('admin/dashboard', 'Admin Dashboard Direct Access'),
            ('dashboard', 'Dashboard Without Auth'),
            ('api/users', 'User List API Exposure'),
            ('api/admin', 'Admin API Exposure'),
            ('internal', 'Internal Endpoint'),
        ]
        
        for path, title in bypass_paths:
            url = f"{self.target_url.rstrip('/')}/{path}"
            resp = self.request(url)
            
            if 'error' in resp and 'status' not in resp:
                continue
            
            status = resp.get('status', 200)
            body = resp.get('body', '')
            
            # Check if the response contains admin-like content without redirect to login
            if status == 200 and len(body) > 200:
                if not re.search(r'(?:login|signin|sign.in|authenticate)', body[:1000], re.IGNORECASE):
                    if re.search(r'(?:dashboard|admin|settings|users|analytics|reports)', body[:2000], re.IGNORECASE):
                        self.add_finding(
                            f"Possible Auth Bypass: {title}",
                            "HIGH",
                            f"The path /{path} returned content that appears to be an authenticated page without requiring login.",
                            f"HTTP {status} | Content length: {len(body)} bytes"
                        )


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanAuth.py <target_url>", file=sys.stderr)
