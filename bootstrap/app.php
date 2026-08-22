<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use NexaBiz\Core\Exceptions\AppException;
use NexaBiz\Core\Http\Middleware\AuthRateLimit;
use NexaBiz\Core\Http\Middleware\CorrelationId;
use NexaBiz\Core\Http\Middleware\SecurityHeaders;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(CorrelationId::class);
        $middleware->append(AuthRateLimit::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('health')
                || $request->is('/')
                || $request->expectsJson(),
        );

        $exceptions->render(function (AppException $e, Request $request) {
            $headers = [];
            if ($e->retryAfter !== null) {
                $headers['Retry-After'] = (string) $e->retryAfter;
            }

            return response()->json($e->toArray(), $e->statusCode, $headers);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            $message = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return response()->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => $message,
                    'details' => $e->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $code = match ($e->getStatusCode()) {
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not_found',
                422 => 'validation_error',
                429 => 'rate_limited',
                default => 'server_error',
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed',
                    'details' => [],
                ],
            ], $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'server_error',
                    'message' => 'Internal server error',
                    'details' => [],
                ],
            ], 500);
        });
    })->create();
