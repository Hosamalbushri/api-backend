<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\AuthenticateApi;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware(AuthenticateApi::class)->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/switch-company', [AuthController::class, 'switchCompany']);

        Route::post('/sync/push', [SyncController::class, 'push']);
        Route::post('/sync/push/batch', [SyncController::class, 'pushBatch']);
        Route::get('/sync/pull', [SyncController::class, 'pull']);
        Route::get('/sync/meta/{entityType}/{entityId}', [SyncController::class, 'meta']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{userId}', [UserController::class, 'show']);
        Route::patch('/users/{userId}', [UserController::class, 'update']);
        Route::post('/users/{userId}/status', [UserController::class, 'setStatus']);
        Route::delete('/users/{userId}', [UserController::class, 'destroy']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{roleId}', [RoleController::class, 'show']);
        Route::patch('/roles/{roleId}', [RoleController::class, 'update']);
        Route::delete('/roles/{roleId}', [RoleController::class, 'destroy']);
        Route::get('/permissions', [RoleController::class, 'permissions']);

        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::patch('/companies/{companyId}', [CompanyController::class, 'update']);
        Route::get('/companies/{companyId}/members', [CompanyController::class, 'members']);
        Route::post('/companies/{companyId}/members', [CompanyController::class, 'addMember']);
        Route::patch('/companies/{companyId}/members/{membershipId}', [CompanyController::class, 'updateMember']);
        Route::delete('/companies/{companyId}/members/{membershipId}', [CompanyController::class, 'removeMember']);

        Route::get('/devices', [DeviceController::class, 'index']);
        Route::post('/devices/register', [DeviceController::class, 'register']);
        Route::post('/devices/sync-disable-requests', [DeviceController::class, 'createSyncDisableRequest']);
        Route::get('/devices/sync-disable-requests', [DeviceController::class, 'listSyncDisableRequests']);
        Route::post('/devices/sync-disable-requests/{requestId}/approve', [DeviceController::class, 'approveSyncDisable']);
        Route::post('/devices/sync-disable-requests/{requestId}/reject', [DeviceController::class, 'rejectSyncDisable']);
        Route::post('/devices/{deviceId}/revoke', [DeviceController::class, 'revoke']);
    });
});
