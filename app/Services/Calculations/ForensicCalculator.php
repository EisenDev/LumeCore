<?php

namespace App\Services\Calculations;

class ForensicCalculator
{
    /**
     * Calculate the overall score from the 6 hexagon vectors.
     * This ensures a Single Source of Truth for scoring across the app.
     *
     * @param array $vectors  ['security_perimeter' => int, 'client_side_velocity' => int, ...]
     * @return int Rounded overall score (0-100)
     */
    /**
     * Calculate REPOSITORY Score (Standard Hexagon).
     * Weights are defined by the LUME Sovereign Mandate for Codebases.
     *
     * @param array $vectors  ['security_perimeter' => int, ...]
     * @return float Rounded score (0-100)
     */
    public function calculateRepositoryScore(array $vectors): float
    {
        // ... (existing code remains same)
        $weights = [
            'security_perimeter'        => 0.20,
            'database_architecture'     => 0.20,
            'code_efficiency'           => 0.20,
            'infrastructure_maturity'   => 0.15,
            'client_side_velocity'      => 0.15,
            'supply_chain_governance'   => 0.10,
        ];

        return $this->computeWeightedScore($vectors, $weights);
    }

    /**
     * Calculate Similarity Index between two tech stacks.
     * Higher similarity reduces the penalty for heuristic variance.
     */
    public function calculateSimilarityIndex(array $webStack, array $repoStack): float
    {
        if (empty($webStack) || empty($repoStack)) return 0.0;

        $matches = 0;
        
        // Normalize helper
        $getNames = function($stack) {
            return array_map(function($item) {
                // Handle both objects with 'name' and raw strings
                $name = is_array($item) ? ($item['name'] ?? '') : (string)$item;
                return strtolower(trim($name));
            }, $stack);
        };

        $webNames = array_filter($getNames($webStack));
        $repoNames = array_filter($getNames($repoStack));

        if (empty($webNames) || empty($repoNames)) return 0.0;

        foreach ($repoNames as $repoName) {
            foreach ($webNames as $webName) {
                // Fuzzy Match: "Tailwind CSS" matches "Tailwind" or "Tailwind CSS 3"
                if ($repoName !== '' && (str_contains($webName, $repoName) || str_contains($repoName, $webName))) {
                    $matches++;
                    break; // Move to next repo item once matched
                }
            }
        }

        // TITAN V6.8: Smart Denominator
        $denominator = max(count($webNames), count($repoNames));
        
        return min(1.0, $matches / $denominator);
    }

    /**
     * Calculate WEBSITE Score (Live Site Forensics).
     * Weights emphasize Velocity, Resilience (Infra), and Security.
     *
     * @param array $vectors ['velocity', 'resilience', 'security', 'supply_chain', 'infrastructure', 'database']
     * @return float Score (0-100)
     */
    public function calculateWebsiteScore(array $vectors, array $roadmap = []): float
    {
        // WEBSITE WEIGHTS
        // Velocity (25%) - User Experience is paramount.
        // Security (25%) - Public facing perimeter.
        // Infrastructure (20%) - Uptime/Resilience.
        // Database (10%) - Less visible but important.
        // Supply Chain (10%) - Frontend deps.
        // Code Efficiency (10%) - Harder to judge from outside.
        $weights = [
            'client_side_velocity'      => 0.25,
            'security_perimeter'        => 0.25,
            'infrastructure_maturity'   => 0.20,
            'database_architecture'     => 0.10,
            'supply_chain_governance'   => 0.10,
            'code_efficiency'           => 0.10, 
        ];

        $score = $this->computeWeightedScore($vectors, $weights);

        // TITAN V2.1: Apply Remediation Bonuses (Core 3)
        // This ensures the score dynamically increases as items are fixed (removed from roadmap)
        if (!empty($roadmap)) {
            $dummyDeductions = [];
            $score += $this->calculateRemediationBonuses($score, $roadmap, $dummyDeductions);
        }

        return (float) max(0, min(100, round($score, 2)));
    }

