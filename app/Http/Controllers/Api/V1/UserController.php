<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditService;
use App\Auth\AdminSafety;
use App\Auth\AuthContext;
use App\Auth\AuthService;
use App\Auth\Authorization;
use App\Auth\PermissionsCatalog;
use App\Exceptions\ValidationAppException;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AdminSafety $safety,
        private readonly Authorization $authorization,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_VIEW, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        if ($auth->user->is_super_admin || $auth->hasPermission(PermissionsCatalog::PLATFORM_USERS_MANAGE)) {
            $rows = User::query()->orderByDesc('created_at')->get();
        } else {
            $companyId = $auth->requireCompanyId();
            $userIds = CompanyUser::query()->where('company_id', $companyId)->pluck('user_id');
            $rows = $userIds->isEmpty()
                ? collect()
                : User::query()->whereIn('id', $userIds)->orderBy('name')->get();
        }

        return response()->json(['data' => $rows->map(fn (User $u) => $this->userOut($u))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_CREATE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $data = $request->validate([
            'name' => 'required|string|min:1|max:200',
            'email' => 'required|string|min:3|max:320',
            'password' => 'required|string|min:8|max:200',
            'phone' => 'nullable|string',
            'status' => 'nullable|string',
            'company_id' => 'nullable|uuid',
            'role_id' => 'nullable|uuid',
            'is_super_admin' => 'nullable|boolean',
        ]);
        if (($data['is_super_admin'] ?? false) && ! $auth->user->is_super_admin) {
            throw new ValidationAppException('Only super admins can create super admins');
        }
        $companyId = $data['company_id'] ?? $auth->companyId;
        if ($companyId !== null) {
            $this->safety->requireCompanyScope($auth, $companyId);
        }
        $user = DB::transaction(function () use ($auth, $data, $companyId) {
            $user = $this->authService->createUser(
                name: $data['name'],
                email: $data['email'],
                password: $data['password'],
                phone: $data['phone'] ?? null,
                status: $data['status'] ?? 'active',
                isSuperAdmin: ($data['is_super_admin'] ?? false) && $auth->user->is_super_admin,
            );
            if ($companyId !== null && isset($data['role_id'])) {
                $this->safety->requireAssignableRole($auth, $data['role_id'], $companyId);
                CompanyUser::query()->create([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'role_id' => $data['role_id'],
                    'status' => 'active',
                ]);
            }
            $this->audit->write(
                action: 'user.created',
                userId: $auth->userId(),
                companyId: $companyId,
                entityType: 'user',
                entityId: (string) $user->id,
            );

            return $user;
        });

        return response()->json(['data' => $this->userOut($user)]);
    }

    public function show(Request $request, string $userId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_VIEW, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $user = $this->safety->requireManageableUser($auth, $userId);

        return response()->json(['data' => $this->userOut($user)]);
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_UPDATE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $data = $request->validate([
            'name' => 'nullable|string|min:1|max:200',
            'phone' => 'nullable|string',
            'status' => 'nullable|string',
            'password' => 'nullable|string|min:8|max:200',
        ]);
        $user = DB::transaction(function () use ($auth, $userId, $data) {
            $user = $this->safety->requireManageableUser($auth, $userId);
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            if (array_key_exists('phone', $data)) {
                $user->phone = $data['phone'];
            }
            if (isset($data['status'])) {
                if (! in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
                    throw new ValidationAppException('status must be active, inactive, or suspended');
                }
                $this->safety->ensureNotLastAdmin($user, $auth->companyId, $data['status']);
                $user->status = $data['status'];
                $this->audit->write(
                    action: 'user.status_changed',
                    userId: $auth->userId(),
                    companyId: $auth->companyId,
                    entityType: 'user',
                    entityId: (string) $user->id,
                    metadata: ['status' => $data['status']],
                );
                if ($data['status'] !== 'active') {
                    $this->authService->revokeUserSessions($user->id);
                }
            }
            if (isset($data['password'])) {
                $user->password_hash = Hash::make($data['password']);
                $this->audit->write(
                    action: 'user.password_changed',
                    userId: $auth->userId(),
                    companyId: $auth->companyId,
                    entityType: 'user',
                    entityId: (string) $user->id,
                );
            }
            $user->save();

            return $user;
        });

        return response()->json(['data' => $this->userOut($user)]);
    }

    public function setStatus(Request $request, string $userId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_UPDATE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $data = $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);
        $user = DB::transaction(function () use ($auth, $userId, $data) {
            $user = $this->safety->requireManageableUser($auth, $userId);
            $this->safety->ensureNotLastAdmin($user, $auth->companyId, $data['status']);
            $user->status = $data['status'];
            $user->save();
            $this->audit->write(
                action: 'user.status_changed',
                userId: $auth->userId(),
                companyId: $auth->companyId,
                entityType: 'user',
                entityId: (string) $user->id,
                metadata: ['status' => $data['status']],
            );
            if ($data['status'] !== 'active') {
                $this->authService->revokeUserSessions($user->id);
            }

            return $user;
        });

        return response()->json(['data' => $this->userOut($user)]);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_DELETE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        DB::transaction(function () use ($auth, $userId): void {
            $user = $this->safety->requireManageableUser($auth, $userId);
            $this->safety->ensureNotLastAdmin($user, $auth->companyId, 'inactive');
            $user->status = 'inactive';
            $user->save();
            $this->authService->revokeUserSessions($user->id);
            $this->audit->write(
                action: 'user.deactivated',
                userId: $auth->userId(),
                companyId: $auth->companyId,
                entityType: 'user',
                entityId: (string) $user->id,
            );
        });

        return response()->json(['data' => ['ok' => true]]);
    }

    private function userOut(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'is_super_admin' => (bool) $user->is_super_admin,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
