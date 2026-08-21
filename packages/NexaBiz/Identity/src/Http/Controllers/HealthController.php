<?php

namespace NexaBiz\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NexaBiz\Core\Http\Controllers\Controller;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health — lightweight server health and API discovery check.
     */
    public function check(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'server' => [
                'name' => 'NexaBiz ERP Server',
                'version' => (string) config('nexabiz.app_version', '1.0.0'),
                'api_version' => 'v1',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
