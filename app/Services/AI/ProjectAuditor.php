<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProjectAuditor Service
 * 
 * The "Sovereign Infrastructure Analyst" - specialized for:
 * - Website/URL scanning
 * - Figma design analysis
 * - Repository evaluationl
 * 
 * Handles audit_type: 'project' and 'design'
 */
class ProjectAuditor
{
   /**
     * Analyze project/website content using Google Gemini API.
     * Includes "Partial Forensic" fallback for slow/broken sites.
     */
    public function analyzeProject(array $projectData, string $evidence = ""): array
    {
        // 1. Detect if this is a "Rescue Scan" (Crawler failed but we have partial data)
        $isPartialScan = empty($evidence) || str_contains($evidence, 'CRAWLER_TIMEOUT') || str_contains($evidence, 'ERR_CONNECTION');

        // 2. Select the appropriate Persona
        // If partial/failed, use the "Forensic Pathologist" persona to explain the death.
        // If healthy, use the "Sovereign Architect" persona.
        $systemInstruction = $isPartialScan 
            ? $this->getForensicFailurePrompt() 
            : $this->getProjectPrompt();
        
        // 3. Format Data
        $content = $this->formatProjectDataForAI($projectData);
        $content .= "\n\n=== RAW FORENSIC EVIDENCE ===\n" . ($evidence ?: "NO DOM CAPTURED. ANALYZING METADATA ONLY.");
        
        return $this->callGemini($content, null, $systemInstruction);
    }

    /**
     * Analyze design using visual proofs (Sovereign Visual Audit).
     * This is for audit_type: 'design'
     * 
     * @param array $images Array of ['mime_type' => string, 'data' => base64_string]
     * @param array $metadata Project metadata (name, url)
     * @return array The analysis result
     * @throws \Exception If the API call fails
     */
    public function analyzeDesign(array $images, array $metadata): array
    {
        $systemInstruction = $this->getDesignPrompt();
        
        $context = "PROJECT: {$metadata['name']}\nURL: {$metadata['url']}\n\nAnalyzing " . count($images) . " visual proofs.";
        
        // Prepare content structure for callGemini
        $content = [
            'text' => $context,
            'images' => $images
        ];

        return $this->callGemini($content, 'multi-modal', $systemInstruction);
    }

