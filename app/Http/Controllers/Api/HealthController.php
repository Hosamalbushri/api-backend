<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sync\SyncService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(SyncService $sync): JsonResponse
    {
        $ok = $sync->databaseOk();

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'database' => $ok ? 'ok' : 'error',
            'app' => config('nexabiz.app_name'),
            'env' => config('nexabiz.app_env'),
        ]);
    }

    public function root(): JsonResponse
    {
        return response()->json([
            'app' => config('nexabiz.app_name'),
            'status' => 'experimental',
            'docs' => '/up',
            'health' => '/health',
            'auth' => '/api/v1/auth/login',
        ]);
    }
}
