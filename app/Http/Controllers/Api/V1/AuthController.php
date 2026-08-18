<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\AuthContext;
use App\Auth\AuthService;
use App\Exceptions\ValidationAppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|min:3|max:320',
            'password' => 'required|string|min:1',
            'company_id' => 'nullable|uuid',
            'device_id' => 'nullable|uuid',
            'device_name' => 'nullable|string',
            'platform' => 'nullable|string',
            'app_version' => 'nullable|string',
        ]);

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

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string|min:10',
        ]);
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

    public function switchCompany(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        if ($auth->session === null) {
            throw new ValidationAppException('Company switch requires a JWT session');
        }
        $data = $request->validate([
            'company_id' => 'required|uuid',
        ]);
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
