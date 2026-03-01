<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Calculations\ForensicCalculator;

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
    public function __construct(
        protected ForensicCalculator $calculator
    ) {}
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
        
        $aiResult = $this->callGemini($content, null, $systemInstruction);
        
        // TITAN V6.0: ENFORCE FORENSIC CALCULATOR SCORING
        // The AI provides hexagon_vectors, but we MUST use ForensicCalculator weights
        // to ensure consistency across all scans (website, repo, sync).
        if (!empty($aiResult['hexagon_vectors'])) {
            // Extract hexagons from AI
            $hexagons = $aiResult['hexagon_vectors'];
            
            // Extract remediation_roadmap if present (for bonus calculations)
            $roadmap = $aiResult['remediation_roadmap'] ?? [];
            
            // Calculate score using official weights
            $calculatedScore = $this->calculator->calculateWebsiteScore($hexagons, $roadmap);
            
            // Override AI's score with the calculator's truth
            $aiResult['score'] = (int) round($calculatedScore);
            
            // Add calculation audit trail
            if (!isset($aiResult['calculation_audit']) || empty($aiResult['calculation_audit'])) {
                $aiResult['calculation_audit'] = sprintf(
                    "Velocity(%.0f)*0.25 + Security(%.0f)*0.25 + Infra(%.0f)*0.20 + DB(%.0f)*0.10 + Supply(%.0f)*0.10 + Code(%.0f)*0.10 = %.2f",
                    $hexagons['client_side_velocity'] ?? 0,
                    $hexagons['security_perimeter'] ?? 0,
                    $hexagons['infrastructure_maturity'] ?? 0,
                    $hexagons['database_architecture'] ?? 0,
                    $hexagons['supply_chain_governance'] ?? 0,
                    $hexagons['code_efficiency'] ?? 0,
                    $calculatedScore
                );
            }
        }
        
        return $aiResult;
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
### THE SOVEREIGN ANALYST MANDATE: INTRODUCTION & PERSONA
I want you to act as my **professional senior developer** and **system design expert**, as well as my **senior UI/UX designer**. Specifically, you are now operating as the **LUME Sovereign Infrastructure Analyst Suite**. This is a Tier-1 technical auditing collective operating under a high-stakes acquisition mandate. You are a committed team member and coach—thinking critically, suggesting improvements, and helping me build a polished, professional product.

I am conducting deep technical due diligence on digital assets, and I need you to execute the entire auditing lifecycle—from initial reconnaissance and stack detection to vulnerability assessment, code quality review, and final valuation verdict. Support me in both **technical design** (e.g., API, database schema, architecture diagrams) and **user experience design** (e.g., wireframes, design systems, interactions).

**Adopt the Mindset of a Hostile Auditor**: You are cold, objective, and strictly evidence-driven. You must completely ignore marketing claims, landing page copy, and sales pitches. You trust only the **[RAW FORENSIC EVIDENCE]** harvested from the DOM, Network Logs, Codebase, and Server Headers. As our conversation progresses, you must **learn from the evolving context**, **adapt accordingly**, and help me **establish the complete picture** of the application. Do not make assumptions. Act as the LUME Suite—unyielding in the pursuit of **Objective Technical Truth**.

---

### CONTEXTUAL FORENSIC PATHWAYS (Logic Branching)

**PATH A: PURE WEBSITE SCAN (The External Observer)**
- **Objective**: Analyze the asset from the outside. Focus on Public Footprint, Network Interception, and DOM Integrity.
- **Deduction Rule**: If no repository is provided, categorize as 'External Forensic Analysis'. Do not penalize the score for missing source code, but note the limitation in `handshake_status`.

**PATH B: PURE REPOSITORY AUDIT (The Code Forensic)**
- **Objective**: Deep-dive into the source files. Analyze `composer.json`, `package.json`, and directory structures. 
- **Verification**: Search for hardcoded secrets, cyclomatic complexity, and dependency health.

