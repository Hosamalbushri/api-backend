<?php

namespace NexaBiz\Identity\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use NexaBiz\Identity\Events\CompanyProvisioned;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Identity\Models\CompanyUser;
use NexaBiz\Identity\Models\Permission;
use NexaBiz\Identity\Models\Role;
use NexaBiz\Identity\Models\RolePermission;
use NexaBiz\Identity\Models\User;
use NexaBiz\Identity\Support\PermissionsCatalog;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');
        $codeToPerm = [];
        foreach (PermissionsCatalog::allPermissions() as [$code, $description]) {
            $existing = Permission::query()->where('code', $code)->first();
            if ($existing === null) {
                $existing = Permission::query()->create([
                    'id' => (string) Str::uuid(),
                    'code' => $code,
                    'description' => $description,
                    'created_at' => $now,
                ]);
            }
            $codeToPerm[$code] = $existing;
        }

        $roleByName = [];
        foreach (PermissionsCatalog::systemRolePermissions() as $roleName => $permCodes) {
            $role = Role::query()->where('name', $roleName)->whereNull('company_id')->first();
            if ($role === null) {
                $role = Role::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => null,
                    'name' => $roleName,
                    'description' => 'System role: '.$roleName,
                    'system_role' => true,
                ]);
            }
            $wantedIds = [];
            foreach ($permCodes as $code) {
                if (isset($codeToPerm[$code])) {
                    $wantedIds[$codeToPerm[$code]->id] = true;
                }
            }
            $existingLinks = RolePermission::query()->where('role_id', $role->id)->get();
            $existingIds = [];
            foreach ($existingLinks as $link) {
                $existingIds[$link->permission_id] = true;
                if (! isset($wantedIds[$link->permission_id])) {
                    $link->delete();
                }
            }
            foreach (array_keys($wantedIds) as $permissionId) {
                if (! isset($existingIds[$permissionId])) {
                    RolePermission::query()->create([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
            $roleByName[$roleName] = $role;
        }

        $companyId = (string) config('nexabiz.seed_company_id');
        $company = Company::query()->find($companyId);
        if ($company === null) {
            $company = Company::query()->create([
                'id' => $companyId,
                'name' => config('nexabiz.seed_company_name'),
                'code' => config('nexabiz.seed_company_code'),
                'status' => 'active',
            ]);
        } else {
            $company->name = config('nexabiz.seed_company_name');
            $company->code = config('nexabiz.seed_company_code');
            $company->status = 'active';
            $company->save();
        }
        Event::dispatch(new CompanyProvisioned($companyId));

        $companyRoles = [];
        foreach ($roleByName as $roleName => $template) {
            if ($roleName === 'Super Admin') {
                continue;
            }
            $existing = Role::query()->where('company_id', $companyId)->where('name', $roleName)->first();
            if ($existing === null) {
                $existing = Role::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'name' => $roleName,
                    'description' => $template->description,
                    'system_role' => true,
                ]);
            }
            $templatePermIds = RolePermission::query()->where('role_id', $template->id)->pluck('permission_id')->all();
            $wanted = array_fill_keys($templatePermIds, true);
            $existingLinks = RolePermission::query()->where('role_id', $existing->id)->get();
            $existingIds = [];
            foreach ($existingLinks as $link) {
                $existingIds[$link->permission_id] = true;
                if (! isset($wanted[$link->permission_id])) {
                    $link->delete();
                }
            }
            foreach ($templatePermIds as $permissionId) {
                if (! isset($existingIds[$permissionId])) {
                    RolePermission::query()->create([
                        'role_id' => $existing->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
            $companyRoles[$roleName] = $existing;
        }

        $adminEmail = strtolower((string) config('nexabiz.seed_admin_email'));
        $admin = User::query()->where('email', $adminEmail)->first();
        if ($admin === null) {
            $admin = User::query()->find(config('nexabiz.default_user_id'));
        }
        if ($admin === null) {
            $admin = User::query()->create([
                'id' => (string) config('nexabiz.default_user_id'),
                'name' => config('nexabiz.seed_admin_name'),
                'email' => $adminEmail,
                'password_hash' => Hash::make((string) config('nexabiz.seed_admin_password')),
                'status' => 'active',
                'is_super_admin' => true,
            ]);
        } else {
            $admin->email = $adminEmail;
            $admin->name = config('nexabiz.seed_admin_name');
            $admin->is_super_admin = true;
            $admin->status = 'active';
            $admin->password_hash = Hash::make((string) config('nexabiz.seed_admin_password'));
            $admin->save();
        }

        $ahmed = User::query()->where('email', 'ahmed@example.com')->first();
        if ($ahmed === null) {
            $ahmed = User::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'Ahmed',
                'email' => 'ahmed@example.com',
                'password_hash' => Hash::make('AhmedSales!123'),
                'status' => 'active',
                'is_super_admin' => false,
            ]);
        }

        $salesRole = $companyRoles['Sales Employee'] ?? null;
        if ($salesRole !== null) {
            $membership = CompanyUser::query()
                ->where('company_id', $companyId)
                ->where('user_id', $ahmed->id)
                ->first();
            if ($membership === null) {
                CompanyUser::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'user_id' => $ahmed->id,
                    'role_id' => $salesRole->id,
                    'status' => 'active',
                ]);
            } else {
                $membership->role_id = $salesRole->id;
                $membership->status = 'active';
                $membership->save();
            }
        }

        $adminRole = $companyRoles['Company Admin'] ?? null;
        if ($adminRole !== null) {
            $membership = CompanyUser::query()
                ->where('company_id', $companyId)
                ->where('user_id', $admin->id)
                ->first();
            if ($membership === null) {
                CompanyUser::query()->create([
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'user_id' => $admin->id,
                    'role_id' => $adminRole->id,
                    'status' => 'active',
                ]);
            }
        }
    }
}
