<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VaultAsset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class VaultAuditExportController extends Controller
{
    /**
     * Download the full audit package (PDF Report + CSV Data).
     */
    public function download(Request $request, VaultAsset $asset)
    {
        // Simple authorization (assuming route is guarded by auth:sanctum)
        if ($request->user()->id !== $asset->user_id) {
            abort(403);
        }

        try {
            $data = $asset->metadata;
            $fileNameValues = pathinfo($asset->file_name, PATHINFO_FILENAME);
            
            // Get just the first word (or segment before first non-alphanumeric char)
            $firstWord = preg_split('/[^a-zA-Z0-9]/', $fileNameValues)[0] ?? 'Audit';
            $cleanName = "{$firstWord}";

            // 1. Generate PDF Report
            // "Very good format" styling handled in the view
            $pdf = Pdf::loadView('reports.audit', [
                'asset' => $asset,
                'audit' => $data,
                'facts' => $data['audit_facts'] ?? [],
                'meta' => $data['audit_meta'] ?? []
            ]);
            // Set paper size to A4
            $pdf->setPaper('a4', 'portrait');
            $pdfContent = $pdf->output();

            // 2. Generate CSV Data
            $csvContent = $this->generateCsv($data['audit_facts'] ?? []);

            // 3. Create ZIP Package
            $zipPath = tempnam(sys_get_temp_dir(), 'lume_audit_');
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                // Add PDF
                $zip->addFromString("LUME_Audit_Report_{$cleanName}.pdf", $pdfContent);
                
                // Add CSV
                $zip->addFromString("LUME_Audit_Data_{$cleanName}.csv", $csvContent);
                
                $zip->close();
            } else {
                throw new \Exception("Could not create ZIP file");
            }

            // Return Download
            return response()->download($zipPath, "LUME_Audit_Report_{$cleanName}.zip")->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error("Audit Export Failed: " . $e->getMessage());
            return response()->json(['error' => 'Export failed', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Convert facts array to CSV string.
     */
    protected function generateCsv(array $facts): string
    {
        if (empty($facts)) return "No data found";

        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, ['Status', 'Data Point', 'Category', 'Context', 'Location', 'Judgment', 'Source Text']);

        foreach ($facts as $fact) {
            fputcsv($output, [
                $fact['status'] ?? '',
                $fact['data_point'] ?? '',
                $fact['category'] ?? '',
                $fact['context'] ?? '',
                $fact['location'] ?? '',
                $fact['judgment'] ?? '',
                $fact['source_text'] ?? ''
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
