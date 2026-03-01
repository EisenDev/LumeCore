<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SecurityAuditor
{
    /**
     * Run the "White Hat" Penetration & QA Scan.
     * @param array $attackSurface Data containing forms, headers, cookies, and routes.
     * @param string $domContext The raw HTML structure to analyze for XSS/DOM vulnerabilities.
     * @param string|null $customPrompt Custom user instructions for sovereign scan focus
     * @param array|null $previousFindings Previous scan vulnerabilities for re-scan validation
     */
    public function performPentest(array $attackSurface, string $domContext, ?string $customPrompt = null, ?array $previousFindings = null): array
    {
        $systemInstruction = $this->getPentestPrompt($customPrompt, $previousFindings);
        
        // Format the attack surface for the AI
        $content = "TARGET ATTACK SURFACE:\n" . json_encode($attackSurface, JSON_PRETTY_PRINT);
        $content .= "\n\nDOM CONTEXT (Snippet):\n" . substr($domContext, 0, 50000); // Analyze first 50k chars for structure
        
        if ($customPrompt) {
            $content = "USER SOVEREIGN INSTRUCTIONS:\n\"{$customPrompt}\"\n\n" . $content;
        }
        
        if ($previousFindings && count($previousFindings) > 0) {
            $content .= "\n\nPREVIOUS SCAN FINDINGS (Validate if fixed):\n" . json_encode($previousFindings, JSON_PRETTY_PRINT);
        }
        
        // TITAN FIX: Pass previousFindings to callGemini for fallback preservation
        return $this->callGemini($content, $systemInstruction, $previousFindings);
    }

    /**
     * Call Gemini API (Standard Implementation)
     */
    protected function callGemini(string $content, string $systemInstruction, ?array $previousFindings = null): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model'); 
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Robust JSON extraction logic (reusing best practice from Project Auditor)
        $response = Http::timeout(120) // Allow 2 mins for forensic analysis
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(3, 5000) // Retry up to 3 times with 5s delay
            ->post($url, [
                'contents' => [['parts' => [['text' => $systemInstruction . "\n\nANALYZE THIS TARGET:\n" . $content]]]],
                'generationConfig' => [
                    'temperature' => 0.1, // Low temperature for deterministic security findings
                    'maxOutputTokens' => 4096, // Increase limit to prevent truncation
                    'topP' => 0.95,
                ],
            ]);

        if ($response->failed()) {
            Log::error("Security Audit API Failed", ['body' => $response->body()]);
            throw new \Exception("Security Audit Failed: " . $response->body());
        }

        $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        
        // Robust Extraction
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
             $text = substr($text, $start, ($end - $start) + 1);
        } else {
             $text = preg_replace('/^```json\s*|\s*```$/', '', trim($text));
        }
        
        $result = json_decode($text, true);

        if (!$result || !is_array($result)) {
            Log::warning("Security Auditor: Failed to parse AI response. Returning minimal structure.", ['raw_text' => substr($text, 0, 500)]);
            return [
                'security_score' => 60, // Lower fallback score for failed analysis
                'qa_score' => 50, 
                'vulnerabilities' => $previousFindings ?? [], // PRESERVE PREVIOUS FINDINGS ON FAIL
                'executive_summary' => 'AI analysis incomplete. Manual review recommended.',
                'thinking_process' => 'AI parsing failed - using conservative fallback scores.',
                'score_breakdown' => [
                    'injection_security' => 15,
                    'auth_privacy' => 15,
                    'access_control' => 15,
                    'configuration' => 15,
                    'data_exposure' => 15,
                    'ui_stability' => 14
                ],
                'remediation_progress' => [
                    'injection' => 0,
                    'auth' => 0,
                    'privacy' => 0,
                    'access' => 0,
                    'ui_stability' => 70
                ]
            ];
        }
        
        return $result;
    }

    /**
     * MASTER PROMPT: Certified Ethical Hacker & QA Lead
     */
    protected function getPentestPrompt(?string $customPrompt = null, ?array $previousFindings = null): string
    {
        $sovereignRule = $customPrompt 
            ? "### SOVEREIGN PRIORITY\nThe user has provided SPECIFIC SOVEREIGN INSTRUCTIONS: \"{$customPrompt}\". You MUST prioritize analyzing these specific areas within the scope of SURFACE-LEVEL WEB APPLICATION TESTING ONLY."
            : "";

        $rescanInstructions = "";
        if ($previousFindings && count($previousFindings) > 0) {
            $rescanInstructions = <<<RESCAN

### **RE-SCAN VALIDATION MODE**
This is a **RE-SCAN**. Previous vulnerabilities have been provided. You MUST:
1. **Validate Each Previous Finding**: Check if it's FIXED or still OPEN.
   - **FIXED**: The vulnerability is POSITIVELY CONFIRMED as remediated by the data (e.g., the missing header is NOW present).
   - **OPEN**: The vulnerability STILL EXISTS or CANNOT BE POSITIVELY CONFIRMED as fixed.
2. **Guilty Until Proven Innocent**: If you do not see EXPLICIT PROOF that a finding has been fixed (e.g. the header is still not in "official_headers"), you MUST mark it as `"status": "OPEN"`. NEVER assume a vulnerability is fixed just because it's a re-scan.
3. **Chain of Trust (Active Testing)**: 
   - If `active_test_results` shows an `error` or `status: error`, you MUST assume all related active tests (XSS, Directory Exposure, CSRF) are **STILL OPEN**. 
   - You CANNOT mark a previously active finding as FIXED if the tester failed to run.
4. **Set Proper Status**: For EACH vulnerability in your output, set:
   - `"status": "FIXED"` if you confirm the issue is remediated.
   - `"status": "OPEN"` if the issue persists or proof of fix is missing.
   - `"status": "IGNORED"` if it's acknowledged but not addressed.
5. **Include ALL Findings**: Report BOTH old vulnerabilities (with updated status) AND any NEW vulnerabilities discovered.
6. **Detailed Evidence**: You MUST provide specific evidence from the current scan data for why a status was changed to FIXED.

RESCAN;
        }

        return <<<PROMPT
You are **LUME SEC_OPS**, a **Surface-Level Web Application Security Analyzer**.

### CRITICAL SCOPE LIMITATION
You analyze ONLY web application security (client-side). You do NOT test:
- Server infrastructure (SSH, network, ports)
- Database security (SQL Server, MySQL, PostgreSQL)
- Operating system hardening
- Internal network architecture

{$sovereignRule}

{$rescanInstructions}

### 1. THE MINDSET
- **Surface Focus**: Analyze HTTP/HTTPS traffic, DOM structure, client-side code, and web server responses.
- **SCORING PROTOCOL (DETERMINISTIC)**:
    - **START at 100 POINTS**.
    - **-35** for CRITICAL findings (e.g., exposed .env, cleartext passwords).
    - **-25** for HIGH findings (e.g., Missing HSTS, CSRF).
    - **-15** for MEDIUM findings (e.g., Missing CSP, X-Frame-Options).
    - **-5** for LOW findings (e.g., Information Leakage).
    - **FLOOR at 0**.


### 2. SURFACE-LEVEL ANALYSIS VECTORS
Analyze the provided data for:
- **Injection**: XSS risks.
- **Auth**: Sensitive data exposure.
- **Privacy**: Missing HTTP security headers.
- **Access**: Unprotected endpoints or sensitive files (.git, .env).
- **UI Stability**: Console errors or functional failures.

### 3. OUTPUT JSON STRUCTURE (Strict)
{
  "thinking_process": "Mandatory step-by-step surface-level analysis...",
  "security_score": 0-100,
  "qa_score": 0-100,
  "vulnerabilities": [
    {
      "severity": "CRITICAL|HIGH|MEDIUM|LOW",
      "type": "e.g. Missing HSTS Header",
      "impact": "e.g. Man-in-the-Middle Risk",
      "location": "Official Headers",
      "description": "Short description...",
      "remediation_steps": [
        "Add 'Strict-Transport-Security' header.",
        "Set 'max-age=31536000'."
      ],
      "verify_action": "VERIFY_HEADER_POSTURE",
      "evidence": "Capture the specific string, payload, or header that triggered this finding (e.g. 'Server: Apache/2.4' or Reflected Payload).",
      "status": "OPEN|FIXED|IGNORED (OPEN if new vulnerability, FIXED if remediated, IGNORED if acknowledged)"
    }
  ],
  "remediation_protocols": {
    "injection": {
      "risk_analysis": "Detailed explanation of WHY this is a risk...",
      "strategic_fix": "High-level architectural fix...",
      "tactical_action_plan": ["Step 1...", "Step 2..."]
    },
    "auth": { "risk_analysis": "...", "strategic_fix": "...", "tactical_action_plan": [] },
    "privacy": { "risk_analysis": "...", "strategic_fix": "...", "tactical_action_plan": [] },
    "access": { "risk_analysis": "...", "strategic_fix": "...", "tactical_action_plan": [] },
    "ui_stability": { "risk_analysis": "...", "strategic_fix": "...", "tactical_action_plan": [] }
  },
  "specific_instructions_analysis": "MANDATORY: If user provided sovereign instructions, provide a dedicated paragraph here analyzing the site against those instructions. If no instructions, summarize the sovereign posture.",
  "score_breakdown": {
    "Missing Headers": -15,
    "Injection Risk": -25,
    "Information Leak": -5
  },
  "executive_summary": "WEB APPLICATION SECURITY POSTURE: [Summary]...",
  "sovereign_results": "Response to user instructions...",
  "sovereign_drifting": boolean
}
PROMPT;
    }
}