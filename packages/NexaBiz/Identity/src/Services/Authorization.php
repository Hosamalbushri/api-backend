<?php

namespace NexaBiz\Identity\Services;

use NexaBiz\Core\Exceptions\PermissionDeniedException;
use NexaBiz\Identity\Models\CompanyUser;
use NexaBiz\Identity\Models\Permission;
use NexaBiz\Identity\Models\RolePermission;
use NexaBiz\Identity\Models\User;
use NexaBiz\Identity\Support\PermissionsCatalog;

class Authorization
{
    /**
     * @return list<string>
     */
    public function loadPermissionCodes(User $user, ?string $companyId): array
    {
        if ($user->is_super_admin) {
            $rows = Permission::query()->pluck('code')->all();

            return PermissionsCatalog::expandPermissionCodes($rows);
        }
        if ($companyId === null) {
            return [];
        }
        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if ($membership === null || $membership->role_id === null) {
            return [];
        }
        $permIds = RolePermission::query()
            ->where('role_id', $membership->role_id)
            ->pluck('permission_id');
        $rows = Permission::query()->whereIn('id', $permIds)->pluck('code')->all();

        return PermissionsCatalog::expandPermissionCodes($rows);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function requirePermissions(array $permissions, array $required, bool $anyOf = false): void
    {
        if ($required === []) {
            return;
        }
        $set = array_fill_keys($permissions, true);
        if ($anyOf) {
            foreach ($required as $code) {
                if (isset($set[$code])) {
                    return;
                }
            }
            throw new PermissionDeniedException('Permission denied', ['required_any_of' => $required]);
        }
        $missing = [];
        foreach ($required as $code) {
            if (! isset($set[$code])) {
                $missing[] = $code;
            }
        }
        if ($missing !== []) {
            throw new PermissionDeniedException(
                'Missing permission(s): '.implode(', ', $missing),
                ['missing' => $missing, 'required' => $required],
            );
        }
    }

    public function syncPermissionFor(string $entityType, string $operation): ?string
    {
        return PermissionsCatalog::syncEntityPermissions()[$entityType.'|'.$operation] ?? null;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function requireSyncOperationPermission(array $permissions, string $entityType, string $operation): void
    {
        $this->requirePermissions($permissions, [PermissionsCatalog::SYNC_EXECUTE]);
        $entityPerm = $this->syncPermissionFor($entityType, $operation);
        if ($entityPerm !== null) {
            $this->requirePermissions($permissions, [$entityPerm]);
        }
    }
}
