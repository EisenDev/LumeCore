#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.error
import ssl
import random
import time
from urllib.parse import urlparse, urljoin

class TitanBase:
    """
    Base class for Lume Agentic Security Scripts.
    Handles standardization of I/O, Networking, and Reporting.
    """
    def __init__(self, target_url):
        self.target_url = target_url
        self.domain = urlparse(target_url).netloc
        self.results = {
            'findings': [],
            'logs': [],
            'meta': {
                'target': target_url,
                'scanner': 'TitanAgent/1.0',
                'timestamp': time.time()
            }
        }
        
        # SSL Context (Unverified for testing)
        self.ssl_context = ssl.create_default_context()
        self.ssl_context.check_hostname = False
        self.ssl_context.verify_mode = ssl.CERT_NONE

        # User Agents
        self.user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Lume-Titan-Agent/1.0'
        ]

    def log(self, message):
        """Log a message to the results"""
        entry = f"[{time.strftime('%H:%M:%S')}] {message}"
        # sys.stderr.write(entry + "\n") # Optional: print to stderr for debug
        self.results['logs'].append(entry)

    def request(self, url, method='GET', headers=None, data=None):
        """Standardized HTTP Request Wrapper"""
        try:
            if not headers: headers = {}
            headers['User-Agent'] = random.choice(self.user_agents)
            
            req = urllib.request.Request(url, method=method, headers=headers, data=data)
            
            with urllib.request.urlopen(req, timeout=15, context=self.ssl_context) as response:
                return {
                    'status': response.getcode(),
                    'body': response.read().decode('utf-8', errors='ignore'),
                    'headers': dict(response.info())
                }
        except urllib.error.HTTPError as e:
            return {
                'status': e.code,
                'body': e.read().decode('utf-8', errors='ignore'),
                'headers': dict(e.headers),
                'error': str(e)
            }
        except Exception as e:
            self.log(f"Request Error: {url} - {str(e)}")
            return {'error': str(e)}

    def add_finding(self, title, severity, description, details=None):
        """Register a security finding"""
        finding = {
            'title': title,
            'severity': severity.upper(), # CRITICAL, HIGH, MEDIUM, LOW, INFO
            'description': description,
            'details': details,
            'location': 'Custom Agent Check'
        }
        self.results['findings'].append(finding)

    def run(self):
        """
        Main execution logic. 
        AI generated subclasses must override this.
        """
        raise NotImplementedError("AI must implement the run() method")

    def finalize(self):
        """Output results as JSON"""
        print(json.dumps(self.results))

if __name__ == "__main__":
    # Test Stub
    if len(sys.argv) > 1:
        agent = TitanBase(sys.argv[1])
        agent.log("Base Agent Initialized")
        agent.finalize()
