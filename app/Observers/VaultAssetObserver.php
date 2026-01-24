<?php

namespace App\Observers;

use App\Models\VaultAsset;
use App\Models\MarketplaceListing;

class VaultAssetObserver
{
    /**
     * Handle the VaultAsset "updated" event.
     */
    public function updated(VaultAsset $asset): void
    {
        // THE SHARK RULE (Ledger Security)
        // If a user edits the file (content/path) or status changes (e.g. re-upload),
        // we MUST pause any active marketplace listing to prevent 'Switch-and-Bait'.
        
        $criticalChanges = $asset->isDirty(['file_path', 'file_size', 'file_name']);
        $statusChanged = $asset->isDirty('status');
        $wasForSale = $asset->getOriginal('is_for_sale');

        // Logic A: Content Modified
        if ($wasForSale && $criticalChanges) {
            $this->pauseListing($asset, 'Asset content modified');
        }

        // Logic B: Status lost verification (e.g. set to 'processing' or 'flagged')
        if ($wasForSale && $statusChanged && $asset->status !== 'verified') {
            $this->pauseListing($asset, "Status changed to {$asset->status}");
        }
    }

    /**
     * Helper to pause the listing and update ledger.
     */
    protected function pauseListing(VaultAsset $asset, string $reason): void
    {
        // 1. Unlist the asset
        // We use quiet update to prevent re-triggering observers if not needed, 
        // though our dirty checks prevent loops anyway.
        $asset->is_for_sale = false;
        $asset->saveQuietly();

        // 2. Update the Ledger Snapshot
        // Mark the active listing as paused
        MarketplaceListing::where('vault_asset_id', $asset->id)
            ->where('status', 'active')
            ->update([
                'status' => 'paused',
                // We could add a 'reason' column to listing table later if needed
            ]);
    }

    /**
     * Handle the VaultAsset "deleted" event.
     */
    public function deleted(VaultAsset $asset): void
    {
        // If cascading delete is not enabled in DB (it is now, but safe to have),
        // we would handle cleanup here.
        // With cascade, this is automatic.
    }
}
