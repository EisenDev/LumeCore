<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubRepositoryAuditor
{
    /**
     * Analyze Flattened Github Repository.
     */
    public function analyzeRepo(string $flattenedContext, array $metadata): array
    {
        $systemInstruction = $this->getSystemPrompt();
        
        // Truncate context if safe limit exceeded (Gemini Flash can handle ~1M tokens, but let's be safe with characters)
        // 500k chars is plenty for code structure analysis
        $context = substr($flattenedContext, 0, 800000); 
        $meta = json_encode($metadata, JSON_PRETTY_PRINT);

        $content = "REPO METADATA:\n{$meta}\n\nSOURCE CODE CONTEXT:\n{$context}";

        return $this->callGemini($content, $systemInstruction);
    }

    protected function callGemini(string $content, string $systemInstruction): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model'); 
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(180) // Longer timeout for code analysis
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(3, 5000)
            ->post($url, [
                'contents' => [['parts' => [['text' => $systemInstruction . "\n\nANALYZE:\n" . $content]]]],
            ]);

        if ($response->failed()) {
            throw new \Exception("Repo Audit Failed: " . $response->body());
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
                'score' => 0,
                'hexagon_vectors' => [],
                'executive_summary' => 'AI Code Analysis failed.',
                'code_quality' => ['rating' => 'F', 'major_issues' => []]
            ];
        }
        
        return $result;
    }

    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are **LUME TITAN CODE ANALYST**.
Analyze the provided Source Code for quality, security, and architectural integrity.

### 1. ANALYSIS VECTORS (0-100)
- **client_side_velocity**: (0 if backend only) Efficiency of frontend code.
- **code_efficiency**: Cleanliness, DRY principles, modern syntax.
- **security_perimeter**: Hardcoded secrets, sanitization, auth logic.
- **supply_chain_governance**: Dependency freshness, lock files.
- **infrastructure_maturity**: CI/CD, Docker, separation of concerns.
- **database_architecture**: Schema quality, ORM usage.

### 2. CRITICAL INSTRUCTION: EXHAUSTIVE TECH AUDIT
You must list **EVERY SINGLE** technology, library, and framework found in `package.json`, `composer.json`, or imports.
**DO NOT SUMMARIZE.** If you see 50 dependencies, I want 50 items in `tech_stack`.
Include deviations (e.g. "React 16" in a "React 18" project).

### 3. OUTPUT JSON STRUCTURE (Strict)
{
  "score": 0-100,
  "executive_summary": "Concise technical summary...",
  "business_summary": "High-level business capabilities...",
  "niche": "e.g. DeFi, SaaS, Healthcare",
  "hexagon_vectors": {
      "client_side_velocity": 0-100,
      "code_efficiency": 0-100,
      "security_perimeter": 0-100,
      "supply_chain_governance": 0-100,
      "infrastructure_maturity": 0-100,
      "database_architecture": 0-100
  },
  "vector_details": {
      "code_efficiency": { "explanation": "...", "improvement_tip": "..." },
      "security_perimeter": { "explanation": "...", "improvement_tip": "..." },
      "infrastructure_maturity": { "explanation": "...", "improvement_tip": "..." },
      "database_architecture": { "explanation": "...", "improvement_tip": "..." },
      "supply_chain_governance": { "explanation": "...", "improvement_tip": "..." },
      "client_side_velocity": { "explanation": "...", "improvement_tip": "..." }
  },
  "code_quality": {
      "rating": "A+|A|B|C|D|F",
      "major_issues": ["Issue 1", "Issue 2"]
  },
  "tech_stack": [
      {"name": "Laravel", "category": "Framework", "version": "10.x", "dot_color": "#FF2D20"},
      {"name": "Vue.js", "category": "Frontend", "version": "3.3", "dot_color": "#42B883"},
      {"name": "Tailwind", "category": "CSS", "version": "3.4", "dot_color": "#06B6D4"},
      {"name": "Axios", "category": "Library", "version": "1.6", "dot_color": "#5A29E4"},
      {"name": "Lodash", "category": "Utility", "version": "4.17", "dot_color": "#3492FF"}
  ],
  "languages": [
      {"name": "PHP", "percentage": 60},
      {"name": "JavaScript", "percentage": 40}
  ],
  "insights": ["Insight 1...", "Insight 2..."],
  "recommendations": ["Rec 1...", "Rec 2..."],
  "warning_flags": []
}
PROMPT;
    }
}