 /**
     * MASTER PROMPT: Sovereign Infrastructure Architect
     */
    protected function getProjectPrompt(): string
    {
        return <<<'PROMPT'
You are the **LUME Sovereign Architect**, a Tier-1 DevSecOps Auditor.
Your mandate is to provide "Objective Technical Truth".

### 1. TECH STACK & LANGUAGE VERIFICATION (INFERENCE ENGINE)
- **Language Breakdown**: Estimate language usage based on the DETECTED TECH STACK.
  - If `Laravel` is found -> Assume significant `PHP` usage.
  - If `Next.js`/`React` is found -> Assume `TypeScript` or `JavaScript`.
  - If `Tailwind` is found -> Assume `CSS`.
- **Framework Rule**: Trust the provided `tech_stack` array. Do NOT list frameworks unless they appear there or are obvious from the DOM/Headers.
- **Alpine vs Vue**: Distinguish carefully based on `x-data` (Alpine) vs `v-if` (Vue).

### 2. SCORING PROTOCOL
- **Local/Staging**: Ignore SSL/SEO. Focus on Code Architecture.
- **Unstructured**: If code is messy, penalize "Resilience" but do not flag as malicious.

### 3. SCORING ALGORITHM (PRECISE INTEGERS 0-100)
**STRICT RULE: Do NOT round scores.** (e.g., 47 is better than 50).
- **Performance (0-100)**: Based on code efficiency, asset optimization (images/css), and lack of bloat.
- **Security (0-100)**: SSL presence, Security Headers (HSTS, CSP), and clean dependency audit.
- **Scalability (0-100)**: Presence of Docker, CI/CD configs, or separation of concerns (API vs Frontend).

### 4. ENTERPRISE GOVERNANCE & SOVEREIGNTY
- **Supply Chain**: Scan `package.json`/`composer.json` for outdated or risky deps.
- **Compliance**: Search for "Privacy Policy", "Terms", "Cookie" keywords. Check for GDPR/CCPA markers.
- **Eco-Index**: Estimate CO2 based on code bloat (A=Clean, F=Heavy).
- **Accessibility (WCAG)**: Analyze semantic HTML, alt tags, and contrast. Grade A/AA/AAA.
- **Data Sovereignty**: Infer server location. If US/FiveEyes -> High Risk. if EU/Switzerland -> Low Risk.

### 5. OUTPUT JSON STRUCTURE (Strict)
{
  "verdict": "verified" | "action_required" | "flagged",
  "score": 0-100,
  "niche": "Specific Industry",
  "business_summary": "2-3 sentences business summary.",
  "executive_summary": "Technical summary.",
  "tech_narrative": "Architectural overview.",
  "risk_matrix": {
    "performance": 0-100 (Integer),
    "security": 0-100 (Integer),
    "scalability": 0-100 (Integer)
  },
  "radar_data": {
    "code_resilience": 0-100,
    "security_perimeter": 0-100,
    "deployment_maturity": 0-100,
    "seo_authority": 0-100,
    "database_architecture": 0-100
  },
  "languages": [
    { "name": "Language Name (e.g. PHP)", "percentage": 0-100 }
  ],
  "vector_details": {
    "code_resilience": { "insight": "...", "improvement": "..." },
    "security_perimeter": { "insight": "...", "improvement": "..." },
    "deployment_maturity": { "insight": "...", "improvement": "..." },
    "seo_authority": { "insight": "...", "improvement": "..." },
    "database_architecture": { "insight": "...", "improvement": "..." }
  },
  "supply_chain_risk": {
      "level": "low|med|high",
      "vulnerabilities": ["string"]
  },
  "compliance_check": {
      "gdpr_compliant": boolean,
      "missing_policies": ["string"]
  },
  "carbon_footprint": {
      "grade": "A|B|C|D|F",
      "estimated_g_co2": number
  },
  "accessibility": { 
      "score": 0-100, 
      "grade": "A|AA|AAA|Fail", 
      "issues": ["string"] 
  },
  "data_sovereignty": { 
      "country": "string", 
      "jurisdiction_risk": "low|high", 
      "provider": "string" 
  },
  "tech_assessment": {
    "stack": [{"name": "string", "category": "string"}],
    "architecture": "string"
  },
  "warning_flags": ["string"],
  "insights": ["string"],
  "is_marketplace_eligible": boolean
}
PROMPT;
    }


    /**
     * DESIGN PROMPT: Sovereign UX/UI Lead
     * Context-Aware: Analyzes Visual Hierarchy, Atomic Design, and Mobile Response.
     */
    protected function getDesignPrompt(): string
    {
        return <<<'PROMPT'
You are the **LUME Sovereign Design Lead**, an expert in Human-Computer Interaction (HCI) and Atomic Design Systems.
Analyze the provided screenshots as a unified interface.

### 1. CONTEXTUAL AESTHETICS
- **Local/Staging Artifacts**: If you see "Lorem Ipsum", placeholder images, or debug borders, note them as "Draft Status" but DO NOT penalize the *potential* of the layout.
- **Mobile Responsiveness**: Aggressively check the narrow-width screenshots. Does the hamburger menu exist? Do columns stack correctly?

### 2. HEURISTIC ANALYSIS
- **Visual Hierarchy**: clear distinction between H1, H2, and body text?
- **Touch Targets**: Are buttons large enough for thumbs (44px+)?
- **Consistency**: Do primary buttons share the same color/radius?

### 3. OUTPUT JSON (Strict)
{
  "verdict": "verified" | "action_required" | "flagged",
  "score": 0-100,
  "audit_type": "design",
  "detailed_forensic_report": {
      "summary": "High-level design critique.",
      "ux_violations": ["Specific heuristic violation (e.g. 'Contrast ratio on secondary buttons is below AA standard')"],
      "mobile_analysis": "Detailed breakdown of the mobile viewport behavior.",
      "component_library_detected": boolean
  },
  "components_detected": ["string (e.g. 'Hero', 'Pricing Table', 'Auth Form')"],
  "insights": ["string"],
  "warning_flags": ["string"],
  "mobile_verified": boolean,
  "is_marketplace_eligible": boolean
}
PROMPT;
    }

