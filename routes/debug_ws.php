<?php

use Illuminate\Support\Facades\Route;
use App\Models\VaultAsset;
use Illuminate\Http\Request;

Route::get('/debug-force-broadcast', function (Request $request) {
    if (!$request->input('asset_id')) {
        return 'Please provide ?asset_id=UUID';
    }
    
    $asset = VaultAsset::find($request->input('asset_id'));
    if (!$asset) return 'Asset not found';
    
    event(new \App\Events\AuditProgressUpdated($asset, 'step_init', 5));
    sleep(1);
    event(new \App\Events\AuditProgressUpdated($asset, 'step_probe', 25));
    
    return 'Broadcast Sent to channel: App.Models.User.' . $asset->user_id;
})->middleware('web');