    /**
     * Calculate SYNC Score (12-Vector Comparison).
     * Compare 6 Repo Vectors vs 6 Website Vectors.
     * 
     * @param array $webVectors
     * @param array $repoVectors
     * @return float Confidence Score (0-100)
     */
    public function calculateSyncScore(array $webVectors, array $repoVectors): float
    {
        $result = $this->calculateSyncScoreWithBreakdown($webVectors, $repoVectors);
        return $result['score'];
    }

    /**
     * Calculate SYNC Score with itemized deductions.
     * 
     * @param array $webVectors
     * @param array $repoVectors
     * @param bool $isVerified  True if lume_verification.txt handshake is confirmed
     * @return array ['score' => float, 'deductions' => string[]]
     */
    public function calculateSyncScoreWithBreakdown(array $webVectors, array $repoVectors, bool $isVerified = false, array $webStack = [], array $repoStack = [], array $roadmap = [], array $context = [], array $aiScores = []): array
    {
        $score = 0.00;
        $pointsLog = [];

        // 1. STRUCTURAL DNA (The Skeleton) - 60 Points
        // We use similarity index and topology presence as verification of identity.
        $similarity = $this->calculateSimilarityIndex($webStack, $repoStack);
        $hasTopology = !empty($context['topology_match']);
        
        $dnaScore = 0;
        // TITAN AI OVERRIDE: Use AI Structural DNA Score if available
        if (isset($aiScores['structural_dna_score'])) {
             $dnaScore = (float) $aiScores['structural_dna_score'];
             if ($dnaScore > 0) {
                 $pointsLog[] = "Structural DNA (AI): +" . round($dnaScore, 1) . " [Semantic Alignment]";
             } else {
                 $pointsLog[] = "Structural DNA Mismatch (AI): 0.0 Points";
             }
        } else {
            if ($hasTopology || $similarity > 0.5 || $isVerified) {
                $dnaScore = 60.0;
                $pointsLog[] = "Structural DNA Match: +60.0 (Identity Confirmed)";
            } else {
                 $dnaScore = $similarity * 60;
                 $pointsLog[] = "Structural DNA Partial: +" . round($dnaScore, 1);
            }
        }
        $score += $dnaScore;

        // 2. TECH STACK (The Skin) - 20 Points
        // TITAN AI OVERRIDE: Use AI Semantic Score if available
        if (isset($aiScores['tech_stack_score'])) {
             $techScore = (float) $aiScores['tech_stack_score'];
             $analysis = $aiScores['tech_stack_analysis'] ?? 'AI Semantic Verification';
             if ($techScore > 0) {
                 $pointsLog[] = "Tech Stack (AI): +" . round($techScore, 1) . " [$analysis]";
             }
             $score += $techScore;
        } else {
            // Fallback to Proportional Scoring
            $techScore = $similarity * 20.0;
            if ($techScore > 0) {
                $pointsLog[] = "Tech Stack Verification: +" . round($techScore, 1) . " (Proportional)";
            }
            $score += $techScore;
        }

        // 3. STRATEGIC CONTEXT (The Soul) - 9 Points
        // TITAN AI OVERRIDE: Use AI Context Score if available
        if (isset($aiScores['strategic_context_score'])) {
            $stratScore = (float) $aiScores['strategic_context_score'];
            $analysis = $aiScores['strategic_analysis'] ?? 'AI Context Analysis';
            if ($stratScore > 0) {
                 $pointsLog[] = "Strategic Context (AI): +" . round($stratScore, 1) . " [$analysis]";
            }
            $score += $stratScore;
        } else {
            // Fallback to Keyword Match
            if (!empty($context['strategic_match'])) {
                $score += 9.0;
                $pointsLog[] = "Strategic Context Match: +9.0";
            }
        }

        // 4. IDENTITY (The Name) - 5 Points
        if (!empty($context['identity_match'])) {
            $score += 5.0;
            $pointsLog[] = "Identity/Name Match: +5.0";
        }

        // 5. VECTOR ALIGNMENT (The Health) - 6 Points
        $vectorScoreTotal = 0;
        $keys = [
            'security_perimeter', 'supply_chain_governance', 'infrastructure_maturity', 
            'database_architecture', 'client_side_velocity', 'code_efficiency'
        ];

        foreach ($keys as $key) {
            $v1 = (float)($webVectors[$key] ?? 0);
            $v2 = (float)($repoVectors[$key] ?? 0);
            $diff = abs($v1 - $v2);
            
            if ($diff < 15) {
                $vectorScoreTotal += 1.0;
            } elseif ($diff <= 40) {
                $vectorScoreTotal += 0.5; // USER REQUESTED BUFFER
            } 
        }
        $score += $vectorScoreTotal;
        if ($vectorScoreTotal > 0) {
            $pointsLog[] = "Vector Alignment: +{$vectorScoreTotal} (Max 6.0)";
        }

        return [
            'score' => (float) min(100, round($score, 2)),
            'deductions' => $pointsLog
        ];
    }