    /**
     * FALLBACK PROMPT: Forensic Pathologist
     * Used when the crawler times out or fails to render DOM.
     */
    protected function getForensicFailurePrompt(): string
    {
        return <<<'PROMPT'
You are the **LUME Forensic Pathologist**.
The automated crawler FAILED to retrieve the DOM for this asset.
Your job is to analyze the *limited evidence* (Headers, DNS, Error Logs, Screenshot) to determine Cause of Death.

**OBJECTIVE:**
Do NOT return an error. Return a valid JSON audit that explains *why* the site is unreachable or un-scannable.

**ANALYSIS RULES:**
1. **Verdict**: Must be `action_required` (if it looks fixable) or `flagged` (if it looks dead/fake).
2. **Score**: Cap at 40/100.
3. **Executive Summary**: Focus on the failure point. (e.g., "The server refused the connection (ERR_CONNECTION_REFUSED). This typically indicates a firewall block, a crashed Nginx service, or an incorrect DNS A-Record.")
4. **Tech Narrative**: "The target infrastructure is unresponsive. Preliminary forensics indicate a failure at the [Network/Application] layer."

**Output the SAME JSON structure as the main prompt**:

{
  "verdict": "verified" | "action_required" | "flagged",
  "score": 0-100,
  "niche": "Specific Industry",
  "business_summary": "2-3 sentences business summary.",
  "executive_summary": "Technical summary of failure.",
  "tech_narrative": "Forensic analysis of the failure.",
  "risk_matrix": {
    "performance": 0-100 (Default to 0 or Estimate),
    "security": 0-100 (Default to 0 or Estimate),
    "scalability": 0-100 (Default to 0 or Estimate)
  },
  "radar_data": {
    "code_resilience": 0-100,
    "security_perimeter": 0-100,
    "deployment_maturity": 0-100,
    "seo_authority": 0-100,
    "database_architecture": 0-100
  },
  "languages": [
    { "name": "Language Name", "percentage": 0-100 }
  ],
  "vector_details": {
    "code_resilience": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "security_perimeter": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "deployment_maturity": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "seo_authority": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "database_architecture": { "insight": "Failure Hypothesis...", "improvement": "..." }
  },
  "tech_assessment": {
    "stack": [{"name": "string", "category": "string"}],
    "architecture": "string"
  },
  "warning_flags": ["string"],
  "insights": ["string"],
  "is_marketplace_eligible": boolean
}
PROMPT;
    }

