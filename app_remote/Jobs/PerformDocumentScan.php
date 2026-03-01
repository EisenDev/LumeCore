<?php

namespace App\Jobs;

use App\Events\AssetStatusUpdated;
use App\Events\AuditFailed;
use App\Events\AuditProgressUpdated;
use App\Models\VaultAsset;
use App\Services\AI\DocumentAuditor;
use App\Services\LedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;

/**
 * PerformDocumentScan Job
 * 
 * Handles the background analysis of documents (PDF, DOCX, Images).
 * Uses PHP-native PDF parsing (Smalot\PdfParser) and DocumentAuditor AI service.
 * 
 * NO Python dependencies required.
 */
class PerformDocumentScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public VaultAsset $asset
    ) {}

    public function handle(DocumentAuditor $auditor, LedgerService $ledgerService): void
    {
        Log::info("LUME DOCUMENT SCAN: Starting scan for asset {$this->asset->id} ({$this->asset->file_name})");

        try {
            // 1. UPDATE STATUS
            $this->asset->update([
                'status' => 'processing',
                'metadata' => array_merge($this->asset->metadata ?? [], ['audit_type' => 'document'])
            ]);
            
            AssetStatusUpdated::dispatch($this->asset);
            AuditProgressUpdated::dispatch($this->asset, 'Initializing Document Scanner...', 10);

            // 2. GET FILE FROM R2
            AuditProgressUpdated::dispatch($this->asset, 'Downloading document from vault...', 15);
            $fileContent = Storage::disk('r2')->get($this->asset->file_path);

            if (empty($fileContent)) {
                throw new \Exception("File content is empty or could not be retrieved from R2.");
            }

            Log::info("LUME DOCUMENT SCAN: File retrieved, size: " . strlen($fileContent) . " bytes");

            // 3. EXTRACT TEXT (TITAN FORENSIC SCANNER - Python)
            AuditProgressUpdated::dispatch($this->asset, 'Initializing Titan Forensic Engine...', 25);
            
            $mimeType = $this->asset->mime_type;
            $text = '';
            $signals = [
                'page_count' => 0,
                'word_count' => 0,
                'has_images' => false,
                'is_encrypted' => false,
                'metadata' => []
            ];

            // Define Python Script Path
            $pythonScript = base_path('app/Services/Python/TitanDocumentScanner.py');
            
            // Create a temp file for the python script to read (since R2 stream might not be a local file path)
            // We need a physical file for python to open
            $tempPath = tempnam(sys_get_temp_dir(), 'lume_doc_');
            file_put_contents($tempPath, $fileContent);
            
            // Rename to correct extension so Python parser detects type
            $extension = match ($mimeType) {
                'application/pdf' => '.pdf',
                'application/msword' => '.doc', // Fix for .doc
                default => '.docx',
            }; 
            if (Str::startsWith($mimeType, 'image/')) $extension = '.img'; // Python script doesn't handle images yet, but logic handles it below
            
            $validPath = $tempPath . $extension;
            rename($tempPath, $validPath);

            try {
                $supportedTypes = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/msword', // Add .doc support
                ];

                if (in_array($mimeType, $supportedTypes)) {
                    
                    AuditProgressUpdated::dispatch($this->asset, 'Executing Forensic Extraction...', 30);

                    // Execute Python Script
                    $projectRoot = base_path();
                    // Detect OS for python command
                    $pythonCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'python' : 'python3';
                    
                    $command = "{$pythonCmd} " . escapeshellarg($pythonScript) . " " . escapeshellarg($validPath);
                    
                    // Capture output
                    $output = shell_exec($command . " 2>&1");
                    
                    // Parse JSON Result
                    $result = json_decode($output, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error("Titan Scanner JSON Error: " . json_last_error_msg(), ['output' => $output]);
                        // Fallback but do NOT use raw content for binary files
                        $text = "[Extraction Failed] Could not parse document content. (JSON Error)"; 
                    } elseif (isset($result['error'])) {
                        // Handle Titan specific errors
                        if ($result['error'] === 'encrypted_file') {
                            $this->asset->update(['status' => 'failed_encryption']);
                            AuditFailed::dispatch($this->asset, 'Document is password protected.');
                            unlink($validPath);
                            return;
                        }
                        // Soft fail instead of crashing job
                        $text = "[Extraction Error] " . ($result['message'] ?? 'Unknown error');
                    } else {
                        // Success traversal
                        $text = $result['full_text'] ?? '';
                        $signals = array_merge($signals, $result['signals'] ?? []);
                        
                        Log::info("TITAN SCAN COMPLETE", [
                            'pages' => $signals['page_count'],
                            'images' => $signals['has_images'],
                            'chars' => strlen($text)
                        ]);
                    }

                } elseif (Str::startsWith($mimeType, 'image/')) {
                    // Vision API Handler (Titan Python doesn't handle raw images yet)
                    AuditProgressUpdated::dispatch($this->asset, 'Processing Image Document...', 40);
                    $text = '[IMAGE DOCUMENT - Visual analysis required]';
                    $signals['has_images'] = true;
                } else {
                    // Fallback for txt/other - Only use raw content if it's text /* CHECK THIS */
                    // Safer: treat as plain text but sanitize later
                    $text = $fileContent;
                }

                // CRITICAL SAFETY: Sanitize Text for Database (Postgres hates Null Bytes)
                // Remove null bytes and ensure valid UTF-8
                $text = str_replace(chr(0), '', $text);
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

            } finally {
                // Cleanup Temp File
                if (file_exists($validPath)) {
                    unlink($validPath);
                }
            }

            $signals['word_count'] = str_word_count($text);

            // FALLBACK: Vision API for Scanned PDFs (image-heavy, no text)
            // If Titan returns empty text but signals images, use Vision
            if (empty(trim($text)) && $mimeType === 'application/pdf' && ($signals['has_images'] ?? false)) {
                Log::info("LUME DOCUMENT SCAN: Image-heavy PDF detected by Titan, switching to Vision AI.");
                AuditProgressUpdated::dispatch($this->asset, 'Image-heavy PDF detected, scanning visuals...', 45);
                $text = base64_encode($fileContent);
            }

            // 4. AI ANALYSIS
            AuditProgressUpdated::dispatch($this->asset, 'AI Forensic Analysis in progress...', 55);
            Log::info("LUME DOCUMENT SCAN: Sending to DocumentAuditor for AI analysis...");

            $analysis = $auditor->analyzeDocument($text, $mimeType, $signals);
            
            Log::info("LUME DOCUMENT SCAN: Analysis Complete.", ['score' => $analysis['score'] ?? 'N/A']);

            // 5. PERSISTENCE
            AuditProgressUpdated::dispatch($this->asset, 'Finalizing Report...', 90);

            $score = intval($analysis['score'] ?? 0);

            // Compute derived signals for UI
            $wordCount = $signals['word_count'] ?? 0;
            $pageCount = $signals['page_count'] ?? 1;
            $signals['read_time'] = max(1, intval(ceil($wordCount / 250))); // avg 250 wpm
            $signals['layout_density'] = $wordCount > 0 && $pageCount > 0
                ? ($wordCount / $pageCount > 300 ? 'Dense Layout' : ($wordCount / $pageCount > 150 ? 'Standard Layout' : 'Sparse Layout'))
                : 'Unknown';

            // Status Logic
            $finalStatus = 'verified';
            if (!empty($analysis['verdict'])) {
                if ($analysis['verdict'] === 'verified_private') $finalStatus = 'verified_private';
                elseif (str_contains($analysis['verdict'], 'rejected') || $analysis['verdict'] === 'action_required') $finalStatus = 'flagged';
            }
            if ($finalStatus === 'verified' && $score < 70) $finalStatus = 'action_required';

            // Store full text (truncated to 50KB for DB) for AI Chat context
            $storedText = mb_substr($text, 0, 50000);

            $this->asset->update([
                'status' => $finalStatus,
                'score' => $score,
                'metadata' => array_merge($this->asset->metadata ?? [], $analysis, [
                    'audit_type' => 'document',
                    'analyzed_at' => now(),
                    'doc_signals' => $signals,
                    'full_text' => $storedText,
                ])
            ]);

            // 6. ACTIVITY LOG
            \App\Models\ScanActivity::create([
                'user_id' => $this->asset->user_id,
                'primary_asset_id' => $this->asset->id,
                'type' => 'document',
                'docu_and_urls_status' => $finalStatus,
                'individual_score' => $score,
                'urls_and_sync' => $this->asset->file_name, // Explicitly save filename
                'details' => json_encode($analysis),
                'scanned_at' => now(),
            ]);

            AuditProgressUpdated::dispatch($this->asset, 'Audit Complete.', 100);
            AssetStatusUpdated::dispatch($this->asset);

            Log::info("LUME DOCUMENT SCAN: Successfully completed for asset {$this->asset->id}. Score: {$score}, Status: {$finalStatus}");

        } catch (\Throwable $e) {
            Log::error("LUME DOCUMENT SCAN FAILED: " . $e->getMessage(), [
                'asset_id' => $this->asset->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Refund only on system crash, not logic failure
            if (!str_contains($e->getMessage(), 'Scanner Error')) {
                $ledgerService->refundAuditCredits($this->asset->user, 'document', $this->asset->file_name);
            }
            
            $this->asset->update(['status' => 'failed_system']);
            AuditFailed::dispatch($this->asset, 'Scan Error: ' . $e->getMessage());
            AssetStatusUpdated::dispatch($this->asset);
        }
    }
}
