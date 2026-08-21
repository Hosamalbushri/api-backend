<?php

namespace App\Http\Middleware;

use App\Exceptions\TooManyRequestsException;
use App\Support\SlidingWindowLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.$ltrim($request->path(), '/');
        if ($request->isMethod('POST') && in_array($path, ['/api/v1/auth/login', '/api/v1/auth/refresh'], true)) {
            $limit = (int) config('nexabiz.auth_rate_limit_per_minute');
            $limiter = new SlidingWindowLimiter($limit, 60.0);
            $host = $request->ip() ?: 'unknown';
            [$allowed, $retryAfter] = $limiter->allow($host.':'.$path, $limit);
            if (! $allowed) {
                throw new TooManyRequestsException(
                    'Too many authentication attempts. Try again later.',
                    $retryAfter,
                );
            }
        }

        return $next($request);
    }
}
