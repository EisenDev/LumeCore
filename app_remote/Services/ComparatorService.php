<?php

namespace App\Services;

use App\Models\VaultAsset;
use Illuminate\Support\Str;

class ComparatorService
{
    /**
     * Compare a Website Asset and a Repository Asset.
     * Returns a difference analysis.
     *
     * @param VaultAsset $webAsset
     * @param VaultAsset $repoAsset
     * @return array
     */
    public function compare(VaultAsset $webAsset, VaultAsset $repoAsset): array
    {
        $webData = $webAsset->metadata ?? [];
        $repoData = $repoAsset->metadata ?? [];

        // 1. Tech Stack Comparison
        $webStack = collect($webData['tech_assessment']['stack'] ?? [])->pluck('name')->map(fn($n) => strtolower($n));
        $repoStack = collect($repoData['tech_assessment']['stack'] ?? [])->pluck('name')->map(fn($n) => strtolower($n));

        $missingInRepo = $webStack->diff($repoStack)->values()->all();
        $missingInWeb = $repoStack->diff($webStack)->values()->all();
        $matching = $webStack->intersect($repoStack)->values()->all();

        // 2. Score Divergence
        $scoreDiff = ($webAsset->score ?? 0) - ($repoAsset->score ?? 0);

        // 3. File vs Route Coverage
        // Web has topology (routes), Repo has file structure
        $webRoutes = count($webData['topology']['nodes'] ?? []);
        $repoFiles = $repoData['file_count'] ?? 0; // Assuming we add this to repo metadata

        return [
            'scores' => [
                'web' => $webAsset->score ?? 0,
                'repo' => $repoAsset->score ?? 0,
                'diff' => $scoreDiff,
                'interpretation' => $this->interpretScoreDiff($scoreDiff),
            ],
            'stack_analysis' => [
                'matching' => $matching,
                'only_on_live_site' => $missingInRepo,   // e.g. CDN, Analytics, Third-party scripts not in repo
                'only_in_source_code' => $missingInWeb, // e.g. DevTools, Backend logic hidden from frontend
            ],
            'structural_integrity' => [
                'routes_detected' => $webRoutes,
                'source_files_scanned' => $repoFiles,
                'ratio' => $repoFiles > 0 ? round($webRoutes / $repoFiles, 2) : 0
            ],
            'discrepancies' => $this->detectDiscrepancies($webData, $repoData),
        ];
    }

    private function interpretScoreDiff(int $diff): string
    {
        if (abs($diff) <= 5) return "Consistent Quality. The code matches the deployed reality.";
        if ($diff > 10) return "Deployment Optimization. The live site performs significantly better than the raw code suggests (e.g., caching, CDN).";
        if ($diff < -10) return "Implementation Gap. The source code is high quality, but the deployment suffers from performace/configuration issues.";
        return "Minor Divergence.";
    }

    private function detectDiscrepancies(array $web, array $repo): array
    {
        $issues = [];

        // Example: Framework mismatch
        // (Simplified logic)
        
        return $issues;
    }
}
