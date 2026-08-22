<?php

use Illuminate\Support\Facades\Route;
use NexaBiz\Identity\Http\Middleware\AuthenticateApi;
use NexaBiz\Initialization\Http\Controllers\BootstrapController;

//Route::prefix('api/v1')->middleware(AuthenticateApi::class)->group(function (): void {
//    Route::get('/bootstrap', [BootstrapController::class, 'status']);
//    Route::get('/bootstrap/data', [BootstrapController::class, 'data']);
//});
