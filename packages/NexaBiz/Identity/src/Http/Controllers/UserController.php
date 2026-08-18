<?php

namespace NexaBiz\Identity\Http\Controllers;

use NexaBiz\Audit\Contracts\AuditWriter;
use NexaBiz\Identity\Services\AdminSafety;
use NexaBiz\Identity\Support\AuthContext;
use NexaBiz\Identity\Services\AuthService;
use NexaBiz\Identity\Services\Authorization;
use NexaBiz\Identity\Support\PermissionsCatalog;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Core\Http\Controllers\Controller;
use NexaBiz\Identity\Http\Requests\StoreUserRequest;
use NexaBiz\Identity\Http\Resources\UserResource;
use NexaBiz\Identity\Models\CompanyUser;
use NexaBiz\Identity\Models\User;
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
        private readonly AuditWriter $audit,
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

        return response()->json(['data' => UserResource::collection($rows)->resolve()]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_CREATE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $data = $request->validated();
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

        return response()->json(['data' => (new UserResource($user))->resolve()]);
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

        return response()->json(['data' => (new UserResource($user))->resolve()]);
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

        return response()->json(['data' => (new UserResource($user))->resolve()]);
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

        return response()->json(['data' => (new UserResource($user))->resolve()]);
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

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
