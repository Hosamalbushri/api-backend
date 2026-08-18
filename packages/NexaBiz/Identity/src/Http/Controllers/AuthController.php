<?php

namespace NexaBiz\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Core\Http\Controllers\Controller;
use NexaBiz\Identity\Http\Requests\LoginRequest;
use NexaBiz\Identity\Http\Requests\RefreshTokenRequest;
use NexaBiz\Identity\Http\Requests\SwitchCompanyRequest;
use NexaBiz\Identity\Services\AuthService;
use NexaBiz\Identity\Support\AuthContext;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(fn () => $this->authService->login(
            email: $data['email'],
            password: $data['password'],
            companyId: $data['company_id'] ?? null,
            deviceIdentifier: $data['device_id'] ?? null,
            deviceName: $data['device_name'] ?? null,
            platform: $data['platform'] ?? null,
            appVersion: $data['app_version'] ?? null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json(['data' => $result]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(fn () => $this->authService->refresh(
            refreshToken: $data['refresh_token'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json(['data' => $result]);
    }

    public function logout(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        if ($auth->session !== null) {
            DB::transaction(fn () => $this->authService->logout(
                sessionId: $auth->session->id,
                userId: $auth->user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        $auth = $this->context($request);

        return response()->json([
            'data' => $this->authService->me($auth->user, $auth->companyId, $auth->deviceId),
        ]);
    }

    public function switchCompany(SwitchCompanyRequest $request): JsonResponse
    {
        $auth = $this->context($request);
        if ($auth->session === null) {
            throw new ValidationAppException('Company switch requires a JWT session');
        }
        $data = $request->validated();
        $result = DB::transaction(fn () => $this->authService->switchCompany(
            user: $auth->user,
            session: $auth->session,
            companyId: $data['company_id'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json(['data' => $result]);
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
