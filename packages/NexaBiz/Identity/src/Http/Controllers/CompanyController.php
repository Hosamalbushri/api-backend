<?php

namespace NexaBiz\Identity\Http\Controllers;

use NexaBiz\Audit\Contracts\AuditWriter;
use NexaBiz\Identity\Services\AdminSafety;
use NexaBiz\Identity\Support\AuthContext;
use NexaBiz\Identity\Services\Authorization;
use NexaBiz\Identity\Support\PermissionsCatalog;
use NexaBiz\Core\Exceptions\NotFoundException;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Core\Http\Controllers\Controller;
use NexaBiz\Identity\Events\CompanyProvisioned;
use NexaBiz\Identity\Http\Resources\CompanyResource;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Identity\Models\CompanyUser;
use NexaBiz\Identity\Models\Role;
use NexaBiz\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function __construct(
        private readonly AdminSafety $safety,
        private readonly Authorization $authorization,
        private readonly AuditWriter $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        if ($auth->user->is_super_admin || $auth->hasPermission(PermissionsCatalog::PLATFORM_COMPANIES_MANAGE)) {
            $rows = Company::query()->orderBy('name')->get();
        } else {
            $ids = CompanyUser::query()
                ->where('user_id', $auth->user->id)
                ->where('status', 'active')
                ->pluck('company_id');
            $rows = $ids->isEmpty()
                ? collect()
                : Company::query()->whereIn('id', $ids)->orderBy('name')->get();
        }

        return response()->json([
            'data' => CompanyResource::collection($rows)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions($auth->permissions, [PermissionsCatalog::PLATFORM_COMPANIES_MANAGE]);
        $data = $request->validate([
            'name' => 'required|string|min:1|max:200',
            'code' => 'required|string|min:1|max:64',
            'status' => 'nullable|string',
        ]);
        $code = strtoupper(trim($data['code']));
        if (Company::query()->where('code', $code)->exists()) {
            throw new ValidationAppException('Company code already exists');
        }
        $company = DB::transaction(function () use ($auth, $data, $code) {
            $company = Company::query()->create([
                'id' => (string) Str::uuid(),
                'name' => trim($data['name']),
                'code' => $code,
                'status' => $data['status'] ?? 'active',
            ]);
            Event::dispatch(new CompanyProvisioned((string) $company->id));
            $this->audit->write(
                action: 'company.created',
                userId: $auth->userId(),
                companyId: $company->id,
                entityType: 'company',
                entityId: (string) $company->id,
            );

            return $company;
        });

        return response()->json([
            'data' => [
                'id' => (string) $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'status' => $company->status,
            ],
        ]);
    }

    public function update(Request $request, string $companyId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::PLATFORM_COMPANIES_MANAGE, PermissionsCatalog::COMPANIES_UPDATE],
            true,
        );
        $data = $request->validate([
            'name' => 'nullable|string|min:1|max:200',
            'status' => 'nullable|string',
        ]);
        $company = Company::query()->find($companyId);
        if ($company === null) {
            throw new NotFoundException('Company not found');
        }
        if (
            ! $auth->user->is_super_admin
            && ! $auth->hasPermission(PermissionsCatalog::PLATFORM_COMPANIES_MANAGE)
            && $auth->companyId !== $companyId
        ) {
            throw new ValidationAppException('Cannot update another company');
        }
        if (isset($data['name'])) {
            $company->name = $data['name'];
        }
        if (isset($data['status'])) {
            $company->status = $data['status'];
        }
        $company->save();

        return response()->json([
            'data' => [
                'id' => (string) $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'status' => $company->status,
            ],
        ]);
    }

    public function members(Request $request, string $companyId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_VIEW, PermissionsCatalog::USERS_MANAGE],
            true,
        );
        $this->safety->requireCompanyScope($auth, $companyId);
        $rows = CompanyUser::query()->where('company_id', $companyId)->get();
        $data = $rows->map(function (CompanyUser $m) {
            $user = User::query()->find($m->user_id);
            $role = $m->role_id ? Role::query()->find($m->role_id) : null;

            return [
                'id' => (string) $m->id,
                'user_id' => (string) $m->user_id,
                'user_email' => $user?->email,
                'user_name' => $user?->name,
                'role_id' => $m->role_id ? (string) $m->role_id : null,
                'role_name' => $role?->name,
                'status' => $m->status,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    public function addMember(Request $request, string $companyId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_CREATE, PermissionsCatalog::USERS_MANAGE, PermissionsCatalog::PLATFORM_USERS_MANAGE],
            true,
        );
        $this->safety->requireCompanyScope($auth, $companyId);
        $data = $request->validate([
            'user_id' => 'required|uuid',
            'role_id' => 'nullable|uuid',
            'status' => 'nullable|string',
        ]);
        $company = Company::query()->find($companyId);
        if ($company === null) {
            throw new NotFoundException('Company not found');
        }
        if (CompanyUser::query()->where('company_id', $companyId)->where('user_id', $data['user_id'])->exists()) {
            throw new ValidationAppException('User already a member');
        }
        $this->safety->requireAssignableRole($auth, $data['role_id'] ?? null, $companyId);
        $membership = DB::transaction(function () use ($auth, $companyId, $data) {
            $membership = CompanyUser::query()->create([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'user_id' => $data['user_id'],
                'role_id' => $data['role_id'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
            $this->audit->write(
                action: 'company.member_added',
                userId: $auth->userId(),
                companyId: $companyId,
                entityType: 'company_user',
                entityId: (string) $membership->id,
                metadata: ['user_id' => $data['user_id'], 'role_id' => $data['role_id'] ?? null],
            );

            return $membership;
        });

        return response()->json(['data' => ['id' => (string) $membership->id]]);
    }

    public function updateMember(Request $request, string $companyId, string $membershipId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_UPDATE, PermissionsCatalog::USERS_MANAGE],
            true,
        );
        $this->safety->requireCompanyScope($auth, $companyId);
        $data = $request->validate([
            'role_id' => 'nullable|uuid',
            'status' => 'nullable|string',
        ]);
        $membership = CompanyUser::query()->find($membershipId);
        if ($membership === null || $membership->company_id !== $companyId) {
            throw new NotFoundException('Membership not found');
        }
        DB::transaction(function () use ($auth, $companyId, $membership, $data): void {
            if (isset($data['role_id'])) {
                $this->safety->requireAssignableRole($auth, $data['role_id'], $companyId);
                $membership->role_id = $data['role_id'];
                $this->audit->write(
                    action: 'role.assignment_changed',
                    userId: $auth->userId(),
                    companyId: $companyId,
                    entityType: 'company_user',
                    entityId: (string) $membership->id,
                    metadata: ['role_id' => $data['role_id']],
                );
            }
            if (isset($data['status'])) {
                $memberUser = User::query()->find($membership->user_id);
                if ($memberUser !== null) {
                    $this->safety->ensureNotLastAdmin($memberUser, $companyId, $data['status']);
                }
                $membership->status = $data['status'];
            }
            $membership->save();
        });

        return response()->json(['data' => ['id' => (string) $membership->id, 'status' => $membership->status]]);
    }

    public function removeMember(Request $request, string $companyId, string $membershipId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::USERS_DELETE, PermissionsCatalog::USERS_MANAGE],
            true,
        );
        $this->safety->requireCompanyScope($auth, $companyId);
        $membership = CompanyUser::query()->find($membershipId);
        if ($membership === null || $membership->company_id !== $companyId) {
            throw new NotFoundException('Membership not found');
        }
        DB::transaction(function () use ($companyId, $membership): void {
            $memberUser = User::query()->find($membership->user_id);
            if ($memberUser !== null) {
                $this->safety->ensureNotLastAdmin($memberUser, $companyId, 'inactive');
            }
            $membership->status = 'inactive';
            $membership->save();
        });

        return response()->json(['data' => ['ok' => true]]);
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
