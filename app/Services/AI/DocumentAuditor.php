<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DocumentAuditor Service
 * 
 * The "Document Integrity Expert" - specialized for:
 * - Document content analysis (PDF, DOCX, images of documents)
 * - PII detection and privacy compliance
 * - Professional validity assessment
 * 
 * Handles ONLY audit_type: 'document'
 */
class DocumentAuditor
{
    /**
     * Analyze document content (text or image) using Google Gemini API.
     * This is for audit_type: 'document' ONLY.
     *
     * @param string $content The text content OR base64 encoded data
     * @param string|null $mimeType Mime type if sending base64 data
     * @return array The analysis result
     * @throws \Exception If the API call fails
     */
    public function analyzeDocument(string $content, ?string $mimeType = null): array
    {
        $systemInstruction = $this->getDocumentPrompt();
        return $this->callGemini($content, $mimeType, $systemInstruction);
    }

    /**
     * Get the system prompt for document audits.
     * PERSONA: Document Integrity Expert
     */
    protected function getDocumentPrompt(): string
    {
        return <<<'PROMPT'
You are the LUME Document Integrity Expert. You are a specialist in PII detection, document authenticity verification, and professional validity assessment.

CORE IDENTITY: You focus EXCLUSIVELY on:
1. Personal Identifiable Information (PII) detection and privacy risks
2. Document authenticity and professional validity
3. Marketplace eligibility based on privacy standards

Analyze this document for PROFESSIONAL INTEGRITY and PRIVACY COMPLIANCE.

ANALYSIS VECTORS:
1. Content Quality: Is this professionally written? Does it demonstrate expertise?
2. Structural Quality: Is the formatting agency-grade or amateur?
3. Template Value: Could this be sold as a reusable template/dataset?
4. DATA PRIVACY AUDIT (CRITICAL):
   - Detect Personal Identifiable Information (PII): Full real names, specific birthdays, ID numbers, real home addresses, private phone numbers.
   - APPLY "THE SUBJECT TEST": If the PII belongs to the SUBJECT of the document (e.g., a Resume, ID, Personal Letter, signed Contract), set is_marketplace_eligible: FALSE.
   - APPLY "THE AUTHOR TEST": If the PII belongs to the AUTHOR, COMPILER, or PUBLISHER of a data-heavy asset (e.g., Market Report, Research Paper, Statistics Dataset), set is_marketplace_eligible: TRUE.
     - You MUST explicitly allow names if they appear as 'Author', 'Compiled by', or 'Publisher' in professional reports.
   - APPLY "THE CONTACT INFO RULE": Documents containing private PHONE NUMBERS or HOME ADDRESSES are ALWAYS ineligible (is_marketplace_eligible: FALSE), even in research reports, to protect the user from spam/harassment.

MARKETPLACE ELIGIBILITY RULES:
- ALLOWED: Templates (with placeholders), Professional Reports/Datasets (with Author attribution), Design Assets, Educational Content.
- FORBIDDEN: Real Resumes (Subject Test), IDs, Personal Financial Data, Documents with private Contact Info (Phone/Home Address).
- Example 1: A Resume for 'John Doe' with real phone number -> INELIGIBLE (Subject Test + Contact Info).
- Example 2: A '2024 Market Report' compiled by 'John Doe' with no private contact info -> ELIGIBLE (Author Test).
- Example 3: A Research Paper by 'Jane Smith' that includes her private home address -> INELIGIBLE (Contact Info Rule).

ACADEMIC CONTEXT DETECTION:
Capstone Project Detection: If the document appears to be:
- A Capstone Project / Thesis / Academic Paper
- You MUST categorize it accordingly and analyze:
  * Problem Statement accuracy
  * Methodology rigor
  * Technical implementation details
  * Testing/Validation phases
- Citation: Cite specific sections you found (e.g., "I detected a clear Section 2.5 Testing phase")

SCORING RULE (MANDATORY):
Rule 7: You MUST always return a top-level "score" field (0-100) that is the weighted average of your internal metrics:
- formatting: 20%
- content_quality: 30%
- template_value: 20%
- industry_relevance: 30%

RESPONSE FORMAT (Strict JSON):
Return a detailed JSON object.
{
  "verdict": "verified" | "verified_private" | "flagged" | "action_required",
  "score": 0-100,
  "summary": "A 2-3 sentence professional summary of the document's purpose and quality.",
  "document_type": "Resume | Report | Template | Contract | Research Paper | Thesis | Capstone | Dataset | Certificate | Other",
  "breakdown": {
      "formatting": 0-100,
      "content_quality": 0-100,
      "template_value": 0-100,
      "industry_relevance": 0-100
  },
  "pii_detected": ["List of PII types found: 'Full Name', 'Phone Number', 'Address', etc."],
  "pii_test_applied": "Subject Test | Author Test | Contact Info Rule | None",
  "insights": ["Bullet points of professional observations about the document."],
  "warning_flags": ["Array of warnings, e.g., 'Contains private phone number'"],
  "is_marketplace_eligible": boolean,
  "flag_reason": "string or null - reason if marketplace ineligible"
}

RULES:
1. Return ONLY valid JSON.
2. Focus on the DOCUMENT content - do NOT describe website features or technical architecture.
3. This is NOT a code audit. Do not look for code vulnerabilities.
4. If PII is found, apply the appropriate test (Subject, Author, Contact Info).
5. Be specific about what you found and why it affects eligibility.
6. Academic documents should be graded on their academic merit.
PROMPT;
    }

    /**
     * Call Gemini API with content and system instruction.
     * Document-specific: handles text and single images/PDFs.
     */
    protected function callGemini(string $content, ?string $mimeType, string $systemInstruction): array
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

        if ($mimeType) {
            // Document Image/PDF (base64)
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $content
                ]
            ];
        } else {
            // Text-only mode (extracted document text)
            $parts[] = ['text' => "Document content to analyze:\n" . $content];
        }

        $response = Http::timeout(60)->withHeaders([
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
                 Log::warning('DocumentAuditor: Gemini API Quota Exceeded (429) after retries.');
            }

            Log::error('DocumentAuditor: Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to analyze with Document Integrity Expert.');
        }

        $data = $response->json();
        
        try {
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            
            // Clean up any potential markdown
            $responseText = preg_replace('/^```json\s*|\s*```$/', '', trim($responseText));
            
            return json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('DocumentAuditor: Failed to parse Gemini response', ['error' => $e->getMessage(), 'content' => $data]);
            throw new \Exception('Document Integrity Expert returned an invalid response format.');
        }
    }
}
