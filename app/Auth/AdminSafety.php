<?php

namespace App\Auth;

use App\Exceptions\NotFoundException;
use App\Exceptions\PermissionDeniedException;
use App\Exceptions\ValidationAppException;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;

class AdminSafety
{
    public function canManageAllTenants(AuthContext $auth): bool
    {
        return $auth->user->is_super_admin || $auth->hasPermission(PermissionsCatalog::PLATFORM_USERS_MANAGE);
    }

    public function requireCompanyScope(AuthContext $auth, string $companyId): void
    {
        if ($this->canManageAllTenants($auth)) {
            return;
        }
        if ($auth->companyId !== $companyId) {
            throw new PermissionDeniedException('Cannot manage another company', ['company_id' => $companyId]);
        }
    }

    /**
     * @return list<string>
     */
    public function rolePermissionCodes(string $roleId): array
    {
        $permIds = RolePermission::query()->where('role_id', $roleId)->pluck('permission_id');

        return Permission::query()->whereIn('id', $permIds)->pluck('code')->all();
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function filterAssignablePermissionCodes(AuthContext $auth, array $codes): array
    {
        if ($this->canManageAllTenants($auth)) {
            return $codes;
        }

        return array_values(array_filter($codes, fn ($code) => ! str_starts_with($code, 'platform.')));
    }

    public function requireViewableRole(AuthContext $auth, string $roleId): Role
    {
        $role = Role::query()->find($roleId);
        if ($role === null) {
            throw new NotFoundException('Role not found');
        }
        if ($this->canManageAllTenants($auth)) {
            return $role;
        }
        $companyId = $auth->requireCompanyId();
        if ($role->company_id === null || $role->company_id === $companyId) {
            return $role;
        }
        throw new NotFoundException('Role not found');
    }

    public function requireManageableRole(AuthContext $auth, string $roleId): Role
    {
        $role = $this->requireViewableRole($auth, $roleId);
        if ($this->canManageAllTenants($auth)) {
            return $role;
        }
        $companyId = $auth->requireCompanyId();
        if ($role->company_id !== $companyId) {
            throw new PermissionDeniedException('Cannot modify platform system roles');
        }
        if ($role->system_role) {
            throw new PermissionDeniedException('Cannot modify system roles');
        }

        return $role;
    }

    public function requireAssignableRole(AuthContext $auth, ?string $roleId, string $companyId): ?Role
    {
        if ($roleId === null) {
            return null;
        }
        $role = Role::query()->find($roleId);
        if ($role === null) {
            throw new NotFoundException('Role not found');
        }
        $this->requireCompanyScope($auth, $companyId);
        if ($this->canManageAllTenants($auth)) {
            return $role;
        }
        if ($role->name === 'Super Admin') {
            throw new PermissionDeniedException('Cannot assign Super Admin role');
        }
        $codes = $this->rolePermissionCodes($role->id);
        foreach ($codes as $code) {
            if (str_starts_with($code, 'platform.')) {
                throw new PermissionDeniedException('Cannot assign platform-scoped roles');
            }
        }
        if ($role->company_id !== null && $role->company_id !== $companyId) {
            throw new NotFoundException('Role not found');
        }

        return $role;
    }

    public function requireManageableUser(AuthContext $auth, string $userId): User
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            throw new NotFoundException('User not found');
        }
        if ($this->canManageAllTenants($auth)) {
            return $user;
        }
        $companyId = $auth->requireCompanyId();
        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();
        if ($membership === null) {
            throw new NotFoundException('User not found');
        }

        return $user;
    }

    public function countActiveSuperAdmins(?string $excludingUserId = null): int
    {
        $q = User::query()->where('is_super_admin', true)->where('status', 'active');
        if ($excludingUserId !== null) {
            $q->where('id', '!=', $excludingUserId);
        }

        return $q->count();
    }

    public function countActiveCompanyAdmins(string $companyId, ?string $excludingUserId = null): int
    {
        $q = CompanyUser::query()
            ->join('roles', 'roles.id', '=', 'company_users.role_id')
            ->join('users', 'users.id', '=', 'company_users.user_id')
            ->where('company_users.company_id', $companyId)
            ->where('company_users.status', 'active')
            ->where('users.status', 'active')
            ->whereRaw('LOWER(roles.name) LIKE ?', ['%admin%']);
        if ($excludingUserId !== null) {
            $q->where('users.id', '!=', $excludingUserId);
        }

        return $q->count();
    }

    public function ensureNotLastAdmin(User $user, ?string $companyId, ?string $nextStatus = null): void
    {
        if ($nextStatus === null || $nextStatus === 'active') {
            return;
        }
        if ($user->is_super_admin && $user->status === 'active') {
            if ($this->countActiveSuperAdmins($user->id) < 1) {
                throw new ValidationAppException(
                    'Cannot deactivate or suspend the last active super administrator'
                );
            }
        }
        if ($companyId !== null && $user->status === 'active') {
            $membership = CompanyUser::query()
                ->join('roles', 'roles.id', '=', 'company_users.role_id')
                ->where('company_users.company_id', $companyId)
                ->where('company_users.user_id', $user->id)
                ->where('company_users.status', 'active')
                ->whereRaw('LOWER(roles.name) LIKE ?', ['%admin%'])
                ->first();
            if ($membership !== null) {
                $remaining = $this->countActiveCompanyAdmins($companyId, $user->id);
                if ($remaining < 1 && $this->countActiveSuperAdmins($user->id) < 1) {
                    throw new ValidationAppException(
                        'Cannot remove the last administrator for this company'
                    );
                }
            }
        }
    }
}
