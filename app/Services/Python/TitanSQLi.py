#!/usr/bin/env python3
"""
TitanSQLi - SQL Injection Vector Scanner
Identifies potential SQL injection points by testing URL parameters and form inputs.
"""
import sys
import os
import re
import json
import time
from urllib.parse import urlparse, urlencode, parse_qs, urljoin
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase


class Agent(TitanBase):

    # Classic SQLi test payloads (non-destructive)
    PAYLOADS = [
        "'",
        "''",
        "' OR '1'='1",
        "' OR '1'='1' --",
        "' OR '1'='1' #",
        "1' ORDER BY 1--",
        "1' ORDER BY 100--",
        "1 AND 1=1",
        "1 AND 1=2",
        "' UNION SELECT NULL--",
        "1; WAITFOR DELAY '0:0:3'--",
        "1' AND SLEEP(3)--",
    ]

    # SQL error signatures in response (indicates reflected errors = potential injection)
    ERROR_SIGNATURES = [
        # MySQL
        r'you have an error in your sql syntax',
        r'warning:.*mysql',
        r'mysql_fetch',
        r'mysql_num_rows',
        r'mysql_query',
        r'MySqlException',
        # PostgreSQL
        r'pg_query',
        r'pg_exec',
        r'PSQLException',
        r'ERROR:\s+syntax error at or near',
        r'unterminated.*string',
        # MSSQL
        r'microsoft.*odbc.*sql',
        r'microsoft.*ole db.*sql',
        r'\bOLE DB\b.*error',
        r'unclosed quotation mark',
        r'mssql_query',
        # SQLite
        r'sqlite.*error',
        r'sqlite3\.OperationalError',
        r'unrecognized token',
        # Oracle
        r'ORA-\d{4,5}',
        r'oracle.*error',
        r'quoted string not properly terminated',
        # Generic
        r'SQL syntax.*error',
        r'sql error',
        r'syntax error.*sql',
        r'invalid.*query',
        r'SQLSTATE\[',
    ]

    def run(self):
        self.log(f"Starting SQL injection scan on {self.target_url}")
        
        # PHASE 1: Scan homepage for forms and links with parameters
        self.log("Phase 1: Discovering input vectors...")
        
        resp = self.request(self.target_url)
        if 'error' in resp and 'status' not in resp:
            self.add_finding(
                "Target Unreachable",
                "CRITICAL",
                f"Could not connect to {self.target_url}",
                resp.get('error', 'Connection failed')
            )
            self.finalize()
            return
            
        body = resp.get('body', '')
        baseline_length = len(body)
        
        # Find forms
        forms = re.findall(r'<form[^>]*action=["\']([^"\']*)["\'][^>]*>(.*?)</form>', body, re.DOTALL | re.IGNORECASE)
        inputs_in_page = re.findall(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*>', body, re.IGNORECASE)
        
        # Find URLs with parameters
        param_links = re.findall(r'href=["\']([^"\']*\?[^"\']+)["\']', body, re.IGNORECASE)
        
        self.log(f"Found {len(forms)} forms, {len(inputs_in_page)} named inputs, {len(param_links)} parameterized links")
        
        vectors_tested = 0
        
        # PHASE 2: Test URL parameters
        self.log("Phase 2: Testing URL parameters for SQLi...")
        
        # Test current URL if it has params
        parsed = urlparse(self.target_url)
        if parsed.query:
            params = parse_qs(parsed.query)
            for param_name in params:
                self._test_parameter(self.target_url, param_name, baseline_length)
                vectors_tested += 1
        
        # Test discovered parameterized links
        for link in param_links[:10]:  # Limit to 10
            full_url = urljoin(self.target_url, link)
            if urlparse(full_url).netloc == self.domain:
                link_parsed = urlparse(full_url)
                params = parse_qs(link_parsed.query)
                for param_name in params:
                    self._test_parameter(full_url, param_name, baseline_length)
                    vectors_tested += 1
        
        # PHASE 3: Test common parameter names on the target
        self.log("Phase 3: Testing common injectable parameters...")
        common_params = ['id', 'user', 'name', 'search', 'q', 'query', 'page', 'category', 'item', 'product', 'order', 'sort', 'filter', 'type', 'action', 'cmd', 'view', 'file', 'lang']
        
        for param in common_params:
            test_url = f"{self.target_url.rstrip('/')}?{param}=1"
            resp = self.request(test_url)
            if 'error' in resp and 'status' not in resp:
                continue
            
            test_body = resp.get('body', '')
            # Only test if the parameter seems to affect output
            if abs(len(test_body) - baseline_length) > 50 or resp.get('status', 200) != 404:
                self._test_parameter(test_url, param, baseline_length)
                vectors_tested += 1
        
        # PHASE 4: Test form inputs
        self.log("Phase 4: Testing form inputs...")
        for form_action, form_body in forms[:5]:  # Limit to 5 forms
            form_inputs = re.findall(r'<input[^>]*name=["\']([^"\']+)["\'][^>]*>', form_body, re.IGNORECASE)
            form_url = urljoin(self.target_url, form_action) if form_action else self.target_url
            method = 'POST' if re.search(r'method=["\']post["\']', form_body, re.IGNORECASE) else 'GET'
            
            for input_name in form_inputs:
                self._test_form_input(form_url, input_name, method)
                vectors_tested += 1
        
        if vectors_tested == 0:
            self.add_finding(
                "No Injectable Parameters Found",
                "INFO",
                "No URL parameters or form inputs were discovered for SQL injection testing.",
                "The application may use API-based communication (XHR/Fetch) which requires deeper testing."
            )
        
        self.log(f"SQL injection scan complete. Tested {vectors_tested} vectors. {len(self.results['findings'])} issues found.")
        self.finalize()

    def _test_parameter(self, url, param_name, baseline_length):
        """Test a URL parameter for SQL injection"""
        parsed = urlparse(url)
        base_url = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        
        for payload in self.PAYLOADS[:6]:  # Test first 6 payloads
            test_url = f"{base_url}?{param_name}={payload}"
            
            try:
                resp = self.request(test_url)
                if 'error' in resp and 'status' not in resp:
                    continue
                    
                response_body = resp.get('body', '')
                status = resp.get('status', 200)
                
                # Check for SQL error messages in response
                for sig in self.ERROR_SIGNATURES:
                    if re.search(sig, response_body, re.IGNORECASE):
                        self.add_finding(
                            f"SQL Injection Vector: {param_name}",
                            "CRITICAL",
                            f"SQL error message detected when injecting into parameter '{param_name}' at {base_url}. The database error was reflected in the response, confirming the input reaches a SQL query without proper sanitization.",
                            f"Payload: {payload} | Error Pattern: {sig} | Endpoint: {base_url}"
                        )
                        return  # One finding per param is enough
                
                # Check for boolean-based differences
                if payload == "1 AND 1=2" and abs(len(response_body) - baseline_length) > 100:
                    self.add_finding(
                        f"Potential Boolean-Based SQLi: {param_name}",
                        "HIGH",
                        f"Significant response size change detected for boolean payload on parameter '{param_name}'. This suggests the parameter is used in a SQL WHERE clause.",
                        f"Baseline: {baseline_length} chars, Payload response: {len(response_body)} chars | Endpoint: {base_url}"
                    )
                    return

                # Check for 500 errors (may indicate SQL error)
                if status == 500 and payload == "'":
                    self.add_finding(
                        f"Server Error on Quote Injection: {param_name}",
                        "HIGH",
                        f"Server returned HTTP 500 when a single quote was injected into parameter '{param_name}'. This strongly suggests unparameterized SQL queries.",
                        f"Payload: ' (single quote) | HTTP {status} | Endpoint: {base_url}"
                    )
                    return
                    
            except Exception as e:
                self.log(f"Error testing {param_name}: {str(e)}")
            
            time.sleep(0.3)  # Rate limiting

    def _test_form_input(self, form_url, input_name, method='POST'):
        """Test a form input field for SQL injection"""
        for payload in self.PAYLOADS[:4]:  # Test first 4 payloads on forms
            data = {input_name: payload}
            
            try:
                if method.upper() == 'POST':
                    encoded = urlencode(data).encode('utf-8')
                    resp = self.request(form_url, method='POST', data=encoded, headers={'Content-Type': 'application/x-www-form-urlencoded'})
                else:
                    test_url = f"{form_url}?{urlencode(data)}"
                    resp = self.request(test_url)
                
                if 'error' in resp and 'status' not in resp:
                    continue
                    
                response_body = resp.get('body', '')
                
                for sig in self.ERROR_SIGNATURES:
                    if re.search(sig, response_body, re.IGNORECASE):
                        self.add_finding(
                            f"SQL Injection in Form Field: {input_name}",
                            "CRITICAL",
                            f"SQL error detected when injecting into form field '{input_name}' submitted to {form_url}.",
                            f"Payload: {payload} | Method: {method} | Error: {sig}"
                        )
                        return
                        
            except Exception as e:
                self.log(f"Error testing form {input_name}: {str(e)}")
            
            time.sleep(0.3)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanSQLi.py <target_url>", file=sys.stderr)
