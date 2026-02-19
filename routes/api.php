<?php

use App\Http\Controllers\Api\VaultUploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public Support Routes
Route::post('/support/github-guide', [\App\Http\Controllers\Api\AIGuideController::class, 'askGitHubHelp']);

// Unified Lume AI Support Routes
Route::middleware(['web', 'auth'])->prefix('ai')->group(function () {
    Route::post('/chat', [\App\Http\Controllers\Api\LumeAISupportController::class, 'chat']);
    Route::get('/history', [\App\Http\Controllers\Api\LumeAISupportController::class, 'history']);
    Route::get('/conversations', [\App\Http\Controllers\Api\LumeAISupportController::class, 'conversations']); // New Route
});

// Vault Upload Routes (uses web session authentication for Inertia.js)
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/vault/presigned-url', [VaultUploadController::class, 'getPresignedUrl']);
    Route::post('/vault/confirm-upload', [VaultUploadController::class, 'confirmUpload']);
    Route::get('/vault/assets/{asset}', [VaultUploadController::class, 'getAsset']);
    Route::get('/vault/assets/{asset}/history', [VaultUploadController::class, 'getHistory']);
    Route::post('/vault/assets/{asset}/retry', [VaultUploadController::class, 'retryAudit']);
    Route::post('/vault/deep-audit', [VaultUploadController::class, 'runDeepAudit']);
    Route::post('/projects/sync', [VaultUploadController::class, 'syncProjectAndRepo']);
    Route::get('/vault/assets/{asset}/export', [\App\Http\Controllers\Api\VaultAuditExportController::class, 'download']);
});