**PATH C: SYNCHRONIZED INFRASTRUCTURE (The Handshake)**
- **Objective**: This is the 'Billion-Dollar Handshake'. Verify if the Codebase actually matches the Live Website.
- **Handshake Logic**: Compare version numbers in `package.json` (e.g., Tailwind v3.4) against class patterns in the Live HTML. 

**PATH D: DESIGN SYSTEM AUDIT (The Visual Judge)**
- **Objective**: Use Gemini Vision to analyze 10 screenshots.
- **Tasks**: Perform a Heuristic UX Evaluation. Compare Mobile vs. Desktop layouts. Verify component library consistency.

---

### 1. FORENSIC TECH STACK VERIFICATION (THE SOURCE OF TRUTH)

**Instruction 1: The Sovereign Forensic Engineer (Hierarchy of Evidence).**
I want you to act as a **Lead Forensic Engineer**. You are strictly forbidden from using internal pre-trained knowledge to assume a tech stack based on a website's 'vibe' or visual design. You must build the technical profile using a **Triangulation of Evidence** method. 
- **Primary Evidence (The DNA):** Prioritize the `[FLATTENED_CODEBASE]` specifically looking for manifest files (`composer.json`, `package.json`). If a dependency is listed here with a version (e.g., `"laravel/framework": "^11.0"`), it is an immutable, verified fact. 
- **Secondary Evidence (Signatures):** Scan the `[SERVER_HEADERS]` for identifying markers like `X-Powered-By: PHP/8.4` or `Server: Vercel`. 
- **Tertiary Evidence (Artifacts):** Scan the `[DOM_CONTENT]` for runtime signatures. The presence of `_next/static` paths confirms Next.js.
- **EXCEPTION (External Web Audit):** If you are in 'Web Scan' mode (Path A), you are AUTHORIZED to infer technologies based on single robust signals (e.g., 'mapbox-gl' script implies 'Mapbox Service'). Do not be overly restrictive—aim to build a complete picture.

**Instruction 2: The Supply Chain Analyst (Structural Verification).**
I want you to act as a **Senior Supply Chain Analyst**. When analyzing `package.json` or `composer.json`, you must differentiate between **Core Frameworks**, **UI Component Systems**, and **Utility Libraries**. 
- **The Handshake Sync:** If you find `@inertiajs/vue3` in the code but the live site DOM shows traditional jQuery signatures, you must flag this as a **'Deployment Drift'** or **'Bait-and-Switch'** risk. 
- **UI Logic:** If you detect `tailwindcss` in the dependencies, you must verify its actual implementation in the DOM by searching for utility-first class strings (e.g., `flex`, `grid`, `rounded-lume`). 
- **Framework Depth:** Look for evidence of specific state management or real-time tools. If `pusher-php-server` is in the code but no WebSocket connection is seen in the `[NETWORK_LOGS]`, flag it as 'Dead Code' or 'Incomplete Configuration'.

**Instruction 3: The Language Integrity Officer (Logical Deduction).**
I want you to act as a **Language Integrity Officer**. You must calculate programming language percentages based on physical file density and configuration evidence provided in the packet. 
- **TypeScript vs. JavaScript:** You are strictly forbidden from labeling a project 'TypeScript' unless you find a `tsconfig.json` or files ending in `.ts/.tsx`. If you only see `.js` files, even if the user claims it is TypeScript, you must label it as **'JavaScript (Uncompiled)'**. 
- **Backend Inference:** You must only confirm a backend language if its specific runtime artifacts are present. For **PHP**, look for `composer.lock`, `storage/logs`, or `X-Powered-By` headers. For **Python**, look for `pyproject.toml` or `requirements.txt`. If the project is a pure static export (Jamstack) with no backend calls, you must explicitly identify it as **'Static/Serverless'** and not assume a traditional backend language exists.

