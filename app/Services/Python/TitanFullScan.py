#!/usr/bin/env python3
"""
TitanFullScan - Comprehensive Security Scanner
Runs all TitanBase agents in sequence for a complete assessment.
This is the default fallback when no specific scan type is selected.
"""
import sys
import os
import json
import time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from TitanBase import TitanBase

# Import all specialized agents
from TitanHeaders import Agent as HeadersAgent
from TitanLeaks import Agent as LeaksAgent
from TitanSQLi import Agent as SQLiAgent
from TitanXSS import Agent as XSSAgent
from TitanDirFuzz import Agent as DirFuzzAgent
from TitanAuth import Agent as AuthAgent
from TitanIDOR import Agent as IDORAgent


class Agent(TitanBase):
    """
    Full comprehensive security scan. Delegates to all specialized agents
    and merges their findings into a single report.
    """

    AGENTS = [
        ('Security Headers', HeadersAgent),
        ('Sensitive Data Exposure', LeaksAgent),
        ('Directory Discovery', DirFuzzAgent),
        ('SQL Injection', SQLiAgent),
        ('Cross-Site Scripting', XSSAgent),
        ('Authentication Security', AuthAgent),
        ('IDOR / Access Control', IDORAgent),
    ]

    def run(self):
        self.log(f"Starting comprehensive security audit on {self.target_url}")
        self.log(f"Running {len(self.AGENTS)} specialized scan modules...")
        
        total_findings = 0
        
        for name, AgentClass in self.AGENTS:
            self.log(f"--- Running module: {name} ---")
            try:
                sub_agent = AgentClass(self.target_url)
                # Suppress sub-agent's finalize() to prevent JSON output
                sub_agent.finalize = lambda: None
                sub_agent.run()
                
                # Do NOT call sub_agent.finalize() — it prints JSON
                # Just merge the results
                module_findings = sub_agent.results.get('findings', [])
                module_logs = sub_agent.results.get('logs', [])
                
                # Tag findings with the module name
                for finding in module_findings:
                    finding['location'] = f"{name}: {finding.get('location', 'N/A')}"
                    self.results['findings'].append(finding)
                
                # Merge logs
                for log_entry in module_logs:
                    self.results['logs'].append(f"[{name}] {log_entry}")
                
                total_findings += len(module_findings)
                self.log(f"Module '{name}' completed: {len(module_findings)} findings")
                
            except Exception as e:
                self.log(f"Module '{name}' failed: {str(e)}")
                self.add_finding(
                    f"Module Error: {name}",
                    "INFO",
                    f"The {name} scan module encountered an error: {str(e)}",
                    "This module's results may be incomplete."
                )
        
        self.log(f"Comprehensive scan complete. Total findings: {total_findings}")
        self.finalize()


if __name__ == "__main__":
    if len(sys.argv) > 1:
        agent = Agent(sys.argv[1])
        agent.run()
    else:
        print("Usage: python TitanFullScan.py <target_url>", file=sys.stderr)
