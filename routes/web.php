<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\Api\VaultUploadController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Documentation
Route::get('/documentation', [\App\Http\Controllers\DocumentationController::class, 'index'])->name('documentation.index');
Route::get('/docs', [\App\Http\Controllers\DocumentationController::class, 'index']); // Alias

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public Marketplace (No Auth Required)
// Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
// Route::get('/marketplace/asset/{asset}', [MarketplaceController::class, 'show'])->name('marketplace.asset.view');

// Global AI Architect Route (Publicly accessible for landing page)
Route::post('/ai/chat', [\App\Http\Controllers\Api\GlobalHelperController::class, 'chat'])->name('ai.chat');
Route::post('/ai/detect', [\App\Http\Controllers\Api\GlobalHelperController::class, 'aiDetection'])->name('ai.detect');

Route::get('/overview', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('overview');

Route::get('/overview/refresh', [DashboardController::class, 'refresh'])
    ->middleware(['auth', 'verified'])
    ->name('overview.refresh');

Route::delete('/scan-activities/{activity}', [DashboardController::class, 'destroyActivity'])
    ->middleware(['auth'])
    ->name('scan-activities.destroy');

// Legal & Standards Pages
Route::get('/privacy-policy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return Inertia::render('TermsOfService');
})->name('terms-of-service');

Route::get('/forensic-standards', function () {
    return Inertia::render('ForensicStandards');
})->name('forensic-standards');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Organazitaion & Team Management
    Route::get('/organazitaions', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organizations/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');
Route::get('/organizations/roadmap', [OrganizationController::class, 'roadmap'])->name('organizations.roadmap');
    Route::get('/organazitaions/{organization}/team', [TeamController::class, 'index'])->name('team.index');

    // Billing & Usage
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe', [\App\Http\Controllers\BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/cancel', [\App\Http\Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/toggle-auto-renew', [\App\Http\Controllers\BillingController::class, 'toggleAutoRenew'])->name('billing.toggle-auto-renew');
    Route::get('/billing/invoice/{id}', [\App\Http\Controllers\BillingController::class, 'downloadInvoice'])->name('billing.invoice.show');

    // LUME Pillar 1: CloudVault Routes
    Route::prefix('vault')->name('vault.')->group(function () {
        Route::post('/presigned-url', [VaultUploadController::class, 'getPresignedUrl'])->name('presigned');
        Route::post('/confirm-upload', [VaultUploadController::class, 'confirmUpload'])->name('confirm');
        Route::post('/download', [VaultUploadController::class, 'download'])->name('download');
        Route::delete('/delete', [VaultUploadController::class, 'destroy'])->name('delete');
        Route::post('/detect-context', [VaultUploadController::class, 'detectContext'])->name('detect-context');
        Route::post('/scan-document', [VaultUploadController::class, 'scanDocument'])->name('scan-document');
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
    // Route::get('/my-marketplace', [\App\Http\Controllers\MarketplaceController::class, 'myListings'])
    //     ->name('marketplace.mylistings');

    // Acquisition & Vault Routes
    // Route::post('/marketplace/purchase/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'purchase'])
    //     ->name('marketplace.purchase');
    // Route::get('/marketplace/vault/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'vault'])
    //     ->name('marketplace.vault.show');
        
    // Route::post('/marketplace/toggle/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'toggleListing'])
    //     ->name('marketplace.asset.toggle');

    // Route::post('/marketplace/check-duplicate', [\App\Http\Controllers\MarketplaceController::class, 'checkDuplicate'])
    //     ->name('marketplace.check-duplicate');

    // Route::post('/marketplace/publish/{asset}', [\App\Http\Controllers\MarketplaceController::class, 'publish'])
    //     ->name('marketplace.publish');

    // Scans Route
    Route::get('/scans', [\App\Http\Controllers\ScansController::class, 'index'])->name('scans.index');
    Route::get('/targets', [\App\Http\Controllers\TargetsController::class, 'index'])->name('targets.index');
    Route::get('/integrations', [\App\Http\Controllers\IntegrationsController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/{platform}/toggle', [\App\Http\Controllers\IntegrationsController::class, 'toggle'])->name('integrations.toggle');
    Route::get('/schedules', [\App\Http\Controllers\SchedulesController::class, 'index'])->name('schedules.index');
    Route::get('/ai-assistant', [\App\Http\Controllers\AIAssistantController::class, 'index'])->name('ai-assistant.index');
    Route::get('/ai/chat/history', [\App\Http\Controllers\Api\GlobalHelperController::class, 'history'])->name('ai.chat.history');
    Route::get('/ai/history', [\App\Http\Controllers\Api\GlobalHelperController::class, 'sessions'])->name('ai.history');
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/teams', [\App\Http\Controllers\TeamController::class, 'indexGlobal'])->name('teams.index');

    // Scanned Website Results Page (Refactored from Modal)
    Route::get('/overview/website/{hash}', [DashboardController::class, 'showWebsiteResults'])->name('website.results');

    // Notifications Routes
    Route::patch('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');


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
        Route::post('/compare', [\App\Http\Controllers\Api\ProjectController::class, 'compare'])->name('compare');
    });
});



require __DIR__.'/auth.php';