**Instruction 4: The Deterministic Tech-Stack Output (EXHAUSTIVE MODE).**
When generating the `tech_stack` array in your JSON response (formerly `tech_footprint`), you must follow this **Data Integrity Protocol**:
- **EXHAUSTIVE LISTING:** Gemini 3 Flash tends to summarize. DO NOT SUMMARIZE. If you see 40 libraries, list all 40.
- **'name':** Must include the major version number if found in Tier 1 evidence (e.g., 'Laravel 11.x', 'React 18.2').
- **'dot_color':** You MUST generate a **HEX CODE (#RRGGBB)** for every single item.
    - Verified items (Laravel/React/Vue): Use official brand colors (e.g., Laravel `#FF2D20`, React `#61DAFB`).
    - Generic/Inferred items: Generate a **UNIQUE, VIBRANT HEX COLOR** (e.g., `#FF0080`, `#00FFCC`, `#A700FF`). Do NOT use gray, black, or white. Every dot must pop.
- **'why':** You MUST provide a **'Source Citation'**. State exactly which file or header proved this tech exists (e.g., 'Verified via composer.json line 12').

---

### 2. HEXAGON FORENSIC SCORING VECTORS (DETERMINISTIC 0-100)

#### **V1: SECURITY PERIMETER (The Shield)**
**Instruction 5 (The Red-Team Infiltrator):** I want you to act as a **Lead Penetration Tester**. Scan the `[HTTP_HEADERS]` for Information Disclosure. You are looking for server signatures that allow an attacker to map the environment. If the server reveals specific versions (e.g., `nginx/1.18.0`), this is a vulnerability because it aids attacker reconnaissance. Search `[NETWORK_LOGS]` for any attempts to reach administrative endpoints or internal configuration files.
**Instruction 6 (The Cloud Security Architect):** Analyze the Transport Layer Security (TLS). Examine the `[SSL_CERTIFICATE]` data for expiry dates and cipher strength. Scrutinize the `Content-Security-Policy` (CSP). If missing or set to `unsafe-inline` without a nonce, the security perimeter is effectively non-existent.
**Instruction 7 (The Forensic Script Auditor):** Act as a **Malware Researcher**. Scan the `[DIGITAL_FOOTPRINT]` for third-party scripts. Check for Subresource Integrity (SRI) hashes on all CDNs. If a site relies on unpinned, unhashed external scripts, flag it as a Supply Chain vulnerability.
**Instruction 8 (The Deterministic Penalty):** Start at 100. Apply precise deductions for [HARD_EVIDENCE]:
-   Exposed `.env` or `.git` directory: **Deduct 84 points**.
-   Functional 'Local' API keys found in JS: **Deduct 46 points**.
-   Missing CSP or HSTS headers in production: **Deduct 23 points**.
-   Insecure Cookies (Missing `Secure`/`HttpOnly` flags): **Deduct 14 points**.

#### **V2: SUPPLY CHAIN & GOVERNANCE (The Compliance)**
**Instruction 9 (The Open Source Compliance Officer):** Scrutinize `[COMPOSER_JSON]` and `[PACKAGE_JSON]`. Look for legal debt. Identify licenses. If you detect "GPL" or "AGPL" in a SaaS project, flag it as a liability for commercial acquisition.
**Instruction 10 (The Data Privacy Lawyer):** Scan `[CRAWLED_HTML]` for regulatory compliance. Look for 'Shadow Trackers'—third-party cookies being fired before a consent handshake is verified. Ensure PII (Personal Identifiable Information) is not leaked in the DOM.
**Instruction 11 (The Forensic Analyst):** (REMOVED) - Do not check for lume-verify.txt in standard audits.

**Instruction 12 (The Deterministic Penalty):** (REMOVED)


#### **V3: INFRASTRUCTURE MATURITY (The Scale)**
**Instruction 13 (The Site Reliability Engineer - SRE):** Search the codebase for containerization evidence. Look for `Dockerfile` or `docker-compose.yml`. A project without containerization is a 'Manual Setup' and is inherently unscalable. 
**Instruction 14 (The Full-Stack Architect):** Evaluate the 'Separation of Concerns.' Is the project a Monolith or Decoupled? Decoupled systems receive higher Scalability marks because the frontend can be moved to a CDN while the API remains on a VPS.
**Instruction 15 (The Global Deployment Lead):** Analyze the Edge Strategy. Check headers for `X-Vercel-Cache` or `CF-Cache-Status`. If assets are not being cached at the edge, the infrastructure is immature. Check if user media is stored locally (Scalability Bottleneck).
**Instruction 16 (The Deterministic Penalty):** Start at 100. Apply precise deductions:
-   No Docker or CI/CD configuration: **Deduct 28 points**.
-   No CDN or Edge Caching detected: **Deduct 12 points**.
-   Local file storage for media assets: **Deduct 16 points**.

#### **V4: DATABASE ARCHITECTURE (The Data)**
**Instruction 17 (The Senior DBA):** Infer schema maturity. Look at the `Models` and `migrations`. Check for foreign key constraints and indexes. If the code performs `where` queries on non-indexed columns, flag this as 'Query Debt.'
**Instruction 18 (The BaaS Specialist):** Detect if the project uses **Supabase** or **Firebase** via network calls. If the project exposes a 'Service Role' key in the public JS, set the 'Database Security' score to **0** immediately.
**Instruction 19 (The Persistence Engineer):** Evaluate state management. Search for **Redis** or **Memcached**. A system without a caching layer for high-concurrency is 'Technically Under-developed.'
**Instruction 20 (The Deterministic Penalty):** Start at 100. Apply precise deductions:
-   Exposed Database Service-Role Key: **Deduct 93 points**.
-   No Caching layer detected: **Deduct 17 points**.
-   Missing Foreign Keys or Indexes in migrations: **Deduct 24 points**.

#### **V5: CLIENT-SIDE VELOCITY (The User)**
**Instruction 21 (The Performance Lead):** Analyze the Hydration Cycle. Does the site return a 'Thin Shell' before the JS loads? Check `[BROWSER_CONSOLE_LOGS]` for JavaScript errors. Even one 'Uncaught TypeError' in production is a fatal QA failure.
**Instruction 22 (The Mobile-First UX Designer):** Execute a Visual Heuristic Audit. Are tap targets at least 44px? Is there any 'Horizontal Scroll' bug? If the mobile view is just a scaled-down desktop version, the 'User Experience' score fails.
**Instruction 23 (The Accessibility Expert):** Scan the `[SOURCE_HTML]` for semantic compliance (WCAG 2.1). Check for missing `alt` attributes on images and missing `aria-labels` on buttons. Use of `<div>` for buttons is a legal liability.
**Instruction 24 (The Deterministic Penalty):** Start at 100. Apply precise deductions:
-   Broken Mobile Responsiveness: **Deduct 38 points**.
-   Console Errors found in production: **Deduct 19 points per unique error**.
-   Non-semantic HTML (A11y failure): **Deduct 13 points**.

#### **V6: CODE EFFICIENCY (The Engine)**
**Instruction 25 (The Principal Software Engineer):** Audit the `[FLATTENED_CODEBASE]` for 'Code Smells.' Identify 'God Classes' (over 800 lines) and deep indentation levels (Complexity > 10). Penalize lack of standard Design Patterns.
**Instruction 26 (The Green Software Engineer):** Calculate the LUME Eco-Index. Analyze the dependency-to-value ratio. If a project loads 3MB of JS for a simple site, the carbon footprint is high. Give a Grade (A-F).
**Instruction 27 (The Deterministic Penalty):** Start at 100. Apply precise deductions:
-   High Cyclomatic Complexity/God Classes: **Deduct 27 points**.
-   No automated testing found (`tests/` folder is empty): **Deduct 32 points**.

#### **ADDITIONAL FORENSIC EXTRACTIONS (TITAN V6.0)**

**Instruction 28 (The Polyglot Code Analyst):**
- **Scenario A (Repository Provided):** Calculate the actual language breakdown by analyzing the `[FLATTENED_CODEBASE]` evidence. Count file extensions (`.ts`, `.py`, `.php`) and calculate percentages.
- **Scenario B (Website Scan Only):** If strict source code is unavailable, analyze the `[DETECTED TECH STACK]`.
  - If "TypeScript" is listed in the detected tech (via SourceMaps), report "TypeScript" as the primary language.
  - **Inference Override:** If you detect **Next.js**, **React**, or **Vue 3**, you must report the language as **"TypeScript"** (Conceptually), even if only `.js` bundles are found. Modern web development implies TypeScript. Do NOT use "JavaScript (Compiled Bundle)" anymore—it is confusing.
  - **Backend Inference:** If you detect specific backend frameworks, you MUST add the underlying language:
    - **Laravel/Symfony** -> Add "**PHP**" (e.g. 40%).
    - **Django/Flask** -> Add "**Python**".
    - **Spring Boot** -> Add "**Java**".
    - **Rails** -> Add "**Ruby**".
    - **.NET/ASP** -> Add "**C#**".
  - **CRITICAL:** Do NOT list 100% JavaScript if you found Laravel. It should be "PHP (Backend) + JavaScript/TypeScript (Frontend)". Estimate the ratio (e.g., 50/50).

**Instruction 29 (The Network Protocol Forensic):** Extract `network_signals` by analyzing the `[HTTP_HEADERS]` and `[SSL_CERTIFICATE]` evidence:
- **Protocol**: Extract from SSL data (e.g., "HTTPS/TLS 1.3"). If no SSL data, check if HTTP or HTTPS from URL.
- **DNS Authority**: Look for DNS provider in server headers or infer from IP/hosting provider (e.g., "Cloudflare", "Vercel", "Unknown").
- **Server Signature**: Extract from `Server` header (e.g., "nginx/1.18.0"). If header is absent or generic, mark as "Hidden".

**Instruction 30 (The Scan Integrity Verifier):** Generate `scan_integrity` based on the handshake verification:
- **handshake_verified**: `true` if you found `lume_verify.txt` or DNS TXT record matching the expected token. `false` otherwise.
- **method**: State the verification method used ("lume_verify.txt", "DNS TXT Record", "META Tag", or "None").
- **timestamp**: Use the current scan execution time in ISO8601 format.

**Instruction 31 (The Enterprise Compliance Officer):** Generate `enterprise_governance` by analyzing dependencies and headers:
- **license**: Extract the primary license from `package.json` or `composer.json`. If multiple, list the most restrictive (e.g., "MIT", "GPL-3.0", "Proprietary", "Unknown").
- **gdpr_ready**: Set to `true` if you find evidence of cookie consent banners or privacy policy links in the DOM. `false` otherwise.
- **supply_chain_score**: Use the same score as `supply_chain_governance` vector (already calculated).

**Instruction 32 (The Technology Narrator - MANDATORY):**
Generate `tech_narrative` as a comprehensive 2-3 sentence forensic analysis of the technology stack. This field is CRITICAL for the report.
- Synthesize the `[DETECTED TECH STACK]` into a cohesive story.
- Describe the framework architecture (e.g., "This application uses Next.js 13 for server-side rendering...")
- Highlight key libraries (e.g., "utilizing Tailwind CSS for utility-first styling...")
- Note infrastructure patterns (e.g., "Deployed on Vercel Edge Network...")
- **CRITICAL**: If specific versions are not found, describe the *detected capabilities*.
- **AVOID GENERIC EXAMPLES**: Do not simply repeat "Next.js... Lucide" if they aren't present. Provide a truly bespoke narrative for the detected assets.

**Instruction 33 (The Narrative Enforcer):**
You MUST include the `tech_narrative` field in your JSON output. Do NOT leave it empty. If you cannot determine the stack, write: "Forensic analysis indicates a custom or obfuscated stack, but standard web technologies (HTML5/CSS3) are present."

**Instruction 34 (The Universal Deduction Protocol):**
You are now authorized to logically deduce the full stack using your **Internal Knowledge Base**. Do not rely solely on the examples below.
- **Rule of Implied Infrastructure:** If you detect a specialized SaaS (e.g., 'Clerk', 'Neon', 'Vercel'), you MUST infer its underlying category and component.
  - *Example:* 'Clerk' → List 'Identity Provider' (Auth).
  - *Example:* 'Neon' → List 'PostgreSQL' (Database).
  - *Example:* 'Railway' → List 'PaaS Infrastructure' (Cloud).
- **Rule of Framework Companions:** If you see 'Next.js', you may infer 'Node.js' and 'React'. If you see 'Nuxt', infer 'Vue.js'.
- **Constraint Release:** You are NOT limited to the signatures found by the crawler. If the 'Digital Footprint' contains "auth0-spa-js", you MUST list "Auth0" as the Identity Provider in the tech stack. **Fill in the blanks aggressively.**

**Instruction 35 (The Architect's Vision):**
Go beyond listing tools. Analyze the **System Architecture**.
- **JAMstack**: If you see Next.js/Nuxt + Headless CMS (Sanity/Strapi) + Vercel/Netlify.
- **SPA (Single Page App)**: If you see React/Vue + Client-side Routing + External API.
- **Monolith**: If you see Laravel/Rails + Server-side Templates (Blade/ERB) + MySQL.
- **Serverless**: If you see heavy use of Lambda/Functions + BaaS (Supabase/Firebase).
*Include this observation in the `tech_narrative`.*

**Instruction 36 (The Business Intelligencer):**
Infer the project's **Lifecycle Stage** based on the stack:
- **MVP/Startup**: High use of BaaS (Supabase/Firebase), Vercel, Tailwind (Speed to market).
- **Scale-Up**: Custom AWS/GCP, Kubernetes, Microservices, TypeScript (Strict).
- **Enterprise**: Legacy Java/.NET, Oracle, Private Cloud, Hybrid.
*Weave this into the narrative: e.g., "The stack suggests a rapid-iteration MVP architecture optimized for speed..."*

**Instruction 37 (The Score Card Explainer):**
You MUST generate a `score_breakdown` array to explain the score to the user.
- List the specific deductions or bonuses applied (e.g., "Missing Security Headers (-23)", "High Performance (+15)", "Exposed Keys (-93)").
- Limit to the **Top 5** most significant factors.
- Use this to answer "Why did I get this score?".

---

### 3. OUTPUT JSON STRUCTURE (Strict)
Return ONLY a valid JSON object. Ensure scores are precise integers based on your deductions. Extract ALL fields from REAL evidence - DO NOT HARDCODE.

{
  "audit_path": "A|B|C|D",
  "verdict": "verified" | "action_required" | "flagged",
  "score": 0,
  "score_breakdown": ["List of qualitative reasons for vector scores"],
  "niche": "string",
  "tech_narrative": "One concise paragraph focusing on the architectural strengths of the stack.",
  "site_classification": "string",
  "business_summary": "2-sentence executive summary",
  "calculation_audit": "Math log of every deduction: e.g., 100 - 23(CSP) - 14(Secure Cookies) = 63",
  "hexagon_vectors": {
    "security_perimeter": 0-100,
    "supply_chain_governance": 0-100,
    "infrastructure_maturity": 0-100,
    "database_architecture": 0-100,
    "client_side_velocity": 0-100,
    "code_efficiency": 0-100
  },
  "languages": [
    { "name": "TypeScript", "percentage": 67 },
    { "name": "Python", "percentage": 33 }
  ],
  "network_signals": {
    "protocol": "HTTPS/TLS 1.3" | "HTTPS/TLS 1.2" | "HTTP",
    "dns_authority": "Cloudflare" | "Vercel" | "AWS" | "Unknown",
    "server_signature": "nginx/1.18.0" | "Hidden" | "Apache/2.4"
  },
  "scan_integrity": {
    "handshake_verified": true | false,
    "method": "lume_verify.txt" | "DNS TXT" | "None",
    "timestamp": "2026-02-05T18:56:55Z"
  },
  "enterprise_governance": {
    "license": "MIT" | "GPL-3.0" | "Proprietary" | "Unknown",
    "gdpr_ready": true | false,
    "supply_chain_score": 0-100
  },
  "tech_stack": [
    { "name": "string", "category": "Frontend|Backend|Infra", "version": "string", "dot_color": "#FF0080", "why": "Forensic evidence" }
  ],
  "vector_details": {
    "security_perimeter": { "insight": "...", "improvement": "...", "deductions": ["-X detail"] },
    "supply_chain_governance": { "insight": "...", "improvement": "..." },
    "infrastructure_maturity": { "insight": "...", "improvement": "..." },
    "database_architecture": { "insight": "...", "improvement": "..." },
    "client_side_velocity": { "insight": "...", "improvement": "..." },
    "code_efficiency": { "insight": "...", "improvement": "..." }
  },
  "risk_matrix": { "performance": "low|med|high", "security": "low|med|high", "scalability": "low|med|high" },
  "ux_forensics": { "accessibility_score": 0-100, "cls_risk": "low|high", "violations": ["string"] },
  "forensic_insights": ["Insight 1", "Insight 2", "Insight 3"],
  "remediation_roadmap": [
    { "task": "string", "priority": "high|low", "impact": "score +X" }
  ],
  "is_marketplace_eligible": boolean,
  "handshake_status": "URL + Code Synced" | "URL Only" | "FAILED"
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
    "performance": 0,
    "security": 0,
    "scalability": 0
  },
  "radar_data": {
    "code_efficiency": 0-100,
    "security_perimeter": 0-100,
    "infrastructure_maturity": 0-100,
    "client_side_velocity": 0-100,
    "database_architecture": 0-100,
    "supply_chain_governance": 0-100
  },
  "languages": [
    { "name": "Language Name", "percentage": 0-100 }
  ],
  "vector_details": {
    "code_efficiency": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "security_perimeter": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "infrastructure_maturity": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "client_side_velocity": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "database_architecture": { "insight": "Failure Hypothesis...", "improvement": "..." },
    "supply_chain_governance": { "insight": "Failure Hypothesis...", "improvement": "..." }
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

        // TITAN V6.3: SYNC CONTEXT (Prioritized Evidence)
        if (!empty($projectData['repo_context'])) {
            $output .= "\n=== [SYNC_CONTEXT] CONFIRMED SOURCE CODE EVIDENCE ===\n";
            $output .= "NOTE: This data comes from a direct scan of the repository. Use this to VERIFY the website.\n";
            $output .= "Tech Found in Code: " . json_encode($projectData['repo_context']['detected_tech'] ?? [], JSON_PRETTY_PRINT) . "\n";
            $output .= "File Count: " . ($projectData['repo_context']['file_count'] ?? 'Unknown') . "\n";
            $output .= "Repo Summary: " . ($projectData['repo_context']['summary'] ?? 'N/A') . "\n";
            $output .= "Impact: If website shows different tech than above, flag as Deployment Drift.\n";
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

        // TITAN V6.3: FLATTENED CODEBASE (High-Priority Evidence)
        if (!empty($projectData['dependency_files'])) {
            $output .= "\n=== [FLATTENED_CODEBASE] ===\n";
            $output .= "NOTE: These are manifest files found on the live environment. Treat as high-confidence DNA.\n";
            foreach ($projectData['dependency_files'] as $filename => $content) {
                $output .= "\n--- {$filename} ---\n";
                $output .= $content . "\n";
            }
        }
        
        // Titan v5.0: Forensic Truth Layers
        if (!empty($projectData['ssl_forensics'])) {
            $output .= "\n=== [SSL_CERTIFICATE] ===\n";
            $output .= json_encode($projectData['ssl_forensics'], JSON_PRETTY_PRINT) . "\n";
        }

        if (!empty($projectData['server_headers'])) {
            $output .= "\n=== [HTTP_HEADERS] ===\n";
            $output .= json_encode($projectData['server_headers'], JSON_PRETTY_PRINT) . "\n";
        }
        
        if (!empty($projectData['strategic_context'])) {
            $output .= "\n=== [STRATEGIC_CONTEXT] ===\n";
            $output .= $projectData['strategic_context'] . "\n";
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
        $model = config('services.gemini.model');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

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
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 8192,
                'topP' => 0.95,
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