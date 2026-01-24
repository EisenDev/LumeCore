<?php

namespace App\Http\Controllers;

use App\Models\VaultAsset;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with user's vault assets and wallet.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Fetch user's wallet
        $wallet = Wallet::where('user_id', $user->id)->first();

        // Fetch ALL user's vault assets (pending, uploaded, verified, flagged, etc.)
        $assets = VaultAsset::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (VaultAsset $asset) {
                return [
                    'id' => $asset->id,
                    'file_name' => $asset->file_name,
                    'file_path' => $asset->file_path,
                    'file_size' => $asset->file_size,
                    'mime_type' => $asset->mime_type,
                    'status' => $asset->status,
                    'metadata' => $asset->metadata,
                    'created_at' => $asset->created_at->toISOString(),
                    'updated_at' => $asset->updated_at->toISOString(),
                ];
            });

        return Inertia::render('Dashboard', [
            'initialAssets' => $assets,
            'wallet' => $wallet ? [
                'id' => $wallet->id,
                'balance' => (float) $wallet->balance,
                'currency' => $wallet->currency,
                'credits' => $wallet->credits,
            ] : null,
        ]);
    }
}
