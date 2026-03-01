<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MarketplaceController extends Controller
{
    /**
     * Publish an asset to the marketplace.
     */
    public function publish(Request $request, VaultAsset $asset)
    {
        if ($asset->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $scanActivity = \Illuminate\Support\Facades\DB::table('scan_activities')
            ->where(function ($query) use ($asset) {
                $query->where('primary_asset_id', $asset->id)
                      ->orWhere('secondary_asset_id', $asset->id);
            })
            ->orderBy('scanned_at', 'desc')
            ->first();

        if ($asset->status === 'verified_private') {
            // Relaxed check
        }

        $syncScore = 0;
        if ($scanActivity) {
            $syncScore = ($scanActivity->type === 'sync') 
                ? ($scanActivity->sync_confidence_score ?? 0) 
                : ($scanActivity->individual_score ?? 0);
        } else {
             $syncScore = $asset->metadata['latest_sync_comparison']['sync_score'] 
                ?? $asset->metadata['score'] 
                ?? 0;
        }
        
        if ($asset->status !== 'verified') {
            if ($syncScore >= 85) {
                $asset->update(['status' => 'verified']);
            } else {
                if ($asset->status !== 'verified_private' && $syncScore < 75) {
                    return back()->withErrors(['asset' => "Asset status is '{$asset->status}' (Score: {$syncScore}). Only verified assets can be listed."]);
                }
            }
        }
        
        $isSync = ($scanActivity && $scanActivity->type === 'sync') || (!empty($asset->metadata['latest_sync_comparison']));
        $isEligible = $asset->metadata['is_marketplace_eligible'] ?? ($asset->status === 'verified' || $asset->status === 'verified_private' || $isSync);
        
        if (!$isEligible && ($syncScore < 75)) {
            $privacyWarning = $asset->metadata['privacy_warning'] ?? 'This asset has not met the minimum quality/sync threshold for listing.';
            return back()->withErrors(['asset' => $privacyWarning]);
        }

        $ledger = app(\App\Services\LedgerService::class);
        $user = Auth::user();

        // TITAN V8.5: Fingerprint Check for Free Updates
        $existingListing = VaultAsset::where('fingerprint_hash', $asset->fingerprint_hash)
            ->where('is_for_sale', true)
            ->where('user_id', $user->id)
            ->exists();

        $isSubscriber = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        // No charge if already listed by owner or if subscriber
        $creditsToDeduct = ($isSubscriber || $existingListing) ? 0 : 20;

        if ($creditsToDeduct > 0 && $ledger->getCredits($user) < 20) {
            return back()->withErrors(['credits' => 'Insufficient credits. You need 20 credits to list an asset.']);
        }

        $price = $request->input('price', 99.00);
        $customName = $request->input('custom_name'); 

        if ($creditsToDeduct > 0) {
            $ledger->consumeCredit($user, (float)$creditsToDeduct, "Listing Fee: {$asset->file_name}");
        }

        if ($customName) {
            $metadata = $asset->metadata ?? [];
            $metadata['custom_name'] = $customName;
            $asset->metadata = $metadata;
        }

        $asset->update([
            'price' => $price,
            'is_for_sale' => true,
            'metadata' => $asset->metadata,
        ]);

        \App\Models\MarketplaceListing::updateOrCreate(
            ['vault_asset_id' => $asset->id],
            [
                'user_id' => Auth::id(),
                'price' => $price,
                // Fix: Cast to int to prevent PGSQL "invalid input syntax for type integer: 89.00"
                'ai_score_snapshot' => (int) ($asset->score ?? $asset->metadata['score'] ?? 0),
                'status' => 'active',
                'updated_at' => now(), 
            ]
        );

        $this->updateMarketplaceJson();

        $message = $isSubscriber ? 'Asset listed successfully! Credits waived (Subscriber).' : 'Asset listed successfully! 20 Credits deducted.';
        return redirect()->route('marketplace.index')->with('success', $message);
    }

    private function updateMarketplaceJson()
    {
        $listings = VaultAsset::where('is_for_sale', true)
            ->whereNotNull('price')
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'title' => $asset->metadata['custom_name'] ?? $asset->file_name,
                    'file_name' => $asset->file_name,
                    'price' => (float) $asset->price,
                    // Fix: Use the authoritative score column
                    'score' => $asset->score ?? $asset->metadata['score'] ?? 0,
                    'category' => $asset->metadata['category'] ?? 'Uncategorized',
                    'seller' => $asset->user->name,
                    'listed_at' => $asset->updated_at->toIso8601String(),
                ];
            });

        \Illuminate\Support\Facades\Storage::disk('public')->put('marketplace_listings.json', $listings->toJson());
    }

    public function toggleListing(Request $request, VaultAsset $asset)
    {
        if ($asset->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $scanActivity = \Illuminate\Support\Facades\DB::table('scan_activities')
            ->where(function ($query) use ($asset) {
                $query->where('primary_asset_id', $asset->id)
                      ->orWhere('secondary_asset_id', $asset->id);
            })
            ->orderBy('scanned_at', 'desc')
            ->first();

        if ($asset->status === 'verified_private') {
        }

        $syncScore = 0;
        if ($scanActivity) {
            $syncScore = ($scanActivity->type === 'sync')
                ? ($scanActivity->sync_confidence_score ?? 0)
                : ($scanActivity->individual_score ?? 0);
        } else {
             $syncScore = $asset->metadata['latest_sync_comparison']['sync_score']
                ?? $asset->metadata['score']
                ?? 0;
        }

        if ($asset->status !== 'verified') {
             if ($syncScore >= 85) {
                 $asset->update(['status' => 'verified']);
             } else {
                 if ($asset->status !== 'verified_private' && $syncScore < 75) {
                    return back()->withErrors(['asset' => "Asset status is '{$asset->status}' (Score: {$syncScore}). Only verified assets can be listed."]);
                 }
             }
        }

        $isEligible = $asset->metadata['is_marketplace_eligible'] ?? ($asset->status === 'verified' || $asset->status === 'verified_private');

        if (!$isEligible && $request->boolean('is_for_sale') && $syncScore < 75) {
            $privacyWarning = $asset->metadata['privacy_warning'] ?? 'This asset has not met the minimum quality threshold for listing.';
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

        $this->updateMarketplaceJson();

        $message = $validated['is_for_sale'] 
            ? 'Asset listed on marketplace successfully!' 
            : 'Asset removed from marketplace.';

        return back()->with('success', $message);
    }

    public function index()
    {
        $listings = \App\Models\MarketplaceListing::whereIn('status', ['active', 'updating', 'flagged'])
            ->has('asset')
            ->with(['user:id,name', 'asset.purchase', 'asset.latestSyncActivity'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($listing) {
                $asset = $listing->asset;
                
                // Convert to array to ensure dynamic properties are serialized
                $assetArray = $asset->toArray();

                // Attach authoritative sync score
                $assetArray['latest_sync_score'] = $asset->latestSyncActivity?->sync_confidence_score 
                    ?? $listing->ai_score_snapshot 
                    ?? $asset->score 
                    ?? 0;
                
                // Helper for purchase status
                $assetArray['is_purchased'] = !is_null($asset->purchase);
                
                // Overlay price and custom name from listing/request
                $assetArray['price'] = $listing->price;
                
                // TITAN V7: Attach Marketplace Status
                $assetArray['marketplace_status'] = $listing->status;
                
                // IMPORTANT: Attach the seller (user) from the Listing to the Asset object
                // The frontend expects asset.user.name
                $assetArray['user'] = $listing->user;
                
                return $assetArray;
            });

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            'viewMode' => 'browse',
            'isAuthenticated' => Auth::check(),
            'authUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ] : null,
        ]);
    }

    public function myListings()
    {
        $listings = VaultAsset::where('user_id', Auth::id())
            ->where('is_for_sale', true) 
            ->with(['user:id,name', 'purchase'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            'viewMode' => 'mylistings',
            'isAuthenticated' => Auth::check(),
            'authUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ] : null,
        ]);
    }

    public function show(VaultAsset $asset)
    {
        // Check if sold (has purchase) - if so, only allow owner or purchaser
        // For public marketplace, we might want to still show it but as "Sold".
        $isPurchased = \App\Models\Purchase::where('vault_asset_id', $asset->id)->exists();
        
        // Allow public viewing even if sold (user requested "Sold" badge on list, implying visibility on detail too likely, or at least list)
        // But for detail view, let's keep it visible but disable actions.
        if (!$asset->is_for_sale && $asset->user_id !== Auth::id() && !$isPurchased) {
             // If completely private (not sold, just toggled off), hide.
             abort(404);
        }

        $asset->load('user:id,name', 'purchase'); // Load purchase to show 'Sold' status

        // FETCH THE RAW SYNC SCORE DIRECTLY FROM SCAN ACTIVITIES
        // This resolves the 84% vs 85% discrepancy by bypassing the asset model cache/column
        $latestSyncScan = \Illuminate\Support\Facades\DB::table('scan_activities')
            ->where(function ($query) use ($asset) {
                $query->where('primary_asset_id', $asset->id)
                      ->orWhere('secondary_asset_id', $asset->id);
            })
            ->where('type', 'sync')
            ->orderBy('scanned_at', 'desc')
            ->first();

        return Inertia::render('Marketplace/AssetView', [
            'asset' => $asset,
            'latestSyncScan' => $latestSyncScan,
            'isAuthenticated' => Auth::check(),
            'authUser' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ] : null,
        ]);
    }
    
    /**
     * Process asset acquisition.
     */
    public function purchase(Request $request, VaultAsset $asset)
    {
        if (!Auth::check()) {
            abort(401);
        }

        // Simulate successful payment
        $price = (float) $asset->price;
        $fee = $price * 0.05;

        // Create Purchase Record
        \App\Models\Purchase::create([
            'user_id' => Auth::id(),
            'vault_asset_id' => $asset->id,
            'price' => $price,
            'fee' => $fee,
            'transaction_id' => 'sim_' . \Illuminate\Support\Str::random(10),
            'status' => 'completed',
            'unlocked_at' => now(),
        ]);
        
        // Mark asset as SOLD (not for sale anymore)
        $asset->update(['is_for_sale' => false]);
        $this->updateMarketplaceJson();

        return redirect()->route('marketplace.vault.show', $asset->id)->with('success', 'Asset acquired successfully! Guide unlocked.');
    }

    /**
     * Display the secure vault for a purchased asset.
     */
    public function vault(VaultAsset $asset, \App\Services\DeploymentGuideService $guideService)
    {
         $userId = Auth::id();
         
         // Check Access: Owner OR Purchaser
         $hasPurchased = \App\Models\Purchase::where('user_id', $userId)
             ->where('vault_asset_id', $asset->id)
             ->exists();

         if ($asset->user_id !== $userId && !$hasPurchased) {
             abort(403, 'Access Denied. You must acquire this asset to view the vault.');
         }

         // Generate Deployment Guide
         $guide = $guideService->generate($asset);

         $asset->load('user:id,name');

         return Inertia::render('Marketplace/BuyerVault', [
             'asset' => $asset,
             'deploymentGuide' => $guide,
             'isPurchased' => true,
             'isAuthenticated' => true,
             'authUser' => [
                 'id' => $userId,
                 'name' => Auth::user()->name,
             ]
         ]);
    }
    public function checkDuplicate(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'repo_url' => 'nullable|string'
        ]);

        $fingerprint = $validated['fingerprint'];

        // Logic: Find ANY other VaultAsset that has the same fingerprint AND is listed (is_for_sale=true)
        // AND does not belong to the current user (you can list duplicates of your own stuff if you want, or maybe not? 
        // User checklist says "Disable List button if it's a DIFFERENT user").
        // Also exclude the current asset itself if it's being updated (though usually check is before listing)

        $duplicate = VaultAsset::where('fingerprint_hash', $fingerprint)
            ->where('is_for_sale', true)
            ->with('user:id,name')
            ->first();

        // Fallback: Check Repo URL for EXACT match if fingerprint somehow misses (unlikely but safe) 
        // NOTE: User REJECTED exact URL matching for security, but "Sovereign Hash" is primary. 
        // We will stick to Hash only as per instructions to avoid "fork & rename" loopholes.
        
        if ($duplicate) {
            $isOwnedByMe = ($duplicate->user_id === Auth::id());
            
            return response()->json([
                'is_duplicate' => !$isOwnedByMe, // If I own it, it's not a "conflict" but an "update"
                'is_owned_by_me' => $isOwnedByMe,
                'original_asset' => [
                    'id' => $duplicate->id,
                    'file_name' => $duplicate->metadata['custom_name'] ?? $duplicate->file_name,
                    'seller_name' => $duplicate->user->name ?? 'Unknown',
                    'listed_at' => $duplicate->updated_at->diffForHumans()
                ]
            ]);
        }

        return response()->json(['is_duplicate' => false]);
    }
}
