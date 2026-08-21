<?php

namespace App\Http\Middleware;

use App\Auth\AuthContext;
use App\Auth\Authorization;
use App\Auth\JwtTokenService;
use App\Exceptions\UnauthorizedException;
use App\Models\AuthSession;
use App\Models\Company;
use App\Models\Device;
use App\Models\SyncDisableRequest;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function __construct(
        private readonly JwtTokenService $jwt,
        private readonly Authorization $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (! $authHeader || ! str_starts_with(strtolower($authHeader), 'bearer ')) {
            throw new UnauthorizedException('Missing or invalid Authorization header');
        }
        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            throw new UnauthorizedException('Missing or invalid Authorization header');
        }

        if (config('nexabiz.allow_dev_token') && hash_equals((string) config('nexabiz.dev_api_token'), $token)) {
            $user = User::query()->find(config('nexabiz.default_user_id'));
            $company = Company::query()->find(config('nexabiz.default_company_id'));
            if ($user === null || $company === null) {
                throw new UnauthorizedException(
                    'Dev token configured but seed identity is missing. Run migrations/seed.'
                );
            }
            $deviceUuid = null;
            $xDevice = $request->header('X-Device-Id');
            if ($xDevice) {
                if (! $this->isUuid($xDevice)) {
                    throw new UnauthorizedException('Invalid X-Device-Id');
                }
                $deviceUuid = trim($xDevice);
            }
            $permissions = $this->authorization->loadPermissionCodes($user, $company->id);
            $request->attributes->set('auth_context', new AuthContext(
                user: $user,
                session: null,
                companyId: $company->id,
                deviceId: $deviceUuid,
                permissions: $permissions,
                isDevToken: true,
            ));

            return $next($request);
        }

        try {
            $payload = $this->jwt->decodeAccessToken($token);
        } catch (\Throwable) {
            throw new UnauthorizedException('Invalid or expired access token');
        }
        if (($payload['typ'] ?? null) !== 'access') {
            throw new UnauthorizedException('Invalid token type');
        }
        $userId = $payload['sub'] ?? null;
        $sessionId = $payload['sid'] ?? null;
        if (! is_string($userId) || ! is_string($sessionId) || ! $this->isUuid($userId) || ! $this->isUuid($sessionId)) {
            throw new UnauthorizedException('Invalid token claims');
        }
        $user = User::query()->find($userId);
        if ($user === null) {
            throw new UnauthorizedException('User not found');
        }
        if ($user->status !== 'active') {
            throw new UnauthorizedException('User cannot authenticate');
        }
        $session = AuthSession::query()->find($sessionId);
        if ($session === null || $session->user_id !== $user->id) {
            throw new UnauthorizedException('Session not found');
        }
        if ($session->status !== 'active') {
            throw new UnauthorizedException('Session revoked');
        }
        $companyId = $session->company_id;
        $deviceId = $session->device_id;
        if ($deviceId !== null) {
            $device = Device::query()->find($deviceId);
            if ($device === null || $device->status !== 'active') {
                if ($device !== null) {
                    $approved = SyncDisableRequest::query()
                        ->where('device_id', $device->id)
                        ->where('status', 'approved')
                        ->first();
                    if ($approved !== null) {
                        throw new UnauthorizedException(
                            'Synchronization disabled by administrator',
                            ['reason' => 'sync_disable_approved'],
                        );
                    }
                }
                throw new UnauthorizedException('Device is revoked or blocked');
            }
        }
        if ($companyId !== null) {
            $company = Company::query()->find($companyId);
            if ($company === null || $company->status !== 'active') {
                throw new UnauthorizedException('Company is not available');
            }
        }
        $permissions = $this->authorization->loadPermissionCodes($user, $companyId);
        $request->attributes->set('auth_context', new AuthContext(
            user: $user,
            session: $session,
            companyId: $companyId,
            deviceId: $deviceId,
            permissions: $permissions,
        ));

        return $next($request);
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/',
            trim($value),
        );
    }
}
