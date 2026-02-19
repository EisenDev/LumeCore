<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebURLandRepoAuditor
{
    /**
     * Compare Website (Live) vs Repository (Code).
     */
    public function compare(array $webData, array $repoData): array
    {
        $systemInstruction = $this->getSystemPrompt();
        
        // Prepare comparison packet
        $packet = [
            'LIVE_WEBSITE' => [
                'url' => $webData['url'] ?? 'N/A',
                'description' => 'The live deployment.',
                'business_summary' => $webData['business_summary'] ?? $webData['website_metadata']['business_summary'] ?? '', // Context
                'detected_stack' => $webData['tech_stack'] ?? [],
                'live_routes' => $webData['live_routes'] ?? [],
                'route_contexts' => $webData['route_contexts'] ?? [], // TITAN V6.6
                'vectors' => $webData['hexagon_vectors'] ?? [],
                'evidence' => $webData['insights'] ?? [],
                'title' => $webData['title'] ?? null // Semantic Match
            ],
            'SOURCE_CODE' => [
                'url' => $repoData['url'] ?? 'N/A',
                'description' => $repoData['description'] ?? 'The git repository.',
                'business_summary' => $repoData['business_summary'] ?? $repoData['repository_metadata']['business_summary'] ?? '', // Context
                'repo_routes' => $repoData['repo_routes'] ?? [], // TITAN V6.5
                'name' => $repoData['name'] ?? null, // Identity Match
                
                // TITAN FIX: Inject Actual Tech Stack (was incorrectly sending forensics)
                'tech_stack' => $repoData['tech_stack'] ?? [],
                'languages' => $repoData['languages'] ?? [],
                
                // Keep signals for confirmation
                'vectors' => $repoData['hexagon_vectors'] ?? [],
                'quality' => $repoData['code_quality'] ?? []
            ]
        ];

        $content = "SYNC COMPARISON PACKET:\n" . json_encode($packet, JSON_PRETTY_PRINT);
        
        Log::info("TITAN DNA: Auditor Comparison Packet", [
            'web_routes' => count($webData['live_routes'] ?? []),
            'repo_keys' => array_keys($repoData['repo_routes'] ?? []),
            'has_contexts' => !empty($webData['route_contexts'])
        ]);

        return $this->callGemini($content, $systemInstruction);
    }
    
    protected function callGemini(string $content, string $systemInstruction): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model'); 
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(3, 5000)
            ->post($url, [
                'contents' => [['parts' => [['text' => $systemInstruction . "\n\nCOMPARE:\n" . $content]]]],
            ]);

        if ($response->failed()) {
             // Return fallback comparison
            return [
                'sync_score' => 50,
                'drift_analysis' => ['summary' => 'Comparison Failed (AI Error)'],
                'hexagon_vectors' => []
            ];
        }

        $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false) {
             $text = substr($text, $start, ($end - $start) + 1);
        } else {
             $text = preg_replace('/^```json\s*|\s*```$/', '', trim($text));
        }
        
        $result = json_decode($text, true);

        if (!$result || !is_array($result)) {
            return [
                'sync_score' => 50,
                'drift_analysis' => ['summary' => 'Comparison Parser Failed'],
                'hexagon_vectors' => []
            ];
        }
        
        return $result;
    }

    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are **LUME TITAN FORENSIC AUDITOR**.
Your job is to **collect evidence** of alignment between a LIVE WEBSITE and a SOURCE CODE REPOSITORY.

### 1. THE "PROOF OF WORK" PROTOCOL (Additive Scoring)
We do NOT use penalties. We use **ADDITIVE POINTS**. You must look for **POSITIVE PROOF** that these assets are linked.

**A. IDENTITY VERIFICATION (The "Handshake")**
- Does the repository contain specific "fingerprints" of the website?
    - Project Name (e.g. "Himsog" in `package.json` vs "Himsog" on UI).
    - Unique Component Names.
    - Specialized Strings/Content.
- **VERDICT**: If you find *any* strong unique identifier, mark `identity_match_boolean: true`.

**B. STRUCTURAL ALIGNMENT (The "Skeleton")**
- Does the Routing/Folder structure match the Navigation?
    - Repo: `/pages/dashboard` -> Web: `/dashboard`.
    - Repo: `/components/auth/Login.tsx` -> Web: Login Modal.
- **SEMANTIC VERIFICATION (The "Truth" Check)**: 
    - Use `ROUTE_CONTEXTS` (text on page) to verify the file content in the repo. 
    - If the page says "Welcome arjay" and the Repo has `AdminDashboard.vue` with greeting logic, it is a Match.
- **AI SCORING (0-60 Points)**: Compare `LIVE_ROUTES` vs `REPO_ROUTES` using this context.
- If the Live site has routes (e.g. `/admin`, `/checkout`) that have NO corresponding files/routes in the Repo, Score = 0.
- If it is a generic React Repo vs a PHP Site, Score = 0.
- Be extremely strict. Ghost routes are a sign of mismatch.

**C. TECH STACK PROOF (AI SCORING)**
- **Compare the stacks semantically**.
- React vs Vue = 0 points.
- React vs React = High points.
- **Score (0-20)**: Be strict. If stacks are fundamentally different, score MUST be < 5.

**D. STRATEGIC CONTEXT (AI SCORING)**
- **Compare Business Summaries**.
- "Medical Dashboard" vs "E-commerce Store" = 0 points.
- **Score (0-9)**: Be strict. Generic keywords like "Admin" do NOT count. verifiable semantic overlap required.

### 2. OUTPUT JSON STRUCTURE (Strict)
{
  "sync_score": 0-100, (ESTIMATE based on confidence.)
  "identity_match_boolean": true/false, (Strictly check if URL/Filename share a unique name like 'himsog')
  "structural_dna_score": 0.0-60.0, (Route/Structure Alignment Score)
  "structural_dna_map": [ (LIST of route matches)
     {"route": "/...", "repoFile": "...", "status": "match/ghost/drift", "type": "page/api", "reason": "..."}
  ],
  "tech_stack_score": 0.0-20.0, (Semantic Match Score)
  "strategic_context_score": 0.0-9.0, (Domain Match Score)
  "tech_stack_analysis": "Brief reasoning for stick score",
  "strategic_analysis": "Brief reasoning for context score",
  "drift_analysis": {
      "summary": "MANDATORY: Provide a highly specific, evidence-based forensic verdict. Mention specific project names, technologies (e.g. Next.js, React), and routing structures found. DO NOT use generic templates like 'the project exhibits alignment'. Speak like a human forensic analyst who has just verified the code.",
      "key_discrepancies": ["Only fatal mismatches (e.g. React Repo vs PHP Site)"],
      "matches": ["List specific matched evidence (e.g. 'Project Name Himsog found in both')"]
  },
  "hexagon_vectors": {
      "Client Side Velocity": 0-100, (Rate the LIVE site performance)
      "Code Efficiency": 0-100, (Rate the REPO code quality)
      "Security Perimeter": 0-100, (Rate the LIVE site security headers/ssl)
      "Supply Chain Governance": 0-100, (Rate the REPO dependency health)
      "Infrastructure Maturity": 0-100, (Rate the LIVE site infra/hosting)
      "Database Architecture": 0-100 (Rate the REPO schema/models)
  },
  "security_score": 0-100,
  "scalability_score": 0-100
}
PROMPT;
    }
}
