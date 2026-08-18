<?php

namespace NexaBiz\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlation = trim((string) ($request->header('X-Correlation-Id') ?: $request->header('X-Request-Id') ?: ''));
        if ($correlation === '') {
            $correlation = (string) Str::uuid();
        }
        $request->attributes->set('correlation_id', $correlation);
        Log::withContext(['correlation_id' => $correlation]);
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlation);
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            Log::channel('sync')->info('request method={m} path={p} correlation_id={c} status={s}', [
                'm' => $request->method(),
                'p' => $request->getPathInfo(),
                'c' => $correlation,
                's' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