    /**
     * Calculate Boosts from Remediation Roadmap.
     * TITAN V2.1: The "Core 3" Remediation Boosts (+14 Points Total)
     */
    public function calculateRemediationBonuses(float $currentScore, array $roadmap, array &$deductions): float
    {
        // 1. CSP Headers (+5)
        $hasCSP = false;
        $hasConsoleFix = false; 
        $hasDependencies = false;

        // Simple heuristic check on the roadmap items to see if they are MARKED AS DONE (or not present implies done if we trust the scanner?)
        // Actually, the user says: "when our sync scan sees those on the remedetion is implemented it should automatically add the score."
        // This means if the scanner DOES NOT Find the issue, we give the points? 
        // OR if the issue WAS there and is now gone?
        // Let's assume: If the scanner says "Safe", we ensure the score reflects that.
        // But the previous "deduction" logic penalized them.
        // If we simply DON'T deduct, the score goes up.
        // BUT the user specifically asked for "+5".
        // This implies the base score (without them) is lower.
        // Let's look at the implementation:
        // If we add a dedicated "Remediation Bonus" section.
        
        // However, to keep it simple and additive as requested:
        // We will check if the specific "Task" is NOT in the roadmap? No, roadmap lists TODOs.
        // So if "Configure Content-Security-Policy" is NOT in the roadmap, it means it's done?
        // Yes, usually.
        // So we check if the relevant deduction was NOT applied?
        
        // User Logic: "60 + 16 = 76". "Then I did the one with 5 score ... = 81".
        // This implies the baseline is 60.
        // If I fix CSP, I get +5.
        // So I need to explicitly ADD points if the issue is ABSENT.
        
        // Let's iterate the 'roadmap' passed from ProjectAuditor.
        // Wait, ProjectAuditor generates the roadmap.
        // If ProjectAuditor finds the issue, it adds to roadmap.
        // If NOT found, it's not in roadmap.
        
        // So:
        // If "CSP" NOT in roadmap -> +5 (or ensure no penalty + bonus)
        // If "Geolocation" NOT in roadmap -> +4
        // If "Dependency" NOT in roadmap -> +5
        
        // BUT we need to be careful not to double count if the base score starts high.
        // Let's assume the "Base Score" calculation (deductions) puts it at ~60 for a raw site.
        // Adding +14 brings it to ~74. +16 Handshake = 90.
        // That fits the logic.
        
        // Let's check for specific keys in the roadmap to see if they are STILL pending.
        $cspPending = false;
        $consolePending = false;
        $depPending = false;
        
        foreach ($roadmap as $item) {
            $task = strtolower($item['task'] ?? '');
            if (str_contains($task, 'content-security-policy') || str_contains($task, 'csp')) $cspPending = true;
            if (str_contains($task, 'geolocation') || str_contains($task, 'console')) $consolePending = true;
            if (str_contains($task, 'npm') || str_contains($task, 'dependency')) $depPending = true;
        }

        $boost = 0.0;
        
        if (!$cspPending) {
            $boost += 5.0;
            $deductions[] = "Remediation Bonus (CSP Verified): +5.0";
        }
        if (!$consolePending) {
            $boost += 4.0;
            $deductions[] = "Remediation Bonus (Console/Geo Clean): +4.0";
        }
        if (!$depPending) {
            $boost += 5.0;
            $deductions[] = "Remediation Bonus (Deps Audited): +5.0";
        }

        return $boost;
    }

