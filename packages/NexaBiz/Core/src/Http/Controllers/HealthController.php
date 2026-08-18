<?php

namespace NexaBiz\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NexaBiz\Core\Support\DatabaseHealth;

class HealthController extends Controller
{
    public function __invoke(DatabaseHealth $health): JsonResponse
    {
        $ok = $health->ok();

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
