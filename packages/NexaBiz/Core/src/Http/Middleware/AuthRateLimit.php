<?php

namespace NexaBiz\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NexaBiz\Core\Exceptions\TooManyRequestsException;
use NexaBiz\Core\Support\SlidingWindowLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if ($request->isMethod('POST') && ($request->is('api/v1/auth/login') || $request->is('api/v1/auth/refresh'))) {
            $limit = (int) config('nexabiz.auth_rate_limit_per_minute');
            $limiter = new SlidingWindowLimiter($limit, 60.0);
            $host = $request->ip() ?: 'unknown';
            [$allowed, $retryAfter] = $limiter->allow($host.':/'.$path, $limit);
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