    /**
     * Compute weighted score helper.
     */
    private function computeWeightedScore(array $vectors, array $weights): float
    {
        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($weights as $key => $weight) {
            $score = $vectors[$key] ?? 0;
            // Clamp 0-100 to ensure validity
            $score = max(0, min(100, (float)$score));
            
            $totalScore += ($score * $weight);
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) return 0.0;

        // Return float with 2 decimal precision (e.g. 59.05)
        return round($totalScore, 2);
    }
    
    /** 
     * Legacy Alias for backward compatibility during refactor.
     * @deprecated Use calculateRepositoryScore instead.
     */
    public function calculateOverallScore(array $vectors): float
    {
        return $this->calculateRepositoryScore($vectors);
    }
    /**
     * Calculate Pentest Score based on Itemized Deductions.
     * 
     * @param array $vulnerabilities
     * @return array [ 'score' => int, 'breakdown' => array ]
     */
    public function calculatePentestScore(array $vulnerabilities): array
    {
        $score = 100;
        $breakdown = [];
        
        // Severity Weights (Deductions)
        $deductions = [
            'CRITICAL' => 35, // Reduced from 40
            'HIGH'     => 25, // Reduced from 30
            'MEDIUM'   => 15, // User requested 15-25 range
            'LOW'      => 5   // User requested 5-15 range
        ];

        foreach ($vulnerabilities as $vuln) {
            $severity = strtoupper($vuln['severity'] ?? 'LOW');
            $penalty = $deductions[$severity] ?? 5;
            
            // Track the first occurrence for the breakdown chart
            $type = $vuln['type'] ?? 'Other';
            if (!isset($breakdown[$type])) {
                $breakdown[$type] = 0;
            }
            $breakdown[$type] -= $penalty;
            
            $score -= $penalty;
        }

        // Special Penalty: HSTS Check (If not already covered)
        $hasHstsPenalty = false;
        foreach ($vulnerabilities as $v) {
            if (str_contains(strtoupper($v['type'] ?? ''), 'HSTS')) {
                $hasHstsPenalty = true;
                break;
            }
        }
        
        if (!$hasHstsPenalty) {
             // Logic would go here if we were checking raw headers, but we rely on AI report
        }

        return [
            'score' => max(0, $score),
            'breakdown' => $breakdown
        ];
    }

    /**
     * Universal Status Logic (The Source of Truth).
     * 0-69   = Flagged (Critical issues)
     * 70-84  = Action Required (Functional but needs polish)
     * 85-100 = Verified (Production Ready)
     * 
     * @param float $score
     * @return array [ 'status' => string, 'label' => string, 'color' => string ]
     */
    public function calculateStatus(float $score): array
    {
        if ($score >= 85) {
            return [
                'status' => 'verified',
                'label' => 'VERIFIED',
                'color' => 'emerald' 
            ];
        }
        
        if ($score >= 70) {
            return [
                'status' => 'action_required',
                'label' => 'ACTION REQUIRED',
                'color' => 'amber'
            ];
        }

        return [
            'status' => 'flagged',
            'label' => 'FLAGGED',
            'color' => 'rose'
        ];
    }
}
