<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\AuthContext;
use App\Auth\AuthService;
use App\Auth\Authorization;
use App\Auth\PermissionsCatalog;
use App\Exceptions\ValidationAppException;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly Authorization $authorization,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions($auth->permissions, [PermissionsCatalog::DEVICES_VIEW]);
        $companyId = $auth->requireCompanyId();
        $rows = Device::query()
            ->where('company_id', $companyId)
            ->orderByRaw('last_seen_at IS NULL')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(function (Device $d) {
                $user = User::query()->find($d->user_id);

                return [
                    'id' => (string) $d->id,
                    'device_identifier' => (string) $d->device_identifier,
                    'device_name' => $d->device_name,
                    'platform' => $d->platform,
                    'app_version' => $d->app_version,
                    'status' => $d->status,
                    'user_id' => (string) $d->user_id,
                    'user_name' => $user?->name,
                    'user_email' => $user?->email,
                    'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                    'created_at' => $d->created_at?->toIso8601String(),
                    'revoked_at' => $d->revoked_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $auth->requireCompanyId();
        $data = $request->validate([
            'device_id' => 'required|uuid',
            'device_name' => 'required|string|min:1|max:200',
            'platform' => 'nullable|string|max:64',
            'app_version' => 'nullable|string|max:64',
        ]);
        $device = DB::transaction(fn () => $this->authService->registerDevice(
            user: $auth->user,
            companyId: $auth->requireCompanyId(),
            deviceIdentifier: $data['device_id'],
            deviceName: $data['device_name'],
            platform: $data['platform'] ?? 'unknown',
            appVersion: $data['app_version'] ?? null,
        ));

        return response()->json([
            'data' => [
                'id' => (string) $device->id,
                'device_identifier' => (string) $device->device_identifier,
                'status' => $device->status,
            ],
        ]);
    }

    public function revoke(Request $request, string $deviceId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions($auth->permissions, [PermissionsCatalog::DEVICES_REVOKE]);
        $device = DB::transaction(fn () => $this->authService->revokeDevice(
            actor: $auth->user,
            companyId: $auth->requireCompanyId(),
            deviceId: $deviceId,
        ));

        return response()->json(['data' => ['id' => (string) $device->id, 'status' => $device->status]]);
    }

    public function createSyncDisableRequest(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $auth->requireCompanyId();
        if ($auth->deviceId === null) {
            throw new ValidationAppException('Current session has no registered device');
        }
        $row = DB::transaction(fn () => $this->authService->requestSyncDisable(
            user: $auth->user,
            companyId: $auth->requireCompanyId(),
            deviceId: $auth->deviceId,
        ));

        return response()->json([
            'data' => [
                'id' => (string) $row->id,
                'status' => $row->status,
                'device_id' => (string) $row->device_id,
                'created_at' => $row->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function listSyncDisableRequests(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::DEVICES_VIEW, PermissionsCatalog::DEVICES_REVOKE],
            true,
        );
        $status = $request->query('status', 'pending');
        $rows = $this->authService->listSyncDisableRequests(
            companyId: $auth->requireCompanyId(),
            status: is_string($status) ? $status : 'pending',
        );
        $out = [];
        foreach ($rows as $row) {
            $user = User::query()->find($row->user_id);
            $device = Device::query()->find($row->device_id);
            $out[] = [
                'id' => (string) $row->id,
                'status' => $row->status,
                'message' => $row->message,
                'user_id' => (string) $row->user_id,
                'user_name' => $user?->name,
                'user_email' => $user?->email,
                'device_id' => (string) $row->device_id,
                'device_name' => $device?->device_name,
                'platform' => $device?->platform,
                'created_at' => $row->created_at?->toIso8601String(),
                'resolved_at' => $row->resolved_at?->toIso8601String(),
            ];
        }

        return response()->json(['data' => $out]);
    }

    public function approveSyncDisable(Request $request, string $requestId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions($auth->permissions, [PermissionsCatalog::DEVICES_REVOKE]);
        $row = DB::transaction(fn () => $this->authService->approveSyncDisable(
            actor: $auth->user,
            companyId: $auth->requireCompanyId(),
            requestId: $requestId,
        ));

        return response()->json(['data' => ['id' => (string) $row->id, 'status' => $row->status]]);
    }

    public function rejectSyncDisable(Request $request, string $requestId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions($auth->permissions, [PermissionsCatalog::DEVICES_REVOKE]);
        $row = DB::transaction(fn () => $this->authService->rejectSyncDisable(
            actor: $auth->user,
            companyId: $auth->requireCompanyId(),
            requestId: $requestId,
        ));

        return response()->json(['data' => ['id' => (string) $row->id, 'status' => $row->status]]);
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
