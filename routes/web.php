<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Api\VaultUploadController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public Marketplace (No Auth Required)
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');

// Global AI Architect Route (Publicly accessible for landing page)
Route::post('/ai/chat', [\App\Http\Controllers\Api\GlobalHelperController::class, 'chat'])->name('ai.chat');
Route::get('/ai/chat/history', [\App\Http\Controllers\Api\GlobalHelperController::class, 'history'])->name('ai.chat.history');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/privacy-policy', function () {
    return \Inertia\Inertia::render('PrivacyPolicy');
})->middleware(['auth'])->name('privacy-policy');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // LUME Pillar 1: CloudVault Routes
    Route::prefix('vault')->name('vault.')->group(function () {
        Route::post('/presigned-url', [VaultUploadController::class, 'getPresignedUrl'])->name('presigned');
        Route::post('/confirm-upload', [VaultUploadController::class, 'confirmUpload'])->name('confirm');
        Route::post('/download', [VaultUploadController::class, 'download'])->name('download');
        Route::delete('/delete', [VaultUploadController::class, 'destroy'])->name('delete');
        Route::post('/detect-context', [VaultUploadController::class, 'detectContext'])->name('detect-context');
    });

    // Test Credits Route (DEV ONLY - Remove in production)
    Route::post('/credits/add-test', function (\Illuminate\Http\Request $request) {
        $request->validate(['amount' => 'required|integer|min:1']);
        $amount = (int) $request->input('amount');
        
        $ledger = app(\App\Services\LedgerService::class);
        $newBalance = $ledger->addTestCredits($request->user(), $amount);
        
        return back()->with('success', "Added {$amount} credits. New Balance: {$newBalance}");
    })->name('credits.add-test');

    // Marketplace Routes
    Route::get('/my-marketplace', [\App\Http\Controllers\MarketplaceController::class, 'myListings'])
        ->name('marketplace.my');
    Route::post('/marketplace/toggle/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'toggleListing'])
        ->name('marketplace.toggle');

    // AI Architect removed (moved to public)

    Route::post('/marketplace/publish/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'publish'])
        ->name('marketplace.publish');

    // Project & Codebase Routes (M&A Platform)
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'myProjects'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'create'])->name('create');
        Route::get('/{project}', [\App\Http\Controllers\Api\ProjectController::class, 'show'])->name('show');
        Route::post('/{project}/health-check', [\App\Http\Controllers\Api\ProjectController::class, 'healthCheck'])->name('health-check');
        Route::post('/{project}/verify-ownership', [\App\Http\Controllers\Api\ProjectController::class, 'verifySiteOwnership'])->name('verify-ownership');
        Route::post('/{project}/validate-credentials', [\App\Http\Controllers\Api\ProjectController::class, 'validateCredentials'])->name('validate-credentials');
        Route::post('/{project}/scan-infrastructure', [\App\Http\Controllers\Api\ProjectController::class, 'scanInfrastructure'])->name('scan-infrastructure');
        Route::post('/{project}/list', [\App\Http\Controllers\Api\ProjectController::class, 'listForSale'])->name('list');
    });
});

require __DIR__.'/auth.php';