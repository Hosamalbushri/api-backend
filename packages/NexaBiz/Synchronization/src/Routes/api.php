<?php

use Illuminate\Support\Facades\Route;
use NexaBiz\Identity\Http\Middleware\AuthenticateApi;
use NexaBiz\Initialization\Http\Controllers\BootstrapController;
use NexaBiz\Synchronization\Http\Controllers\SyncController;

Route::prefix('api/v1')->middleware(AuthenticateApi::class)->group(function (): void {
    Route::post('/sync/push', [SyncController::class, 'push']);
    Route::post('/sync/push/batch', [SyncController::class, 'pushBatch']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::get('/sync/meta/{entityType}/{entityId}', [SyncController::class, 'meta']);
    Route::get('/bootstrap', [BootstrapController::class, 'status']);
    Route::get('/bootstrap/data', [BootstrapController::class, 'data']);
});
