<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HealthController::class, 'root']);
Route::get('/health', HealthController::class);
