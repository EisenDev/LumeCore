<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VaultAsset;
use App\Models\AssetHealthLog;
use App\Events\AssetStatusUpdated;
use App\Services\HealthCheckService;
use Illuminate\Support\Facades\Http;

class CheckAssetHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lume:check-asset-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit uptime and domain health for marketplace assets. Auto-delist if critical failure.';

    /**
     * Execute the console command.
     */
    public function handle(HealthCheckService $healthService)
    {
        $assets = VaultAsset::where('is_for_sale', true)
            ->whereNotNull('metadata->website_url') // Only check assets with URLs
            ->get();

        $this->info("Checking health for {$assets->count()} assets...");

        foreach ($assets as $asset) {
            $url = $asset->metadata['website_url'] ?? null;
            if (!$url) continue;

            $this->checkAsset($asset, $url, $healthService);
        }
    }

    protected function checkAsset(VaultAsset $asset, string $url, HealthCheckService $healthService)
    {
        $domain = parse_url($url, PHP_URL_HOST) ?? $url;

        // 1. HTTP HEAD Check for 200 OK
        $isUp = false;
        $statusCode = 0;
        $responseTime = 0;
        $error = null;

        try {
            $start = microtime(true);
            $response = Http::timeout(10)->head($url);
            $responseTime = (int) ((microtime(true) - $start) * 1000);
            $statusCode = $response->status();
            $isUp = $response->ok(); // 200-299 range
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        
        AssetHealthLog::create([
            'vault_asset_id' => $asset->id,
            'check_type' => 'http',
            'status' => $isUp ? 'ok' : 'failed',
            'response_time_ms' => $responseTime,
            'message' => $error ?? ($isUp ? 'OK' : "HTTP {$statusCode}"),
        ]);

        // Auto-Delist on Critical Failure (521, 5xx, or timeout)
        if (!$isUp || ($statusCode >= 500)) {
            $this->delistAsset($asset, $error ?? "Server Unreachable (HTTP {$statusCode})");
            return;
        }

        // 2. Domain Expiry Check
        $whois = $healthService->checkDomainWhois($domain);
        
        if ($whois['days_until_expiry'] !== null && $whois['days_until_expiry'] <= 0) {
             AssetHealthLog::create([
                'vault_asset_id' => $asset->id,
                'check_type' => 'domain',
                'status' => 'failed',
                'message' => 'Domain Expired',
            ]);
            $this->delistAsset($asset, "Domain expired.");
        }
    }

    protected function delistAsset(VaultAsset $asset, string $reason)
    {
        // Update asset with is_for_sale: false and health_error in metadata
        $metadata = $asset->metadata ?? [];
        $metadata['health_error'] = $reason;
        
        $asset->update([
            'is_for_sale' => false,
            'metadata' => $metadata,
        ]);
        
        // Notify User CLI
        $this->error("Delisted Asset #{$asset->id}: {$reason}");
        
        // Broadcast real-time update to user's dashboard via Reverb
        event(new AssetStatusUpdated($asset->fresh()));

        // Log the delisting event
        AssetHealthLog::create([
            'vault_asset_id' => $asset->id,
            'check_type' => 'enforcement',
            'status' => 'failed',
            'message' => "Auto-delisted: {$reason}",
        ]);
    }
}
