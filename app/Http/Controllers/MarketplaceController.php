<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    /**
     * Toggle an asset's marketplace listing status.
     *
     * @param Request $request
     * @param VaultAsset $asset
     * @return \Illuminate\Http\RedirectResponse
     */
    /**
     * Publish an asset to the marketplace.
     */
    public function publish(Request $request, VaultAsset $asset)
    {
        // Ensure user owns this asset
        if ($asset->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Ensure asset is verified before allowing listing
        if ($asset->status === 'verified_private') {
            return back()->withErrors(['asset' => 'This asset contains personal information (PII) and cannot be listed on the public marketplace.']);
        }

        if ($asset->status !== 'verified') {
            return back()->withErrors(['asset' => 'Only verified assets can be listed on the marketplace.']);
        }

        // Check marketplace eligibility from AI audit
        $isEligible = $asset->metadata['is_marketplace_eligible'] ?? false;
        if (!$isEligible) {
            $privacyWarning = $asset->metadata['privacy_warning'] ?? 'This asset contains personal information and cannot be sold.';
            return back()->withErrors(['asset' => $privacyWarning]);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0.01|max:999999.99',
        ]);

        $asset->update([
            'price' => $validated['price'],
            'is_for_sale' => true,
        ]);

        // Create Ledger Entry (Snapshot)
        \App\Models\MarketplaceListing::create([
            'vault_asset_id' => $asset->id,
            'user_id' => Auth::id(),
            'price' => $validated['price'],
            'ai_score_snapshot' => $asset->metadata['score'] ?? 0,
            'status' => 'active',
        ]);

        return back()->with('success', 'Asset is now LIVE on the public marketplace!');
    }

    /**
     * Toggle an asset's marketplace listing status.
     *
     * @param Request $request
     * @param VaultAsset $asset
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleListing(Request $request, VaultAsset $asset)
    {
        // Ensure user owns this asset
        if ($asset->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Ensure asset is verified before allowing listing
        if ($asset->status === 'verified_private') {
            return back()->withErrors(['asset' => 'This asset contains personal information (PII) and cannot be listed on the public marketplace.']);
        }

        if ($asset->status !== 'verified') {
            return back()->withErrors(['asset' => 'Only verified assets can be listed on the marketplace.']);
        }

        // Check marketplace eligibility from AI audit
        $isEligible = $asset->metadata['is_marketplace_eligible'] ?? false;
        if (!$isEligible && $request->boolean('is_for_sale')) {
            $privacyWarning = $asset->metadata['privacy_warning'] ?? 'This asset contains personal information and cannot be sold.';
            return back()->withErrors(['asset' => $privacyWarning]);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'is_for_sale' => 'required|boolean',
        ]);

        $asset->update([
            'price' => $validated['price'],
            'is_for_sale' => $validated['is_for_sale'],
        ]);

        $message = $validated['is_for_sale'] 
            ? 'Asset listed on marketplace successfully!' 
            : 'Asset removed from marketplace.';

        return back()->with('success', $message);
    }

    /**
     * Display the public marketplace.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Fetch assets that are for sale, verified, AND marketplace eligible
        $listings = VaultAsset::where('is_for_sale', true)
            ->where('status', 'verified')
            ->whereJsonContains('metadata->is_marketplace_eligible', true)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            'isAuthenticated' => Auth::check(),
            'authUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ] : null,
        ]);
    }

    /**
     * Display the user's own marketplace listings.
     *
     * @return \Inertia\Response
     */
    public function myListings()
    {
        // Fetch all verified assets for the current user that could be listed
        $listings = VaultAsset::where('user_id', Auth::id())
            ->where('status', 'verified')
            ->whereJsonContains('metadata->is_marketplace_eligible', true)
            ->orderBy('is_for_sale', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Vault/MyMarketplace', [
            'listings' => $listings,
        ]);
    }
}
