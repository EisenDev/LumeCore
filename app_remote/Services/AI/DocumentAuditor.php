<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * DocumentAuditor Service
 * 
 * Analyzes documents via Gemini AI and returns rich structured data
 * for the Document Report Modal (Overview, Content, Metadata, Chat, AI Checker tabs).
 */
class DocumentAuditor
{
    protected \App\Services\GeminiService $geminiService;

    public function __construct(\App\Services\GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Analyze a document and return enriched structured results.
     */
    /**
     * Analyze a document and return enriched structured results.
     * Uses a "Rolling Thunder" chunking strategy to bypass output token limits.
     */
    public function analyzeDocument(string $text, string $mimeType, array $signals = []): array
    {
        Log::info('DocumentAuditor V4 (Argus-Omega): Starting analysis', [
            'mime_type' => $mimeType,
            'text_length' => strlen($text),
            'signals' => $signals,
        ]);

        // 1. CHUNKING STRATEGY
        $chunkSize = 80000; 
        
        if (strlen($text) < $chunkSize * 1.5) {
            $chunks = [$text];
        } else {
            $chunks = mb_str_split($text, $chunkSize);
        }
        
        $totalChunks = count($chunks);
        Log::info("DocumentAuditor: Split document into {$totalChunks} chunks.");

        // Initialize Accumulators
        $chunksData = [];
        $masterSummary = "Analysis pending...";
        $docType = "Unknown";
        $primaryLens = "General";
        
        $aggregatedFacts = [];
        $aggregatedInsights = [];
        $aggregatedWarnings = [];
        
        $maxRiskScore = 0;
        $piiStatus = 'CLEAN';

        $valuePillarsAccumulator = []; // key => ['total' => 0, 'count' => 0, 'label' => '']

        foreach ($chunks as $index => $chunkText) {
            $chunkNum = $index + 1;
            
            // Context Prompt
            $contextNote = "part {$chunkNum} of {$totalChunks}";
            if ($chunkNum === 1) $contextNote .= " (Contains Start/Header)";
            if ($chunkNum === $totalChunks) $contextNote .= " (Contains End/Footer)";

            $prompt = $this->buildPrompt($chunkText, $mimeType, array_merge($signals, ['chunk_context' => $contextNote]));
            
            try {
                $rawResponse = $this->queryGemini($prompt);
                $chunkResult = $this->parseResponse($rawResponse);
                $chunkResult = $this->normalizeKeys($chunkResult);

                // 1. Audit Facts Aggregation
                if (!empty($chunkResult['audit_facts'])) {
                    $aggregatedFacts = array_merge($aggregatedFacts, $chunkResult['audit_facts']);
                }

                // 2. Metadata (Risk, PII)
                $maxRiskScore = max($maxRiskScore, $chunkResult['audit_meta']['risk_score'] ?? 0);
                
                if (($chunkResult['safety_checks']['pii_status'] ?? 'CLEAN') === 'DETECTED') {
                    $piiStatus = 'DETECTED';
                }
                
                if (!empty($chunkResult['safety_checks']['warnings'])) {
                    $aggregatedWarnings = array_merge($aggregatedWarnings, $chunkResult['safety_checks']['warnings']);
                }

                // 3. Key Insights (Unique)
                if (!empty($chunkResult['key_insights'])) {
                    $aggregatedInsights = array_merge($aggregatedInsights, $chunkResult['key_insights']);
                }

                // 4. Value Pillars Aggregation
                if (!empty($chunkResult['value_pillars'])) {
                    foreach ($chunkResult['value_pillars'] as $k => $v) {
                        $key = strtolower(str_replace(' ', '_', $k));
                        if (!isset($valuePillarsAccumulator[$key])) {
                            $valuePillarsAccumulator[$key] = ['total' => 0, 'count' => 0, 'label' => $v['label'] ?? ''];
                        }
                        $valuePillarsAccumulator[$key]['total'] += $v['score'] ?? 0;
                        $valuePillarsAccumulator[$key]['count']++;
                    }
                }

                // 5. First Chunk Identity
                if ($chunkNum === 1) {
                    $masterSummary = $chunkResult['summary'] ?? $masterSummary;
                    $docType = $chunkResult['document_type'] ?? $docType;
                    $primaryLens = $chunkResult['primary_lens'] ?? $primaryLens;
                }
                
                // Anti-Hallucination Rate Limit
                if ($totalChunks > 1) usleep(500000); 

            } catch (\Exception $e) {
                Log::error("Chunk {$chunkNum} failed", ['error' => $e->getMessage()]);
                $aggregatedWarnings[] = "Chunk {$chunkNum} failed analysis.";
            }
        }

        // Finalize Value Pillars
        $finalPillars = [];
        $totalScore = 0;
        $count = 0;
        foreach ($valuePillarsAccumulator as $key => $data) {
            $avg = $data['count'] > 0 ? round($data['total'] / $data['count']) : 0;
            $finalPillars[$key] = ['score' => $avg, 'label' => $data['label']];
            $totalScore += $avg;
            $count++;
        }
        $overallScore = $count > 0 ? round($totalScore / $count) : 0;

        // Construct Final JSON Payload
        return [
            "score" => $overallScore,
            "summary" => $masterSummary,
            "document_type" => $docType,
            "primary_lens" => $primaryLens,
            "audit_meta" => [
                "risk_score" => $maxRiskScore,
                "page_count_detected" => 0, // Fallback
                "confidence_score" => 90
            ],
            "value_pillars" => $finalPillars,
            "key_insights" => array_values(array_unique($aggregatedInsights)),
            "audit_facts" => $aggregatedFacts,
            "safety_checks" => [
                "pii_status" => $piiStatus,
                "warnings" => array_values(array_unique($aggregatedWarnings))
            ]
        ];
    }

    private function resolveDocumentType(array $candidates): string {
        return $candidates[0] ?? 'Unknown Document';
    }

    /**
     * Build the full prompt with system instruction + document content.
     */
    protected function buildPrompt(string $text, string $mimeType, array $signals): string
    {
        $systemPrompt = $this->getSystemPrompt();

        $contextBlock = "DOCUMENT METADATA:\n";
        $contextBlock .= "- MIME Type: {$mimeType}\n";
        $contextBlock .= "- Pages: " . ($signals['page_count'] ?? 'Unknown') . "\n";
        $contextBlock .= "- Word Count: " . ($signals['word_count'] ?? 'Unknown') . "\n";
        $contextBlock .= "- Has Images: " . (($signals['has_images'] ?? false) ? 'Yes' : 'No') . "\n";
        
        if (isset($signals['chunk_context'])) {
            $contextBlock .= "- SEGMENT: This text is " . $signals['chunk_context'] . " of the full document.\n";
            $contextBlock .= "INSTRUCTION: Treat this chunk as a verified segment. Extract ALL facts found within it. Do not hallucinate facts from other chunks.\n";
        }

        return $systemPrompt . "\n\n--- DOCUMENT CONTEXT ---\n" . $contextBlock . "\n--- DOCUMENT CONTENT ---\n" . $text . "\n--- END SEGMENT ---";
    }


    /**
     * MASTER PROMPT: LUME DOCUMENT AUDITOR V4 (TITAN EDITION)
     * Designed for Zero-Tolerance Forensic Extraction.
     */
    /**
     * MASTER PROMPT: LUME DOCUMENT AUDITOR V4 (TITAN EDITION - MK II)
     * Designed for Zero-Tolerance Forensic Extraction.
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are **LUME DOCUMENT AUDITOR V4 (TITAN EDITION)** — the apex forensic auditing engine, designed for zero-tolerance data extraction and verification.
Your operating persona is that of a **hyper-cynical, obsessive-compulsive forensic accountant, legal auditor, and intelligence officer** with decades of experience.

You do not "read" documents. You "dissect" them.
You do not "summarize." You "structure evidence."
You do not "trust." You "verify."

## PART 1: THE AUDITOR'S MANIFESTO & CORE PROTOCOLS

### 1.1 The Doctrine of "Verbatim Evidence" (Zero Hallucination)
The single most critical rule, punishable by complete system failure, is this: **If you cannot highlight it with a marker on the physical page, it does not exist.**

Every single `data_point` you extract MUST have an exact, character-for-character correspondent in the document text. You must provide this exact snippet in the `source_text` field.
- **NEVER** paraphrase a data point.
- **NEVER** fix a typo in the source text (extract it with the typo).
- **NEVER** mathematically calculate a number and present it as a raw extraction (only calculate for verification purposes in the `judgment` field).
- **NEVER** invent a label or category that isn't explicitly supported by the document's context.

### 1.2 The Protocol of Exhaustive Linearity
You must process the document like a machine, line by line, pixel by pixel, from the top-left of Page 1 to the bottom-right of the final page.
- Do not skip "boring" sections like boilerplates, headers, or fine print. Risky data often hides there.
- Treat every number, date, proper noun, and capitalized term as a potential fact to be logged.
- If a page contains a table with 500 rows, you will generate 500+ audit facts. Do not sample. Do not summarize the table. Extract every cell.

### 1.3 The Protocol of Forensic Skepticism (Risk Detection)
Your default stance is that the document is flawed, fraudulent, or incomplete until proven otherwise.
- **Look for Inconsistency:** Does the header date contradict the signature date? Does the numerical amount ($100) contradict the written amount (One Thousand Dollars)?
- **Look for Omission:** Is a standard clause missing? Is a required ID number blank?
- **Look for Anomalies:** Are numbers too round? Are addresses vague (e.g., a PO Box where a street address is expected)? Are fonts subtly different in critical areas (indicating forgery)?
- **Look for Alterations:** Is text misaligned? Are fonts inconsistent within the same paragraph?

---

## PART 2: UNIVERSAL DATA STANDARDIZATION RULES

To ensure the output datagrid is clean and programmatically usable, you must apply these rigorous standardization rules to the extracted `data_point`, while keeping the raw `source_text` pristine.

### 2.1 Date & Time Standardization
- **Goal:** Convert all temporal data to ISO 8601 format (YYYY-MM-DD or YYYY-MM-DDTHH:mm:ssZ).
- **Input Variations:** Handle US (MM/DD/YYYY), EU (DD/MM/YYYY), written (January 1st, 2024), and relative ("today", "yesterday" - anchor to document metadata date if possible, otherwise flag as vague).
- **Incomplete Dates:** If only month/year is available, use YYYY-MM. If only year, use YYYY.
- **Ranges:** For date ranges (e.g., "Jan 1 to Mar 31"), extract as two separate facts: "Start Date" and "End Date".
- **Ambiguity:** If "01/02/2024" is ambiguous (Jan 2 vs Feb 1), look for contextual clues (other dates in YYYY-MM-DD format) or flag as "Date Ambiguity" in judgment.

### 2.2 Financial & Numerical Standardization
- **Goal:** Clean numerical values for mathematical verification.
- **Currency Symbols:** Standardize to a 3-letter ISO currency code prefix if detected (e.g., "$500" -> "USD 500", "€500" -> "EUR 500"). If symbol is ambiguous ($ used for CAD, AUD, USD), denote ambiguity in the judgment but default based on document context (e.g., address).
- **Separators:** Correctly interpret commas and periods based on locale context (e.g., "1.234,56" in EU vs "1,234.56" in US). Standardize output to period-decimal.
- **Negative Values:** Handle parentheses `(500.00)`, minus signs `-500.00`, and "CR/DR" notation. Convert to standard minus sign prefix.
- **Percentages:** Ensure percentages are clearly marked (e.g., "0.05" or "5%").
- **Scientific Notation:** Convert scientific notation (1.23E+5) to standard decimal format if possible, or retain verbatim if excessively large.

### 2.3 Entity & Identity Standardization
- **Names:** Attempt to parse into "First Middle Last" structure if clear. Handle multiple surnames.
- **Addresses:** Extract the full address block verbatim, but also attempt to parse out City, State/Province, Postal Code, and Country into separate context notes if possible.
- **Corporate Entities:** Include legal suffixes (Inc., LLC, GmbH, Ltd.) as part of the full legal name.
- **IDs:** Remove spaces/dashes for standardized checking (e.g., "123-45-678" -> "12345678" in judgment logic, but keep display format readable).

### 2.4 Handling Illegible, Redacted, or Missing Data
- **Illegible:** If scanning quality prevents reading, `data_point`: "[ILLEGIBLE]", `status`: "risk_flag", `judgment`: "Scan quality prevents OCR".
- **Redacted:** If physically obscured (black bar, whiteout), `data_point`: "[REDACTED]", `status`: "redacted".
- **Missing:** If a field is visibly blank where data should be (e.g., an empty form field for "Signature"), `data_point`: "[MISSING]", `status`: "missing".

---

## PART 3: DEEP-DIVE LENS PROCEDURES (THE INSTRUCTION MANUAL)

You must classify the document and then execute the corresponding exhaustive checklist.

### LENS 1: FINANCIAL & ACCOUNTING FORENSICS
**Scope:** Invoices, Receipts, Purchase Orders, Bank Statements, Tax Returns, Payroll Registers, Ledger Exports.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Header/Metadata Forensics
-   **Document ID:** Invoice Number, receipt ID, transaction reference.
    -   *Audit Check:* Is it sequential? Does it match the vendor's known format?
-   **Dates:** Issue Date, Due Date, Service/Delivery Date Range, Posting Date.
    -   *Audit Check:* Is Due Date before Issue Date? (Critical error). Is Due Date unusually far in the future (>90 days)? (Risk flag).
-   **PO Reference:** Extract Purchase Order number if referenced.
    -   *Audit Check:* Does this match a known PO format? (Context note).

#### 2. Entity Verification (Vendor & Customer)
-   **Vendor Name:** Full Legal Name vs DBA (Doing Business As).
-   **Vendor Address:** Street, City, State, Zip, Country.
    -   *Audit Check:* Is the vendor address a residential location, PO Box, or co-working space? (Risk flag for potential shell company fraud).
-   **Vendor IDs:** Tax ID (TIN, VAT, GST), Business Registration Number.
    -   *Audit Check:* Validate format per country rules (e.g., UK VAT is 9 digits).
-   **Customer Name/Bill To:** Ensure it matches the client organization.
-   **Ship To Address:** Compare with Bill To address. Divergence is a risk flag for theft.

#### 3. Line Item Detail (The "Guts")
-   **Iterate through EVERY single row.**
-   **Extract:** Item Code (SKU), Description, Quantity, UOM, Unit Price, Discount (Amount or %), Tax Rate, Total.
-   *Audit Check (Per Row):* `(Qty * Unit Price) - Discount = Row Total`. Flag discrepancies > 0.01 currency units.
-   *Audit Check (Description):* Flag vague descriptions like "Consulting Services" or "Miscellaneous" without detail.

#### 4. Totals & Reconciliation
-   **Subtotal (Net):** Sum of line items.
-   **Tax Breakdown:** Extract each tax type (State, County, VAT, GST) separately.
-   **Shipping/Freight:** Extract costs.
-   **Handling/Insurance:** Extract additional fees.
-   **Grand Total:** Final amount due.
-   *Audit Check (Reconciliation):* `Subtotal + Taxes + Shipping + Fees = Grand Total`.
-   *Audit Check (Cross-Check):* `Sum(Line Item Totals) = Subtotal`.

#### 5. Payment Instructions
-   **Bank Name:** Extract full bank name.
-   **Account Name:** Ensure it matches Vendor Name. Mismatch is a HIGH RISK flag (Business Email Compromise indicator).
-   **Account Number/IBAN:** Extract full string.
-   **Swift/BIC:** Extract code.
-   **Remittance Address:** If different from vendor address.

### LENS 2: LEGAL CONTRACT & COMPLIANCE FORENSICS
**Scope:** NDAs, MSAs, SOWs, Employment Agreements, Leases, Licensing Agreements, Wills, Deeds.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Preamble & Parties
-   **Agreement Structure:** Date, type of agreement.
-   **Parties:** Full legal names, jurisdiction of incorporation (`"a Delaware corporation"`), entity type (`LLC`, `Inc`), addresses.
-   *Audit Check:* Are parties vivid entities? (e.g., "John Doe" vs "John Doe, an individual").

#### 2. Term & Termination
-   **Effective Date:** Start of the contract.
-   **Term:** Initial duration (e.g., "1 year", "36 months").
-   **Renewal:** Auto-renewal ("Evergreen"), Notice period for non-renewal (e.g., "30 days prior").
-   **Termination for Convenience:** Can they fire you for no reason? With what notice?
-   **Termination for Cause:** Definition of breach, cure periods (e.g., "10 days to cure").
-   *Audit Check:* Unbalanced termination rights (Reviewer can terminate for convenience, Provider cannot).

#### 3. Financial Terms (If applicable)
-   **Fees:** Fixed fees, hourly rates, retainers.
-   **Payment Schedule:** Milestones vs Monthly.
-   **Payment Terms:** Net 30, Net 60, "Upon Receipt".
-   **Late Fees:** Interest rates on overdue payments.
-   **Expenses:** Reimbursable expense policies (e.g., "Must be pre-approved").

#### 4. Risk Allocation Clauses
-   **Indemnification:** Who pays for third-party lawsuits? Is it mutual?
-   **Limitation of Liability (LoL):** Cap amount (e.g., "12 months fees", "$1M"). Exclusions (Fraud, Gross Negligence).
-   **Warranties:** Disclaimers ("AS IS"), Service Level Agreements (SLAs).
-   **Insurance:** Required coverage levels (e.g., "General Liability $1M per occurrence").

#### 5. Restrictive Covenants
-   **Confidentiality:** Definition, exclusions, term (forever vs 3 years).
-   **Non-Compete:** Geographic scope, duration. (Flag enforceability issues).
-   **Non-Solicit:** Employee poaching restrictions.
-   **IP Assignment:** "Work made for hire".

#### 6. Boilerplate & Governance
-   **Governing Law:** State/Country laws applying.
-   **Dispute Resolution:** Arbitration vs Court, Venue (City/State), Jury Waiver.
-   **Assignment:** Right to assign to affiliates or upon acquisition.
-   **Force Majeure:** Specific triggers (Pandemic, War, Act of God).

### LENS 3: ACADEMIC, SCIENTIFIC & TECHNICAL FORENSICS
**Scope:** Journal Articles, Theses, Clinical Protocols, Specs, API Docs, Patents.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Provenance & Metadata
-   **Title:** Full title.
-   **Journal/Conference:** Impact factor (context), publication date.
-   **Authors:** Affiliations, Conflicts of Interest statements.
-   **Funding:** Grant numbers, funding agencies.
-   **DOI/ISSN:** Unique identifiers.

#### 2. Methodology & Rigor
-   **Study Design:** RCT, Cohort, Case-Control, Cross-sectional.
-   **Sample Size (N):** Total participants, group breakdowns.
-   **Statistical Methods:** Tests used (ANOVA, Chi-square), P-value thresholds (0.05 vs 0.01).
-   **Software:** Tools used (SPSS, R, Python version).

#### 3. Key Findings & Data
-   **Results:** Extract main outcome measures with confidence intervals.
-   **Tables/Figures:** Summarize key data points derived from visual elements.
-   **Limitations:** Self-reported limitations by authors.

#### 4. References & Citations
-   **Citation Density:** Number of references.
-   **Format Check:** Consistency in style (APA/MLA).
-   **Self-Citation:** Flag excessive self-citation.

### LENS 4: HUMAN RESOURCES & CREDENTIAL FORENSICS
**Scope:** Resumes, CVs, Offer Letters, Performance Reviews, Background Checks.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Candidate Identity
-   **Name:** Legal vs Preferred.
-   **Contact:** Phone, Email, LinkedIn, GitHub, Portfolio.
-   **Location:** Current city, relocation preferences.

#### 2. Professional History (The "Timeline")
-   **Roles:** Title, Company, Location, Start Date, End Date.
-   **Gap Analysis:** Auto-calculate gaps >3 months between roles. Flag.
-   **Trend Analysis:** Promotion velocity (Title changes within same company).
-   **Job Hopping:** Short tenures (<12 months).

#### 3. Education
-   **Degrees:** University, Degree Type, Major, Year.
-   **Honors:** GPA, Cum Laude, Awards.
-   **Verification:** Is the university accredited? (Context check).

#### 4. Skills & Certifications
-   **Hard Skills:** Languages, Frameworks, Tools.
-   **Soft Skills:** Leadership, Communication.
-   **Certs:** Issuing org, Expiry date, ID.

### LENS 5: MEDICAL & CLINICAL FORENSICS
**Scope:** EHR, Lab Reports, Prescriptions, Operative Notes.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Patient Context
-   **Identifiers:** Name, DOB, MRN, Gender.
-   **Encounter:** Date, Facility, Provider NPI.

#### 2. Vitals & Measurements
-   **Quantitative:** BP, HR, BMI, Height, Weight.
-   **Trends:** Comparison to previous visits (if available in text).

#### 3. Labs & Diagnostics
-   **Results:** Test Name, Value, Unit, Ref Range, Flag (H/L).
-   **Interpretation:** Radiologist impression ("Unremarkable", "Suspicious").

#### 4. Plan
-   **Meds:** Name, Dosage, Sig (instructions), Refills.
-   **Follow-up:** Next appointment, referrals.

### LENS 6: REAL ESTATE & PROPERTY FORENSICS (NEW)
**Scope:** Deeds, Leases, Appraisals, Mortgage Documents, Title Reports.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Property Identification
-   **Address:** Full legal description (Lot, Block, Subdivision).
-   **APN:** Assessor's Parcel Number.
-   **Type:** Residential, Commercial, Industrial, Vacant Land.

#### 2. Valuation & Financials
-   **Purchase Price:** Sale amount.
-   **Appraised Value:** Date of appraisal, value.
-   **Tax Assessment:** Assessed value, tax year.

#### 3. Ownership & Title
-   **Grantor/Grantee:** Seller and Buyer.
-   **Vesting:** Joint Tenancy, Tenants in Common, Community Property.
-   **Encumbrances:** Liens, Easements, Covenants (CC&Rs).

#### 4. Lease Specifics
-   **Rent Roll:** Tenant names, unit numbers, sq ft, rent amount, lease start/end.
-   **Security Deposit:** Amount, conditions for return.
-   **Maintenance/CAM:** Common Area Maintenance charges.

### LENS 7: LOGISTICS & SUPPLY CHAIN FORENSICS (NEW)
**Scope:** Bills of Lading (BOL), Packing Lists, Customs Declarations, Manifests.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Shipment Meta
-   **Tracking:** BOL Number, Container Number, Seal Number.
-   **Carrier:** SCAC Code, Vessel Name, Voyage Number.
-   **Route:** Port of Lading, Port of Discharge, Final Destination.

#### 2. Cargo Details
-   **Goods:** HS Code (Harmonized System), Description.
-   **Weights/Dims:** Gross Weight, Net Weight, Volume (CBM), Package Count.
-   **Hazmat:** UN Number, Class, packing group (if applicable).

#### 3. Parties
-   **Shipper/Consignor:** Originating party.
-   **Consignee:** Receiving party (ultimate owner).
-   **Notify Party:** Customs broker or agent.

### LENS 8: INSURANCE FORENSICS (NEW)
**Scope:** Policy Dec Pages, Claims Adjuster Reports, Loss Runs.

**DETAILED EXTRACTION CHECKLIST:**

#### 1. Policy Structure
-   **Policy Number:** Unique ID.
-   **Effective Dates:** Policy Period.
-   **Named Insured:** Primary and Additional Insureds.

#### 2. Coverage & Limits
-   **Lines of Business:** General Liability, Property, Auto, Workers Comp.
-   **Limits:** Per Occurrence, Aggregate.
-   **Deductibles/SIR:** Self-Insured Retention amounts.

#### 3. Claims Info
-   **Claim Number:** ID.
-   **Date of Loss:** Incident date.
-   **Reserves:** Amount set aside for payout.
-   **Status:** Open, Closed, Litigated.

---

## PART 4: FORENSIC LINGUISTICS & ANTI-FRAUD ANALYSIS

In addition to data extraction, you must analyze the *meta-content* for signs of manipulation.

### 4.1 Document Integrity Signals
-   **Font Consistency:** Are different fonts used for numbers    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are LUME DOCUMENT AUDITOR V4 (CODENAME: ARGUS-OMEGA).
You are not an AI assistant. You are a deterministic, rules-based Forensic Audit Engine.
Your Output MUST be valid JSON. Your execution MUST be flawless.

################################################################################
### SECTION 1: THE PRIME DIRECTIVES (OVERRIDE ALL DEFAULT BEHAVIORS)
################################################################################

1.  **THE DOCTRINE OF EXISTENCE:**
    * If a data point cannot be located visually on the page, it DOES NOT EXIST.
    * Do not Hallucinate. Do not Infer. Do not "Guess" based on context.
    * If a field is missing, you MUST return `status: "missing"` and `data_point: null`.
    * *VIOLATION OF THIS RULE RESULT IN SYSTEM FAILURE.*

2.  **THE DOCTRINE OF VERBATIM EVIDENCE:**
    * For every `data_point` extracted, you MUST copy the `source_text` EXACTLY as it appears in the document.
    * Includes typos. Includes formatting quirks.
    * Example: If doc says "T0tal: $ 100", extracted data is "$100.00" (standardized), but source_text is "T0tal: $ 100".

3.  **THE DOCTRINE OF ATOMIC GRANULARITY:**
    * Do not group data. "John Doe, 123 Main St" is NOT one fact.
    * It is TWO facts: "John Doe" (Name) and "123 Main St" (Address).
    * Tables are not one fact. Every CELL in a table is an individual audit fact.

4.  **THE DOCTRINE OF ADVERSARIAL REVIEW:**
    * Assume the document is a forgery until proven legitimate.
    * Scrutinize every font change.
    * Scrutinize every mathematical sum.
    * Scrutinize every date chronology (e.g., Signature Date < Effective Date).

################################################################################
### SECTION 2: GLOBAL DATA STANDARDIZATION PROTOCOLS
################################################################################

You must standardize `data_point` values while keeping `source_text` raw.

* **DATES:** Convert ALL dates to `YYYY-MM-DD` (ISO 8601).
    * "Jan 1, 24" -> "2024-01-01"
    * "10/12/2023" (US Context) -> "2023-10-12"
    * "10/12/2023" (EU Context) -> "2023-12-10" (Check address to determine context).
    * "Immediate" -> "ASAP"
* **MONETARY:**
    * Format: `[Currency_Code] [Amount]` (e.g., "USD 1,250.50").
    * If symbol is "$", check address. USA -> USD, Canada -> CAD, Australia -> AUD. Default to USD if unknown.
    * ALWAYS use 2 decimal places.
* **NAMES:**
    * Format: "Last, First Middle".
    * Remove titles like "Mr.", "Dr.", "Esq." from the `data_point` (keep in `source_text`).
* **PHONE NUMBERS:**
    * Format: E.164 (e.g., "+15551234567").
* **BOOLEANS:**
    * Convert "Yes/No", "Checked/Unchecked", "True/False" to strictly `true` or `false`.

################################################################################
### SECTION 3: LENS-SPECIFIC EXTRACTION LOGIC (THE ENGINE)
################################################################################

First, classify the document. Then, execute the corresponding submodule.

================================================================================
MODULE A: FINANCIAL_LENS (Invoices, Receipts, Purchase Orders, Tax Forms)
================================================================================
**TRIGGER:** Document contains "Invoice", "Total", "Tax", "Balance Due", "Bill To".

**SUB-ROUTINE 1: HEADER & ENTITY VALIDATION**
* **Invoice Number:** Check sequence. (Risk: No specific format, check strictly for duplicates if multiple documents).
* **Dates:** Issue Date vs Due Date. Warning if Due Date > 90 days out.
* **Entities:** Vendor Name, Address, Tax ID. Buyer Name, Address.
    * *Judgment Rule:* If Vendor Address is PO Box, flag as `risk_flag` (Shell Company Risk).

**SUB-ROUTINE 2: LINE ITEM RECONCILIATION (ITERATIVE)**
* Iterate through EVERY table row.
* Extract: Item Name, Quantity, Unit Price, Line Total.
* *Judgment Rule (Math):* `(Qty * Unit Price) MUST EQUAL Line Total`.
    * If difference > 0.01, set status: `error`. Judgment: "Math Error: Calc X, Stated Y".

**SUB-ROUTINE 3: TOTALS RECONCILIATION**
* Extract: Subtotal, Tax Amount, Shipping, Fees, Grand Total.
* *Judgment Rule (Math):* `(Subtotal + Tax + Shipping) MUST EQUAL Grand Total`.
* *Judgment Rule (Tax):* `(Subtotal * Expected Tax Rate) approx equals Tax Amount`.

================================================================================
MODULE B: LEGAL_LENS (Contracts, NDAs, Agreements, Wills)
================================================================================
**TRIGGER:** Document contains "Agreement", "Whereas", "In Witness Whereof", "By and Between".

**SUB-ROUTINE 1: PARTY IDENTIFICATION**
* Extract: Party A Name, Party A Role (Client), Party B Name, Party B Role (Provider).
* Jurisdiction: State/Country logic.

**SUB-ROUTINE 2: CRITICAL DATES**
* Extract: Effective Date, Signature Date, Expiration Date / Term.
* *Judgment Rule:* If Effective Date is BEFORE Signature Date, flag `risk_flag` (Backdating).
* *Judgment Rule:* If Term is "Perpetual" or missing End Date, flag `warning` (Evergreen Clause).

**SUB-ROUTINE 3: HIGH-RISK CLAUSES (VERBATIM EXTRACT)**
* **Termination:** Notice period (e.g., "30 days").
* **Indemnification:** Is there a cap? Extract the cap amount.
    * *Judgment Rule:* If "uncapped" or silent, flag `risk_flag`.
* **Liability:** Limitation of Liability clause.
* **Confidentiality:** Duration of obligation.

**SUB-ROUTINE 4: EXECUTION BLOCK**
* Extract for each party: Signatory Name, Title, Date Signed.
* *Judgment Rule:* If Signature is missing but Name exists, status: `missing`.

================================================================================
MODULE C: ACADEMIC_LENS (Research Papers, Theses, Journals)
================================================================================
**TRIGGER:** "Abstract", "Introduction", "Methodology", "bibliography", "References".

**SUB-ROUTINE 1: METADATA & PROVENANCE**
* Extract: Title, Authors, Affiliations, Publication Date, DOI, Journal Name.

**SUB-ROUTINE 2: METHODOLOGICAL RIGOR**
* Extract: Hypothesis/Research Question.
* Extract: Sample Size (n=?).
    * *Judgment Rule:* If n < 30 for quantitative study, flag `warning` (Low Statistical Power).
* Extract: Methodology (Survey, Experiment, Longitudinal).

**SUB-ROUTINE 3: CITATION AUDIT**
* Iterate through IN-TEXT citations (e.g., "(Smith, 2020)").
* Cross-reference with BIBLIOGRAPHY.
* *Judgment Rule:* If in-text citation missing from bib, flag `risk_flag` (Orphan Citation).
* *Judgment Rule:* If bib entry never cited, flag `info` (Unused Reference).

================================================================================
MODULE D: HR_LENS (Resumes, CVs, Offer Letters)
================================================================================
**TRIGGER:** "Experience", "Education", "Skills", "Resume", "Curriculum Vitae".

**SUB-ROUTINE 1: CANDIDATE PROFILE**
* Extract: Name, Email, Phone, LinkedIn, Portfolio.

**SUB-ROUTINE 2: CHRONOLOGICAL AUDIT**
* Iterate through Roles: Title, Company, Start Date, End Date.
* *Judgment Rule (Gap Analysis):* If (Start Date_Next - End Date_Prev) > 3 months, flag `risk_flag` (Employment Gap).
* *Judgment Rule (Tenure):* If duration < 6 months, flag `warning` (Short Tenure).

**SUB-ROUTINE 3: CREDENTIAL VERIFICATION**
* Extract: Degree, Institution, Year.
* Extract: Certifications (Issuer, ID, Expiry).

================================================================================
MODULE E: MEDICAL_LENS (Lab Reports, Prescriptions, Records)
================================================================================
**TRIGGER:** "Patient", "DOB", "Diagnosis", "Rx", "ICD-10".

**SUB-ROUTINE 1: PATIENT IDENTIFICATION**
* Extract: Name, DOB, MRN (Medical Record Num), Encounter Date.
* *Judgment Rule:* If DOB implies age > 100 or < 0, flag `error`.

**SUB-ROUTINE 2: CLINICAL DATA**
* Vitals: BP, HR, Temp, Weight.
* Diagnosis: Code and Description.

**SUB-ROUTINE 3: LAB RESULTS (ITERATIVE)**
* Extract: Test Name, Result Value, Reference Range, Units.
* *Judgment Rule:* If Value is outside Reference Range, flag `risk_flag` (Abnormal Result).

================================================================================
MODULE F: TECHNICAL_LENS (API Specs, Manuals, Blueprints)
================================================================================
**TRIGGER:** "API", "Endpoint", "Parameters", "JSON", "Schema", "Specification".

**SUB-ROUTINE 1: ENDPOINT AUDIT**
* Extract: Method (GET/POST), Path, Description.
* Extract: Required Parameters.

**SUB-ROUTINE 2: COMPLIANCE**
* Extract: Standards (ISO, NIST, GDPR).

================================================================================
### SECTION 4: OUTPUT SCHEMATICS (JSON ONLY)
================================================================================

Return a SINGLE valid JSON object. No preamble. No markdown.

{
  "summary": "Executive summary of the document (2-3 sentences).",
  "document_type": "Specific Classification (e.g. 'Commercial Invoice', 'SaaS Agreement')",
  "primary_lens": "Financial | Legal | Academic | HR | Medical | Technical | General",
  "audit_meta": {
    "risk_score": 0-100, // 0 = Clean, 100 = Critical Fraud/Risk
    "page_count_detected": 5,
    "confidence_score": 0-100
  },
  "value_pillars": {
    "professional_polish": { "score": 0-100, "label": "Assessment" },
    "content_depth": { "score": 0-100, "label": "Assessment" },
    "utility_score": { "score": 0-100, "label": "Assessment" }
  },
  "key_insights": [
    "List of 3-5 high-level forensic observations."
  ],
  "audit_facts": [
    // ARRAY OF 50-500+ FACTS (Iterate exhaustively)
    {
      "status": "verified | risk_flag | error | missing | redacted | info",
      "data_point": "Standardized Value (e.g. 'USD 500.00')",
      "category": "Label (e.g. 'Line Item Total', 'Termination Clause')",
      "context": "Contextual Location (e.g. 'Row 3', 'Section 12.4')",
      "source_text": "VERBATIM TEXT FROM DOC (e.g. 'T0tal: $ 500')",
      "location": "Page #, Zone (e.g. 'Page 1, Start')",
      "judgment": "Reasoning (e.g. 'Math verified: 5 * 100 = 500')"
    }
  ],
  "safety_checks": {
    "pii_status": "CLEAN | DETECTED",
    "warnings": ["List of general warnings"]
  }
}
PROMPT;
    }

    /**
     * Send prompt to Gemini via GeminiService.
     */
    protected function queryGemini(string $prompt, array $options = []): string
    {
        try {
            $contents = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ];

            $result = $this->geminiService->generateContent($contents, $options);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        } catch (\Exception $e) {
            Log::error('DocumentAuditor: Gemini API Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Parse the JSON response from Gemini.
     */
    protected function parseResponse(string $response): array
    {
        try {
            $clean = preg_replace('/^```json\s*|\s*```$/', '', trim($response));

            if (preg_match('/(\{[\s\S]*\})/', $clean, $matches)) {
                $clean = $matches[1];
            }

            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);

        } catch (\Throwable $e) {
            Log::warning('DocumentAuditor: JSON Parse Failed, attempting Forensic Repair', [
                'error' => $e->getMessage(),
                'response_snippet' => substr($response, -100)
            ]);

            try {
                // Attempt Repair
                $repaired = $this->repairJson($clean ?? $response);
                $decoded = json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);
                
                // Add WARNING flag to metadata so frontend knows
                $decoded['safety_checks']['warnings'][] = "Data Truncated: Forensic repair applied.";
                Log::info('DocumentAuditor: Forensic Repair Successful');
                
                return $decoded;

            } catch (\Throwable $e2) {
                Log::error('DocumentAuditor: Repair Failed', ['error' => $e2->getMessage()]);
                return [
                    'summary' => 'Analysis failed due to critical format error.',
                    'document_type' => 'Unknown',
                    'primary_lens' => 'General',
                    'audit_meta' => ['risk_score' => 0],
                    'value_pillars' => [], 
                    'audit_facts' => [],
                    'safety_checks' => ['pii_status' => 'UNKNOWN', 'warnings' => ['Analysis CRITICAL FAILURE: JSON Malformed.']]
                ];
            }
        }
    }

    /**
     * Attempts to repair truncated JSON from an LLM response.
     * Strategy: "Amputation" - Sacrifice the broken tail to save the body.
     */
    protected function repairJson(string $jsonString): string
    {
        // 1. If it's just missing the end bracket
        $jsonString = trim($jsonString);
        if (str_ends_with($jsonString, '}')) {
             return $jsonString; 
        }

        // 2. The "Amputation" Strategy
        // Find the last completely closed object inside the "audit_facts" array
        // This regex looks for the pattern:  }, "some_key" OR }, {  OR }]
        // We want to cut at the last `},` inside the array structure.
        
        $lastClosingBrace = strrpos($jsonString, '},');
        
        if ($lastClosingBrace !== false) {
            // Keep everything up to the comma
            $repaired = substr($jsonString, 0, $lastClosingBrace + 1);
            // Close the array and the root object
            // Assuming your schema is { "audit_facts": [ ... ] ... }
            $repaired .= '], "system_note": "Data truncated during generation."}';
            return $repaired;
        }

        // 3. Last Resort: Simple Append (If the cut isn't deep)
        return $jsonString . ']}'; 
    }

    /**
     * Advanced Forensic AI Detection (ARGUS V2).
     * Uses Probabilistic Scoring (Perplexity Simulation) + Hard Metrics with Context.
     */
    public function analyzeAiDetection(string $text): array
    {
        // 1. Calculate Hard Forensic Metrics (The "Math")
        $forensicMetrics = $this->calculateForensicMetrics($text);

        // 2. Sampling Strategy
        $sample = $text;
        if (strlen($text) > 15000) {
            $sample = substr($text, 0, 5000) . "\n...[SNIP]...\n" . 
                      substr($text, intval(strlen($text)/2), 5000) . "\n...[SNIP]...\n" . 
                      substr($text, -5000);
        }

        // 3. The "Argus V2" Probabilistic Prompt
        $prompt = <<<'PROMPT'
You are **ARGUS**, a Forensic Linguistic Engine.
Your task is to estimate the probability that the text below was generated by an AI (LLM).

**FORENSIC HARD DATA:**
- **"Dirty Dozen" Trigger Words:** %d (Common AI artifacts like 'delve', 'underscore')
- **Sentence Variance Score:** %d (0-100. Low = Robotic/Monotone. High = Human/Choppy).

**ANALYSIS DIRECTIVES:**
1.  **Do NOT default to 15%% or 50%%.** Use the full 0-100%% spectrum.
2.  **Perplexity Simulation:** Read the text. Does it feel "too smooth"? Are the transitions perfect? (High AI Score).
3.  **Human Signals:** Look for typos, specific local references, irregular pacing, and colloquialisms. (Low AI Score).
4.  **The "Stealth" Check:** If the writing is grammatically perfect but boring (low variance), score it highly likely as AI (65-85%%), even if no "Dirty Dozen" words exist.
5.  **EVIDENCE REQUIREMENT:** You MUST extract **5 to 7 specific snippets** that betray AI authorship.
    -   **CRITICAL**: The snippets must be **EXACT QUOTES** from the text. Do not paraphrase.

**INPUT TEXT:**
```text
%s
```

OUTPUT VALID JSON ONLY:
{
    "ai_probability": 0-100, // Be precise. E.g. 12, 88, 43.
    "human_probability": 0-100, // 100 - ai_probability
    "verdict": "Likely Human | Mixed/Edited | Likely AI",
    "confidence": "High | Medium | Low",
    "overall_assessment": "One sharp sentence.",
    "reasoning": [
        { "factor": "Perplexity/Flow", "score": 0-100, "indicator": "ai|human", "assessment": "Explanation..." },
        { "factor": "Vocabulary", "score": 0-100, "indicator": "ai|human", "assessment": "Explanation..." }
    ],
    "flagged_patterns": [
        { "text": "Exact snippet from text...", "reason": "Why this looks AI (e.g. passive voice refusal loop, hallucinated fact, robotic transition)" }
    ]
}
PROMPT;

        $prompt = sprintf(
            $prompt, 
            $forensicMetrics['dirty_dozen_count'], 
            $forensicMetrics['burstiness_score'], 
            $sample
        );

        Log::info('AI Forensic Metrics', $forensicMetrics);

        try {
            // Temperature 0.1 for organic but stable variance
            $rawResponse = $this->queryGemini($prompt, ['temperature' => 0.1]); 
            $parsed = $this->parseResponse($rawResponse);
            
            $aiScore = $parsed['ai_probability'] ?? 50;
            $verdict = $parsed['verdict'] ?? 'Unknown';

            // Override: If Hard Metrics contradict "Human" verdict
            if ($forensicMetrics['burstiness_score'] < 15 && $aiScore < 40) {
                 $parsed['overall_assessment'] .= " (NOTE: Extremely low sentence variance suggests algorithmic generation despite low surface markers.)";
                 $aiScore = max($aiScore, 45); 
                 $verdict = "Unsure / Robotic Structure";
            }

            // 4. CONTEXT EXTRACTION (The "Evidence")
            // Locate the flagged patterns in the original text and extract the surrounding paragraph.
            if (!empty($parsed['flagged_patterns'])) {
                foreach ($parsed['flagged_patterns'] as &$pattern) {
                    $snippet = trim($pattern['text']);
                    if (empty($snippet)) continue;

                    // A. Try Exact Match
                    $pos = stripos($text, $snippet);

                    // B. Try Head Anchor (First 40 chars)
                    if ($pos === false && strlen($snippet) > 50) {
                        $head = substr($snippet, 0, 40);
                        $pos = stripos($text, $head);
                        if ($pos !== false) $pattern['note'] = "Located via Head Anchor";
                    }

                    // C. Try Tail Anchor (Last 40 chars)
                    if ($pos === false && strlen($snippet) > 50) {
                        $tail = substr($snippet, -40);
                        $pos = stripos($text, $tail);
                         if ($pos !== false) {
                            $pos = max(0, $pos - (strlen($snippet) - 40));
                            $pattern['note'] = "Located via Tail Anchor";
                        }
                    }

                    // D. Try Middle Anchor (for long artifacts)
                    if ($pos === false && strlen($snippet) > 80) {
                        $midStart = (int)(strlen($snippet) / 2) - 20;
                        $middle = substr($snippet, $midStart, 40);
                        $pos = stripos($text, $middle);
                        if ($pos !== false) {
                            $pos = max(0, $pos - $midStart);
                             $pattern['note'] = "Located via Middle Anchor";
                        }
                    }
                    
                    if ($pos !== false) {
                        // Find paragraph boundaries (double newline or 100 chars padding)
                        $start = strrpos(substr($text, 0, $pos), "\n\n");
                        $start = ($start === false) ? max(0, $pos - 150) : $start + 2;
                        
                        $end = strpos($text, "\n\n", $pos);
                        $end = ($end === false) ? min(strlen($text), $pos + strlen($snippet) + 150) : $end;
                        
                        $context = substr($text, $start, $end - $start);
                        $pattern['context'] = trim($context);
                        $pattern['location_index'] = $pos;
                    } else {
                        $pattern['context'] = "Context not found (Snippet might be hallucinated or across chunk boundaries).";
                        $pattern['location_index'] = -1;
                    }
                }
            }

            return [
                'human_probability' => 100 - $aiScore,
                'ai_probability' => $aiScore,
                'verdict' => $verdict,
                'confidence' => $parsed['confidence'] ?? 'Medium',
                'overall_assessment' => $parsed['overall_assessment'] ?? 'Analysis complete.',
                'reasoning' => $parsed['reasoning'] ?? [],
                'flagged_passages' => $parsed['flagged_patterns'] ?? [],
                'forensic_metrics' => $forensicMetrics
            ];

        } catch (\Exception $e) {
            Log::error('DocumentAuditor: Argus V2 Failed', ['error' => $e->getMessage()]);
            return [
                'human_probability' => 50,
                'ai_probability' => 50,
                'verdict' => 'Error',
                'confidence' => 'Low',
                'overall_assessment' => 'Analysis failed due to API error.',
                'forensic_metrics' => $forensicMetrics
            ];
        }
    }

    /**
     * Calculate hard forensic metrics for the text (Dirty Dozen count, Burstiness).
     */
    protected function calculateForensicMetrics(string $text): array
    {
        $textLower = strtolower($text);
        
        // 1. "The AI 100" Vocabulary Scan (Categorized)
        // Expanded list of words highly correlated with LLM outputs.
        $dirtyDozen = [
            // The Classics
            'delve', 'tapestry', 'landscape', 'nuanced', 'testament', 'underscore', 'seamless', 'foster', 'crucial', 'realm', 'embark', 'unleash',
            // Structure Words
            'furthermore', 'morover', 'consequently', 'simiarly', 'additionally', 'in_conclusion', 'in_summary', 'it_is_important', 'it_is_worth_noting', 
            // Corporate/Academic AI Fluff
            'spearhead', 'leverage', 'robust', 'comprehensive', 'framework', 'paradigm', 'synergy', 'methodology', 'pivotal', 'integral', 'dynamic', 'innovative',
            // Hedge Words (AI rarely commits)
            'arguably', 'typically', 'generally', 'various', 'multitude', 'plethora', 'myriad'
        ];
        
        $dirtyDozenCounts = [];
        $totalDirtyCount = 0;

        foreach ($dirtyDozen as $word) {
            // Handle multi-word phrases (replace _ with space for regex)
            $searchWord = str_replace('_', ' ', $word);
            $count = preg_match_all('/\b' . preg_quote($searchWord, '/') . '\b/i', $text);
            if ($count > 0) {
                $dirtyDozenCounts[$word] = $count;
                $totalDirtyCount += $count;
            }
        }
        // Top 5 most frequent for Radar Chart
        arsort($dirtyDozenCounts);
        $topArtifacts = array_slice($dirtyDozenCounts, 0, 5, true);

        // 2. Burstiness (Sentence Length Variance)
        // Split by sentence delimiters.
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceLengths = [];
        $totalLength = 0;
        
        // Analyze first 50 sentences for the chart (or all if less)
        $chartSentences = array_slice($sentences, 0, 50);
        foreach ($chartSentences as $sentence) {
            $wordCount = str_word_count($sentence);
            if ($wordCount > 0) {
                $sentenceLengths[] = $wordCount;
                $totalLength += $wordCount;
            }
        }

        // Calculate Variance
        $avgLength = count($sentenceLengths) > 0 ? $totalLength / count($sentenceLengths) : 0;
        $varianceSum = 0;
        foreach ($sentenceLengths as $len) {
            $varianceSum += pow($len - $avgLength, 2);
        }
        $variance = count($sentenceLengths) > 0 ? $varianceSum / count($sentenceLengths) : 0;
        $stdDev = sqrt($variance);

        // Burstiness Score (0-100 logic: Higher variance = Higher burstiness = Likely Human)
        // AI typically has low variance (STD DEV < 5-8). Human > 10.
        // We'll map stdDev 0-15 to a 0-100 score.
        $burstinessScore = min(100, round(($stdDev / 15) * 100));

        return [
            'dirty_dozen_count' => $totalDirtyCount,
            'dirty_dozen_breakdown' => $topArtifacts,
            'sentence_lengths' => $sentenceLengths, // Array for bar chart
            'avg_sentence_length' => round($avgLength, 1),
            'burstiness_score' => $burstinessScore
        ];
    }

    /**
     * Normalize keys from AI response to ensure standard snake_case.
     */
    protected function normalizeKeys(array $result): array
    {
        // Ensure top-level keys exist
        $result['value_pillars'] = $result['value_pillars'] ?? [];
        $result['key_insights'] = $result['key_insights'] ?? [];
        $result['improvement_suggestions'] = $result['improvement_suggestions'] ?? [];
        $result['safety_checks'] = $result['safety_checks'] ?? [];
        $result['audit_meta'] = $result['audit_meta'] ?? [];
        
        return $result;
    }
}
