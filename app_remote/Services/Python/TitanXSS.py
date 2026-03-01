#!/usr/bin/env python3
"""
TitanXSS - Cross-Site Scripting (XSS) Scanner
Tests for reflected XSS in URL parameters and form inputs.
"""
import sys
import os
import re
import time
from urllib.parse import urlparse, urlencode, parse_qs, urljoin, quote
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # XSS test payloads (harmless canary strings)
    PAYLOADS = [
        # Simple reflection tests
        {'payload': '<script>alert("LUME_XSS_CANARY")</script>', 'check': 'LUME_XSS_CANARY', 'type': 'Reflected Script Tag'},
        {'payload': '"><img src=x onerror=alert(1)>', 'check': 'onerror=alert(1)', 'type': 'Event Handler Injection'},
        {'payload': "'-alert(1)-'", 'check': "'-alert(1)-'", 'type': 'Inline JS Injection'},
        {'payload': '<svg onload=alert(1)>', 'check': 'onload=alert(1)', 'type': 'SVG Event Handler'},
        {'payload': '{{7*7}}', 'check': '49', 'type': 'Template Injection (SSTI)'},
        {'payload': '${7*7}', 'check': '49', 'type': 'Template Literal Injection'},
        {'payload': '<img src="x" onerror="alert(\'XSS\')">', 'check': 'onerror="alert', 'type': 'IMG Tag Event Handler'},
    ]

    # Polyglot payload — tests multiple contexts at once
    POLYGLOT = "jaVasCript:/*-/*`/*\\`/*'/*\"/**/(/* */oNcliCk=alert() )//%%0telerik%%0aonfocusin=alert(1)//autoevents"

    def run(self):
        self.log(f"Starting XSS scan on {self.target_url}")
        
        # Fetch baseline
        resp = self.request(self.target_url)
        if 'error' in resp and 'status' not in resp:
            self.add_finding("Target Unreachable", "CRITICAL", 
                f"Could not connect to {self.target_url}", resp.get('error', ''))
            self.finalize()
            return
            
        body = resp.get('body', '')
        
        # PHASE 1: Check for CSP (affects XSS exploitability)
        headers = resp.get('headers', {})
        has_csp = any('content-security-policy' in k.lower() for k in headers)
        if not has_csp:
            self.add_finding(
                "No Content-Security-Policy (XSS Amplifier)",
                "MEDIUM",
                "No CSP header detected. Any reflected XSS can execute arbitrary JavaScript without script-src restrictions.",
                "Add a strict CSP: Content-Security-Policy: default-src 'self'; script-src 'self'"
            )

        # PHASE 2: Discover input vectors
        self.log("Phase 2: Discovering XSS input vectors...")
        
        # URL parameters
        param_links = re.findall(r'href=["\']([^"\']*\?[^"\']+)["\']', body, re.IGNORECASE)
        
        # Forms
        forms = re.findall(r'<form[^>]*action=["\']?([^"\'>\s]*)["\']?[^>]*>(.*?)</form>', body, re.DOTALL | re.IGNORECASE)
        
        # Search functionality (high XSS probability)
        search_inputs = re.findall(r'<input[^>]*(?:type=["\'](?:search|text)["\'])[^>]*name=["\']([^"\']+)["\']', body, re.IGNORECASE)
        search_inputs += re.findall(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*(?:type=["\'](?:search|text)["\'])', body, re.IGNORECASE)
        
        self.log(f"Found {len(param_links)} param links, {len(forms)} forms, {len(search_inputs)} search inputs")
        
        vectors_tested = 0

        # PHASE 3: Test URL parameters
        self.log("Phase 3: Testing URL parameters for reflected XSS...")
        
        # Test current URL params
        parsed = urlparse(self.target_url)
        if parsed.query:
            params = parse_qs(parsed.query)
            for param_name in params:
                self._test_param_xss(self.target_url, param_name)
                vectors_tested += 1

        # Test discovered links
        seen_params = set()
        for link in param_links[:8]:
            full_url = urljoin(self.target_url, link)
            if urlparse(full_url).netloc == self.domain:
                link_params = parse_qs(urlparse(full_url).query)
                for param_name in link_params:
                    if param_name not in seen_params:
                        seen_params.add(param_name)
                        self._test_param_xss(full_url, param_name)
                        vectors_tested += 1

        # PHASE 4: Test common reflective parameters
        self.log("Phase 4: Testing common reflective parameters...")
        common_params = ['q', 'search', 'query', 'keyword', 's', 'term', 'name', 'user', 'msg', 'message', 'title', 'error', 'redirect', 'url', 'callback', 'next', 'return']
        
        for param in common_params:
            canary = f"LUME_REFLECT_{param}"
            test_url = f"{self.target_url.rstrip('/')}?{param}={canary}"
            resp = self.request(test_url)
            if 'error' in resp and 'status' not in resp:
                continue
            test_body = resp.get('body', '')
            
            # If the canary is reflected, this param is interesting
            if canary in test_body:
                self.log(f"Parameter '{param}' reflects input — testing XSS payloads")
                self._test_param_xss(test_url, param)
                vectors_tested += 1

        # PHASE 5: Test form inputs  
        self.log("Phase 5: Testing form inputs for XSS...")
        for form_action, form_body in forms[:5]:
            form_inputs = re.findall(r'<input[^>]*name=["\']([^"\']+)["\']', form_body, re.IGNORECASE)
            form_url = urljoin(self.target_url, form_action) if form_action else self.target_url
            
            for input_name in form_inputs[:3]:
                self._test_form_xss(form_url, input_name)
                vectors_tested += 1

        # PHASE 6: Check for DOM-based XSS indicators
        self.log("Phase 6: Checking for DOM-based XSS indicators...")
        dom_sinks = [
            (r'\.innerHTML\s*=', 'innerHTML assignment'),
            (r'document\.write\s*\(', 'document.write()'),
            (r'\.outerHTML\s*=', 'outerHTML assignment'),
            (r'eval\s*\(', 'eval()'),
            (r'setTimeout\s*\(\s*["\']', 'setTimeout with string'),
            (r'setInterval\s*\(\s*["\']', 'setInterval with string'),
            (r'location\s*=', 'location assignment'),
            (r'window\.location\.href\s*=', 'window.location.href assignment'),
        ]
        
        for pattern, sink_name in dom_sinks:
            if re.search(pattern, body):
                # Check if user-controllable sources feed into these sinks
                sources = ['location.hash', 'location.search', 'location.href', 'document.URL', 'document.referrer', 'window.name']
                for source in sources:
                    if source in body:
                        self.add_finding(
                            f"Potential DOM-Based XSS: {sink_name}",
                            "HIGH",
                            f"Found dangerous sink '{sink_name}' in JavaScript code, and user-controllable source '{source}' is also present. This combination may allow DOM-based XSS.",
                            f"Sink: {sink_name} | Source: {source}"
                        )
                        break

        if vectors_tested == 0:
            self.add_finding(
                "No Reflective Parameters Found",
                "INFO",
                "No URL parameters or form inputs reflecting user input were discovered.",
                "The application may use API-based rendering which requires different XSS testing methodology."
            )

        self.log(f"XSS scan complete. Tested {vectors_tested} vectors. {len(self.results['findings'])} issues found.")
        self.finalize()

    def _test_param_xss(self, url, param_name):
        """Test a URL parameter for reflected XSS"""
        parsed = urlparse(url)
        base_url = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        
        for test in self.PAYLOADS[:5]:
            test_url = f"{base_url}?{param_name}={quote(test['payload'])}"
            try:
                resp = self.request(test_url)
                if 'error' in resp and 'status' not in resp:
                    continue
                response_body = resp.get('body', '')
                
                if test['check'] in response_body:
                    self.add_finding(
                        f"Reflected XSS: {param_name} ({test['type']})",
                        "HIGH",
                        f"XSS payload was reflected unescaped in the response for parameter '{param_name}' at {base_url}. The payload type is: {test['type']}.",
                        f"Payload: {test['payload'][:80]} | Reflected check: {test['check']}"
                    )
                    return  # One finding per param
            except Exception as e:
                self.log(f"Error testing XSS on {param_name}: {str(e)}")
            time.sleep(0.2)

    def _test_form_xss(self, form_url, input_name):
        """Test a form field for reflected XSS"""
        for test in self.PAYLOADS[:3]:
            data = urlencode({input_name: test['payload']}).encode('utf-8')
            try:
                resp = self.request(form_url, method='POST', data=data, 
                                   headers={'Content-Type': 'application/x-www-form-urlencoded'})
                if 'error' in resp and 'status' not in resp:
                    continue
                response_body = resp.get('body', '')
                
                if test['check'] in response_body:
                    self.add_finding(
                        f"Reflected XSS in Form: {input_name} ({test['type']})",
                        "HIGH",
                        f"XSS payload submitted to form field '{input_name}' at {form_url} was reflected without sanitization.",
                        f"Payload: {test['payload'][:80]} | Method: POST"
                    )
                    return
            except Exception as e:
                self.log(f"Error testing form XSS: {str(e)}")
            time.sleep(0.2)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanXSS.py <target_url>", file=sys.stderr)
