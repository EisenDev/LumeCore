#!/usr/bin/env python3
"""
TitanIDOR - Insecure Direct Object Reference Scanner
Tests for IDOR by manipulating ID parameters to detect unauthorized data access.
"""
import sys
import os
import re
import time
from urllib.parse import urlparse, urlencode, parse_qs, urljoin
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # Common IDOR parameter names
    IDOR_PARAMS = [
        'id', 'user_id', 'userId', 'uid', 'account_id', 'accountId',
        'profile_id', 'profileId', 'order_id', 'orderId', 'doc_id',
        'document_id', 'file_id', 'fileId', 'invoice_id', 'invoiceId',
        'ticket_id', 'ticketId', 'item_id', 'itemId', 'product_id',
        'ref', 'reference', 'num', 'number',
    ]

    # Common API patterns that are IDOR-prone
    API_PATTERNS = [
        'api/users/{id}', 'api/user/{id}', 'api/accounts/{id}',
        'api/orders/{id}', 'api/invoices/{id}', 'api/documents/{id}',
        'api/files/{id}', 'api/profiles/{id}', 'api/tickets/{id}',
        'users/{id}', 'user/{id}', 'profile/{id}', 'account/{id}',
        'order/{id}', 'invoice/{id}', 'file/{id}', 'download/{id}',
    ]

    def run(self):
        self.log(f"Starting IDOR analysis on {self.target_url}")
        
        # PHASE 1: Scan homepage for links with IDs
        self.log("Phase 1: Discovering potential IDOR endpoints...")
        
        resp = self.request(self.target_url)
        if 'error' in resp and 'status' not in resp:
            self.add_finding("Target Unreachable", "CRITICAL",
                f"Could not connect to {self.target_url}", resp.get('error', ''))
            self.finalize()
            return
        
        body = resp.get('body', '')
        
        # Find all links with numeric IDs in them
        id_links = re.findall(r'href=["\']([^"\']*(?:\?[^"\']*(?:id|user|account|order|file|doc|invoice|profile)\w*=\d+)[^"\']*)["\']', body, re.IGNORECASE)
        
        # Find API-like URLs in JavaScript
        api_urls = re.findall(r'["\'](?:https?://[^"\']*|/api/[^"\']*)["\']', body)
        
        # Find URLs with numeric path segments (e.g., /users/123)
        path_id_links = re.findall(r'href=["\']([^"\']*\/\d+[^"\']*)["\']', body, re.IGNORECASE)
        
        self.log(f"Found {len(id_links)} ID-parameterized links, {len(api_urls)} API URLs, {len(path_id_links)} path-ID links")
        
        vectors_tested = 0

        # PHASE 2: Test URL parameter-based IDOR
        self.log("Phase 2: Testing parameter-based IDOR...")
        
        tested_urls = set()
        for link in id_links[:10]:
            full_url = urljoin(self.target_url, link)
            if urlparse(full_url).netloc != self.domain:
                continue
            if full_url in tested_urls:
                continue
            tested_urls.add(full_url)
            
            parsed = urlparse(full_url)
            params = parse_qs(parsed.query)
            
            for param, values in params.items():
                if any(id_name in param.lower() for id_name in ['id', 'user', 'account', 'order', 'doc', 'file', 'invoice', 'profile', 'ref', 'num']):
                    original_value = values[0]
                    self._test_idor_param(full_url, param, original_value)
                    vectors_tested += 1

        # PHASE 3: Test common IDOR parameter names on the target
        self.log("Phase 3: Probing common IDOR parameter names...")
        
        for param in self.IDOR_PARAMS:
            # Test with ID=1 (usually admin or first user) 
            test_url = f"{self.target_url.rstrip('/')}?{param}=1"
            resp = self.request(test_url)
            
            if 'error' in resp and 'status' not in resp:
                continue
            
            status = resp.get('status', 0)
            resp_body = resp.get('body', '')
            
            # If we get a 200 with meaningful content, test sequential IDs
            if status == 200 and len(resp_body) > 100:
                # Check if changing the ID gives different content (= IDOR)
                self._test_idor_param(test_url, param, '1')
                vectors_tested += 1
            
            time.sleep(0.2)

        # PHASE 4: Test path-based IDOR (e.g., /api/users/1 vs /api/users/2)
        self.log("Phase 4: Testing API path-based IDOR...")
        
        for pattern in self.API_PATTERNS:
            base_path = pattern.replace('{id}', '')
            url_1 = f"{self.target_url.rstrip('/')}/{pattern.replace('{id}', '1')}"
            
            resp1 = self.request(url_1)
            if 'error' in resp1 and 'status' not in resp1:
                continue
            
            status = resp1.get('status', 0)
            if status == 200:
                body1 = resp1.get('body', '')
                
                # Try ID 2
                url_2 = f"{self.target_url.rstrip('/')}/{pattern.replace('{id}', '2')}"
                resp2 = self.request(url_2)
                
                if 'status' in resp2 and resp2.get('status') == 200:
                    body2 = resp2.get('body', '')
                    
                    # Different content = we're accessing different records
                    if body1 != body2 and len(body1) > 50 and len(body2) > 50:
                        # Check if it looks like user data
                        has_pii = any(re.search(p, body1, re.IGNORECASE) for p in [
                            r'"email"', r'"phone"', r'"address"', r'"name"',
                            r'"username"', r'"password"', r'"ssn"',
                        ])
                        
                        severity = 'CRITICAL' if has_pii else 'HIGH'
                        
                        self.add_finding(
                            f"IDOR: /{pattern}",
                            severity,
                            f"Sequential ID access works on endpoint /{base_path}. IDs 1 and 2 return different data without authentication. {'Contains PII-like fields.' if has_pii else ''}",
                            f"URL: {url_1} returned {len(body1)} bytes, {url_2} returned {len(body2)} bytes"
                        )
                        vectors_tested += 1
            
            time.sleep(0.2)

        # PHASE 5: Check for predictable resource URLs
        self.log("Phase 5: Checking for predictable file/resource access...")
        predictable_paths = [
            'uploads/1', 'files/1', 'documents/1', 'images/1',
            'download?file=1', 'export?id=1', 'report?id=1',
        ]
        
        for path in predictable_paths:
            url = f"{self.target_url.rstrip('/')}/{path}"
            resp = self.request(url)
            
            if 'error' in resp and 'status' not in resp:
                continue
            
            if resp.get('status') == 200 and len(resp.get('body', '')) > 100:
                self.add_finding(
                    f"Predictable Resource URL: /{path}",
                    "MEDIUM",
                    f"The path /{path} returns content with a sequential/predictable ID. An attacker could enumerate and access other users' files.",
                    f"HTTP 200 | Content: {len(resp.get('body', ''))} bytes"
                )
                vectors_tested += 1

        if vectors_tested == 0:
            self.add_finding(
                "No IDOR Vectors Discovered",
                "INFO",
                "No obvious IDOR-prone endpoints were found. The application may use UUIDs or session-bound access control.",
                "Manual testing with authenticated sessions recommended for deeper IDOR analysis."
            )

        self.log(f"IDOR scan complete. Tested {vectors_tested} vectors. {len(self.results['findings'])} findings.")
        self.finalize()

    def _test_idor_param(self, url, param, original_value):
        """Test if changing an ID parameter returns different user data"""
        parsed = urlparse(url)
        base_url = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        
        # Get response for original ID
        resp1 = self.request(url)
        if 'error' in resp1 and 'status' not in resp1:
            return
        body1 = resp1.get('body', '')
        
        # Try adjacent IDs
        try:
            original_int = int(original_value)
            test_ids = [original_int + 1, original_int - 1, original_int + 100]
        except ValueError:
            test_ids = [1, 2, 999]
        
        for test_id in test_ids:
            test_url = f"{base_url}?{param}={test_id}"
            resp2 = self.request(test_url)
            
            if 'error' in resp2 and 'status' not in resp2:
                continue
            
            if resp2.get('status') == 200:
                body2 = resp2.get('body', '')
                
                # Different content with different IDs = potential IDOR
                if body1 != body2 and len(body2) > 50 and abs(len(body1) - len(body2)) < len(body1):
                    self.add_finding(
                        f"Potential IDOR: parameter '{param}'",
                        "HIGH",
                        f"Changing {param} from {original_value} to {test_id} returned different content of similar size. This suggests unauthenticated access to different records.",
                        f"Original ({original_value}): {len(body1)} bytes | Modified ({test_id}): {len(body2)} bytes | URL: {base_url}"
                    )
                    return
            
            time.sleep(0.2)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanIDOR.py <target_url>", file=sys.stderr)
