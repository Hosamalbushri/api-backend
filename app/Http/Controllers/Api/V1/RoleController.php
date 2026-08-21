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
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends Controller
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
            [PermissionsCatalog::ROLES_VIEW, PermissionsCatalog::ROLES_MANAGE],
            true,
        );
        $companyId = $auth->companyId;
        $rows = Role::query()
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Role $r) => [
                'id' => (string) $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'system_role' => (bool) $r->system_role,
                'company_id' => $r->company_id ? (string) $r->company_id : null,
                'permission_count' => RolePermission::query()->where('role_id', $r->id)->count(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::ROLES_CREATE, PermissionsCatalog::ROLES_MANAGE],
            true,
        );
        $data = $request->validate([
            'name' => 'required|string|min:1|max:120',
            'description' => 'nullable|string',
            'permission_codes' => 'nullable|array',
            'permission_codes.*' => 'string',
        ]);
        $companyId = $auth->requireCompanyId();
        $role = DB::transaction(function () use ($auth, $data, $companyId) {
            $role = Role::query()->create([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'system_role' => false,
            ]);
            $this->authService->setRolePermissions(
                $role->id,
                $this->safety->filterAssignablePermissionCodes($auth, $data['permission_codes'] ?? []),
            );
            $this->audit->write(
                action: 'role.created',
                userId: $auth->userId(),
                companyId: $companyId,
                entityType: 'role',
                entityId: (string) $role->id,
            );

            return $role;
        });

        return response()->json(['data' => ['id' => (string) $role->id, 'name' => $role->name]]);
    }

    public function show(Request $request, string $roleId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::ROLES_VIEW, PermissionsCatalog::ROLES_MANAGE],
            true,
        );
        $role = $this->safety->requireViewableRole($auth, $roleId);
        $permIds = RolePermission::query()->where('role_id', $role->id)->pluck('permission_id');
        $codes = Permission::query()->whereIn('id', $permIds)->pluck('code')->all();

        return response()->json([
            'data' => [
                'id' => (string) $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'system_role' => (bool) $role->system_role,
                'permissions' => $codes,
            ],
        ]);
    }

    public function update(Request $request, string $roleId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::ROLES_UPDATE, PermissionsCatalog::ROLES_MANAGE],
            true,
        );
        $data = $request->validate([
            'name' => 'nullable|string|min:1|max:120',
            'description' => 'nullable|string',
            'permission_codes' => 'nullable|array',
            'permission_codes.*' => 'string',
        ]);
        $role = DB::transaction(function () use ($auth, $roleId, $data) {
            $role = $this->safety->requireManageableRole($auth, $roleId);
            if (isset($data['name'])) {
                $role->name = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $role->description = $data['description'];
            }
            if (array_key_exists('permission_codes', $data)) {
                $filtered = $this->safety->filterAssignablePermissionCodes($auth, $data['permission_codes'] ?? []);
                $this->authService->setRolePermissions($role->id, $filtered);
                $this->audit->write(
                    action: 'role.permissions_changed',
                    userId: $auth->userId(),
                    companyId: $auth->companyId,
                    entityType: 'role',
                    entityId: (string) $role->id,
                    metadata: ['permissions' => $filtered],
                );
            }
            $role->save();

            return $role;
        });

        return response()->json(['data' => ['id' => (string) $role->id, 'name' => $role->name]]);
    }

    public function destroy(Request $request, string $roleId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::ROLES_DELETE, PermissionsCatalog::ROLES_MANAGE],
            true,
        );
        DB::transaction(function () use ($auth, $roleId): void {
            $role = $this->safety->requireManageableRole($auth, $roleId);
            if ($role->system_role) {
                throw new ValidationAppException('Cannot delete system roles');
            }
            $role->delete();
        });

        return response()->json(['data' => ['ok' => true]]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::ROLES_VIEW, PermissionsCatalog::PERMISSIONS_MANAGE],
            true,
        );
        $rows = Permission::query()->orderBy('code')->get();

        return response()->json([
            'data' => $rows->map(fn (Permission $p) => [
                'id' => (string) $p->id,
                'code' => $p->code,
                'description' => $p->description,
            ])->values(),
        ]);
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