    /**
     * Format project data for AI consumption.
     */
    protected function formatProjectDataForAI(array $projectData): string
    {
        $output = "=== PROJECT DATA FOR ANALYSIS ===\n\n";

        // Website Info
        if (!empty($projectData['website_url'])) {
            $output .= "WEBSITE URL: {$projectData['website_url']}\n";
        }

        // GitHub Info
        if (!empty($projectData['github_url'])) {
            $output .= "GITHUB REPO: {$projectData['github_url']}\n";
        }

        // Health Check Results
        if (!empty($projectData['health_data'])) {
            $output .= "\n=== HEALTH CHECK ===\n";
            $output .= json_encode($projectData['health_data'], JSON_PRETTY_PRINT) . "\n";
        }

        // GitHub Data
        if (!empty($projectData['github_data'])) {
            $output .= "\n=== GITHUB METADATA ===\n";
            $output .= json_encode($projectData['github_data'], JSON_PRETTY_PRINT) . "\n";
        }

        // Tech Stack
        if (!empty($projectData['tech_stack'])) {
            $output .= "\n=== DETECTED TECH STACK ===\n";
            foreach ($projectData['tech_stack'] as $tech) {
                $output .= "- {$tech['name']} ({$tech['category']})";
                if (!empty($tech['version'])) {
                    $output .= " v{$tech['version']}";
                }
                $output .= "\n";
            }
        }

        // ENV Variables
        if (!empty($projectData['env_variables'])) {
            $output .= "\n=== REQUIRED ENV VARIABLES ===\n";
            $output .= "Count: " . count($projectData['env_variables']) . "\n";
            $required = array_filter($projectData['env_variables'], fn($v) => $v['required'] ?? false);
            $output .= "Required: " . count($required) . "\n";
            
            // Group by category
            $categories = [];
            foreach ($projectData['env_variables'] as $var) {
                $cat = $var['category'] ?? 'General';
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }
            foreach ($categories as $cat => $count) {
                $output .= "- {$cat}: {$count} variables\n";
            }
        }

        // DNS Provider
        if (!empty($projectData['dns_provider'])) {
            $output .= "\n=== DNS PROVIDER ===\n";
            $output .= "Provider: {$projectData['dns_provider']['provider']}\n";
        }

        // Config Files
        if (!empty($projectData['config_files'])) {
            $output .= "\n=== CONFIG FILES FOUND ===\n";
            foreach ($projectData['config_files'] as $file) {
                $output .= "- {$file['path']} ({$file['size']} bytes)\n";
            }
        }

        // Raw Dependency Files (For Handshake Verification)
        if (!empty($projectData['dependency_files'])) {
            $output .= "\n=== RAW DEPENDENCY FILES (EVIDENCE) ===\n";
            foreach ($projectData['dependency_files'] as $filename => $content) {
                $output .= "\n--- {$filename} ---\n";
                $output .= $content . "\n";
            }
        }

        // Live Site Metadata (Evidence)
        if (!empty($projectData['head_tags'])) {
            $output .= "\n=== LIVE SITE METADATA (HEAD TAGS) ===\n";
            $output .= $projectData['head_tags'] . "\n";
        }
        if (!empty($projectData['generator_header'])) {
             $output .= "SERVER GENERATOR HEADER: " . $projectData['generator_header'] . "\n";
        }

        return $output;
    }

    /**
     * Call Gemini API with content and system instruction.
     */
    protected function callGemini(mixed $content, ?string $mimeType, string $systemInstruction): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";

        if (empty($apiKey)) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $parts = [];
        
        // System Instruction is always first
        $parts[] = ['text' => $systemInstruction];

        if ($mimeType === 'multi-modal' && is_array($content)) {
            // Handle Design Audit (images + text context)
            if (!empty($content['text'])) {
                 $parts[] = ['text' => $content['text']];
            }
            if (!empty($content['images'])) {
                foreach ($content['images'] as $img) {
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $img['mime_type'],
                            'data' => $img['data']
                        ]
                    ];
                }
            }
        } elseif ($mimeType) {
            // Single Image/PDF (fallback for future use)
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $content
                ]
            ];
        } else {
            // Text-only mode (Project Audit)
            $parts[] = ['text' => "Content to analyze:\n" . $content];
        }

        $response = Http::timeout(120)->withHeaders([
            'Content-Type' => 'application/json',
        ])->retry(3, 15000, function ($exception, $request) {
            // Retry on timeout or 429
            return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                   ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 429);
        })->post($url, [
            'contents' => [
                [
                    'parts' => $parts
                ]
            ]
        ]);

        if ($response->failed()) {
            if ($response->status() === 429) {
                 Log::warning('ProjectAuditor: Gemini API Quota Exceeded (429) after retries.');
            }

            Log::error('ProjectAuditor: Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to analyze with Sovereign Infrastructure Analyst.');
        }

        $data = $response->json();
        
        try {
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            
            // Robust JSON Extraction: Find first '{' and last '}'
            $start = strpos($responseText, '{');
            $end = strrpos($responseText, '}');
            
            if ($start !== false && $end !== false) {
                $responseText = substr($responseText, $start, ($end - $start) + 1);
            } else {
                // Fallback cleanup if braces not found (though unlikely for valid JSON)
                $responseText = preg_replace('/^```json\s*|\s*```$/', '', trim($responseText));
            }
            
            return json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            // THE 0 SCORE FIX: Log raw response for debugging
            Log::error("ProjectAuditor: JSON Parse Failed. Raw Response:\n" . ($responseText ?? 'NULL'));
            Log::error("ProjectAuditor: JSON Error: " . $e->getMessage());
            
            throw new \Exception('Sovereign Infrastructure Analyst returned an invalid response format. Check logs for raw output.');
        }
    }
}
