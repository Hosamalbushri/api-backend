<?php

namespace App\Auth;

use App\Audit\AuditService;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationAppException;
use App\Models\AuthSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Device;
use App\Models\Role;
use App\Models\SyncDisableRequest;
use App\Models\SyncSequence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly JwtTokenService $jwt,
        private readonly Authorization $authorization,
        private readonly AuditService $audit,
    ) {}

    public function getUserByEmail(string $email): ?User
    {
        return User::query()->where('email', strtolower(trim($email)))->first();
    }

    /**
     * @return list<CompanyUser>
     */
    public function listCompanyMemberships(string $userId): array
    {
        return CompanyUser::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->get()
            ->all();
    }

    public function ensureSyncSequence(string $companyId): void
    {
        if (! SyncSequence::query()->find($companyId)) {
            SyncSequence::query()->create([
                'company_id' => $companyId,
                'next_value' => 1,
            ]);
        }
    }

    public function assertUserCanAuthenticate(User $user): void
    {
        if ($user->status === 'inactive') {
            throw new UnauthorizedException('User is inactive');
        }
        if ($user->status === 'suspended') {
            throw new UnauthorizedException('User is suspended');
        }
        if ($user->status !== 'active') {
            throw new UnauthorizedException('User cannot authenticate');
        }
    }

    public function login(
        string $email,
        string $password,
        ?string $companyId = null,
        ?string $deviceIdentifier = null,
        ?string $deviceName = null,
        ?string $platform = null,
        ?string $appVersion = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $user = $this->getUserByEmail($email);
        if ($user === null || ! Hash::check($password, $user->password_hash)) {
            $this->audit->write(
                action: 'auth.login_failed',
                metadata: ['email' => strtolower(trim($email))],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
            throw new UnauthorizedException('Invalid email or password');
        }
        $this->assertUserCanAuthenticate($user);

        $companies = [];
        foreach ($this->listCompanyMemberships($user->id) as $m) {
            $company = Company::query()->find($m->company_id);
            if ($company === null || $company->status !== 'active') {
                continue;
            }
            $role = $m->role_id ? Role::query()->find($m->role_id) : null;
            $companies[] = [
                'id' => (string) $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'role' => $role?->name,
            ];
        }

        $selectedCompanyId = $companyId;
        if ($selectedCompanyId === null && count($companies) === 1) {
            $selectedCompanyId = $companies[0]['id'];
        }
        if ($selectedCompanyId !== null) {
            if (! $user->is_super_admin && ! collect($companies)->contains(fn ($c) => $c['id'] === $selectedCompanyId)) {
                throw new ForbiddenException('Not a member of the requested company');
            }
            $company = Company::query()->find($selectedCompanyId);
            if ($company === null || $company->status !== 'active') {
                throw new ValidationAppException('Company is not available');
            }
            $this->ensureSyncSequence($selectedCompanyId);
        }

        $device = null;
        if ($deviceIdentifier !== null && $selectedCompanyId !== null) {
            $device = $this->registerOrTouchDevice(
                $user->id,
                $selectedCompanyId,
                $deviceIdentifier,
                $deviceName ?? 'Unknown device',
                $platform ?? 'unknown',
                $appVersion,
            );
        }

        [$session, $refreshRaw] = $this->createSession($user, $selectedCompanyId, $device?->id);
        [$accessToken, $expiresIn] = $this->jwt->createAccessToken(
            $user->id,
            $session->id,
            $selectedCompanyId,
            $device?->id,
            (bool) $user->is_super_admin,
        );
        $user->last_login_at = CarbonImmutable::now('UTC');
        $user->save();
        $permissions = $this->authorization->loadPermissionCodes($user, $selectedCompanyId);
        $roles = $this->roleNames($user, $selectedCompanyId);
        $this->audit->write(
            action: 'auth.login',
            userId: $user->id,
            companyId: $selectedCompanyId,
            deviceId: $device?->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $accountType = ($user->is_super_admin || collect($roles)->contains(fn ($r) => str_contains(strtolower($r), 'admin')))
            ? 'admin'
            : 'user';

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshRaw,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
            'user' => $this->userPublic($user),
            'account_type' => $accountType,
            'companies' => $companies,
            'current_company_id' => $selectedCompanyId,
            'roles' => $roles,
            'permissions' => collect($permissions)->sort()->values()->all(),
            'device' => $device ? $this->devicePublic($device) : null,
            'session_id' => (string) $session->id,
        ];
    }

    public function refresh(string $refreshToken, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $tokenHash = $this->jwt->hashToken($refreshToken);
        $session = AuthSession::query()->where('refresh_token_hash', $tokenHash)->first();
        if ($session === null) {
            throw new UnauthorizedException('Invalid refresh token');
        }
        if ($session->status !== 'active') {
            $this->revokeFamily($session->family_id);
            $this->audit->write(
                action: 'auth.refresh_reuse_detected',
                userId: $session->user_id,
                companyId: $session->company_id,
                deviceId: $session->device_id,
                metadata: ['family_id' => (string) $session->family_id],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
            throw new UnauthorizedException('Refresh token reuse detected');
        }
        if ($session->expires_at <= CarbonImmutable::now('UTC')) {
            $session->status = 'expired';
            $session->revoked_at = CarbonImmutable::now('UTC');
            $session->save();
            throw new UnauthorizedException('Refresh token expired');
        }
        $user = User::query()->find($session->user_id);
        if ($user === null) {
            throw new UnauthorizedException('User not found');
        }
        $this->assertUserCanAuthenticate($user);
        if ($session->device_id !== null) {
            $device = Device::query()->find($session->device_id);
            if ($device === null || $device->status !== 'active') {
                $this->raiseDeviceInactive($device);
            }
        }
        $session->status = 'rotated';
        $session->revoked_at = CarbonImmutable::now('UTC');
        $session->save();
        [$newSession, $refreshRaw] = $this->createSession(
            $user,
            $session->company_id,
            $session->device_id,
            $session->family_id,
        );
        $session->replaced_by_id = $newSession->id;
        $session->save();
        [$accessToken, $expiresIn] = $this->jwt->createAccessToken(
            $user->id,
            $newSession->id,
            $session->company_id,
            $session->device_id,
            (bool) $user->is_super_admin,
        );
        $this->audit->write(
            action: 'auth.refresh',
            userId: $user->id,
            companyId: $session->company_id,
            deviceId: $session->device_id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshRaw,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
            'session_id' => (string) $newSession->id,
            'current_company_id' => $session->company_id,
        ];
    }

    public function logout(string $sessionId, string $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $session = AuthSession::query()->find($sessionId);
        if ($session === null || $session->user_id !== $userId) {
            return;
        }
        if ($session->status === 'active') {
            $session->status = 'revoked';
            $session->revoked_at = CarbonImmutable::now('UTC');
            $session->save();
        }
        $this->audit->write(
            action: 'auth.logout',
            userId: $userId,
            companyId: $session->company_id,
            deviceId: $session->device_id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    public function switchCompany(
        User $user,
        AuthSession $session,
        string $companyId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        if (! $user->is_super_admin) {
            $membership = CompanyUser::query()
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->first();
            if ($membership === null) {
                throw new ForbiddenException('Not a member of the requested company');
            }
        }
        $company = Company::query()->find($companyId);
        if ($company === null || $company->status !== 'active') {
            throw new NotFoundException('Company not found');
        }
        $this->ensureSyncSequence($companyId);
        $session->status = 'rotated';
        $session->revoked_at = CarbonImmutable::now('UTC');
        $session->save();
        $deviceId = $session->device_id;
        if ($deviceId !== null) {
            $device = Device::query()->find($deviceId);
            if ($device !== null && $device->company_id !== $companyId) {
                $device = $this->registerOrTouchDevice(
                    $user->id,
                    $companyId,
                    $device->device_identifier,
                    $device->device_name,
                    $device->platform,
                    $device->app_version,
                );
                $deviceId = $device->id;
            }
        }
        [$newSession, $refreshRaw] = $this->createSession($user, $companyId, $deviceId, $session->family_id);
        $session->replaced_by_id = $newSession->id;
        $session->save();
        [$accessToken, $expiresIn] = $this->jwt->createAccessToken(
            $user->id,
            $newSession->id,
            $companyId,
            $deviceId,
            (bool) $user->is_super_admin,
        );
        $permissions = $this->authorization->loadPermissionCodes($user, $companyId);
        $roles = $this->roleNames($user, $companyId);
        $this->audit->write(
            action: 'auth.switch_company',
            userId: $user->id,
            companyId: $companyId,
            deviceId: $deviceId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshRaw,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
            'current_company_id' => $companyId,
            'roles' => $roles,
            'permissions' => collect($permissions)->sort()->values()->all(),
            'session_id' => (string) $newSession->id,
            'company' => [
                'id' => (string) $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ],
        ];
    }

    public function me(User $user, ?string $companyId, ?string $deviceId): array
    {
        $permissions = $this->authorization->loadPermissionCodes($user, $companyId);
        $roles = $this->roleNames($user, $companyId);
        $company = $companyId ? Company::query()->find($companyId) : null;
        $device = $deviceId ? Device::query()->find($deviceId) : null;
        $memberships = [];
        foreach ($this->listCompanyMemberships($user->id) as $m) {
            $c = Company::query()->find($m->company_id);
            if ($c === null) {
                continue;
            }
            $role = $m->role_id ? Role::query()->find($m->role_id) : null;
            $memberships[] = [
                'id' => (string) $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'role' => $role?->name,
            ];
        }

        return [
            'user' => $this->userPublic($user),
            'current_company' => $company ? [
                'id' => (string) $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ] : null,
            'companies' => $memberships,
            'roles' => $roles,
            'permissions' => collect($permissions)->sort()->values()->all(),
            'device' => $device ? $this->devicePublic($device) : null,
        ];
    }

    /**
     * @return array{0: AuthSession, 1: string}
     */
    public function createSession(
        User $user,
        ?string $companyId,
        ?string $deviceId,
        ?string $familyId = null,
    ): array {
        $raw = $this->jwt->generateRefreshToken();
        $now = CarbonImmutable::now('UTC');
        $session = AuthSession::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'company_id' => $companyId,
            'device_id' => $deviceId,
            'refresh_token_hash' => $this->jwt->hashToken($raw),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'status' => 'active',
            'expires_at' => $now->addSeconds((int) config('nexabiz.refresh_token_ttl_seconds')),
            'last_used_at' => $now,
        ]);

        return [$session, $raw];
    }

    public function revokeFamily(string $familyId): void
    {
        $now = CarbonImmutable::now('UTC');
        AuthSession::query()
            ->where('family_id', $familyId)
            ->where('status', 'active')
            ->get()
            ->each(function (AuthSession $row) use ($now): void {
                $row->status = 'revoked';
                $row->revoked_at = $now;
                $row->save();
            });
    }

    public function registerOrTouchDevice(
        string $userId,
        string $companyId,
        string $deviceIdentifier,
        string $deviceName,
        string $platform,
        ?string $appVersion,
    ): Device {
        $device = Device::query()
            ->where('company_id', $companyId)
            ->where('device_identifier', $deviceIdentifier)
            ->first();
        $now = CarbonImmutable::now('UTC');
        if ($device === null) {
            $device = Device::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'company_id' => $companyId,
                'device_name' => $deviceName,
                'platform' => $platform,
                'app_version' => $appVersion,
                'device_identifier' => $deviceIdentifier,
                'status' => 'active',
                'last_seen_at' => $now,
            ]);
            $this->audit->write(
                action: 'device.registered',
                userId: $userId,
                companyId: $companyId,
                deviceId: $device->id,
                entityType: 'device',
                entityId: (string) $device->id,
            );

            return $device;
        }
        if (in_array($device->status, ['revoked', 'blocked'], true)) {
            throw new UnauthorizedException('Device is revoked or blocked');
        }
        $device->user_id = $userId;
        $device->device_name = $deviceName;
        $device->platform = $platform;
        $device->app_version = $appVersion;
        $device->last_seen_at = $now;
        $device->save();

        return $device;
    }

    public function registerDevice(
        User $user,
        string $companyId,
        string $deviceIdentifier,
        string $deviceName,
        string $platform,
        ?string $appVersion,
    ): Device {
        return $this->registerOrTouchDevice(
            $user->id,
            $companyId,
            $deviceIdentifier,
            $deviceName,
            $platform,
            $appVersion,
        );
    }

    public function revokeDevice(User $actor, string $companyId, string $deviceId, ?string $reason = null): Device
    {
        $device = Device::query()->find($deviceId);
        if ($device === null || $device->company_id !== $companyId) {
            throw new NotFoundException('Device not found');
        }
        $device->status = 'revoked';
        $device->revoked_at = CarbonImmutable::now('UTC');
        $device->save();
        AuthSession::query()
            ->where('device_id', $device->id)
            ->where('status', 'active')
            ->get()
            ->each(function (AuthSession $s): void {
                $s->status = 'revoked';
                $s->revoked_at = CarbonImmutable::now('UTC');
                $s->save();
            });
        $this->audit->write(
            action: 'device.revoked',
            userId: $actor->id,
            companyId: $companyId,
            deviceId: $device->id,
            entityType: 'device',
            entityId: (string) $device->id,
            metadata: $reason ? ['reason' => $reason] : null,
        );

        return $device;
    }

    public function requestSyncDisable(User $user, string $companyId, string $deviceId, ?string $message = null): SyncDisableRequest
    {
        $device = Device::query()->find($deviceId);
        if ($device === null || $device->company_id !== $companyId) {
            throw new NotFoundException('Device not found');
        }
        if ($device->user_id !== $user->id && ! $user->is_super_admin) {
            throw new ForbiddenException('You can only request disable for your own device');
        }
        if ($device->status !== 'active') {
            throw new ValidationAppException('Device is not active');
        }
        $existing = SyncDisableRequest::query()
            ->where('device_id', $device->id)
            ->where('status', 'pending')
            ->first();
        if ($existing !== null) {
            return $existing;
        }
        $trimmed = trim((string) $message);
        $row = SyncDisableRequest::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'status' => 'pending',
            'message' => $trimmed !== '' ? substr($trimmed, 0, 500) : null,
        ]);
        $this->audit->write(
            action: 'sync.disable_requested',
            userId: $user->id,
            companyId: $companyId,
            deviceId: $device->id,
            entityType: 'sync_disable_request',
            entityId: (string) $row->id,
            metadata: ['message' => $row->message],
        );

        return $row;
    }

    /**
     * @return list<SyncDisableRequest>
     */
    public function listSyncDisableRequests(string $companyId, ?string $status = 'pending'): array
    {
        $q = SyncDisableRequest::query()->where('company_id', $companyId);
        if ($status) {
            $q->where('status', $status);
        }

        return $q->orderByDesc('created_at')->get()->all();
    }

    public function approveSyncDisable(User $actor, string $companyId, string $requestId): SyncDisableRequest
    {
        $row = SyncDisableRequest::query()->find($requestId);
        if ($row === null || $row->company_id !== $companyId) {
            throw new NotFoundException('Request not found');
        }
        if ($row->status !== 'pending') {
            throw new ValidationAppException('Request is not pending');
        }
        $row->status = 'approved';
        $row->resolved_by_id = $actor->id;
        $row->resolved_at = CarbonImmutable::now('UTC');
        $row->save();
        $this->revokeDevice($actor, $companyId, $row->device_id, 'sync_disable_approved');
        $this->audit->write(
            action: 'sync.disable_approved',
            userId: $actor->id,
            companyId: $companyId,
            deviceId: $row->device_id,
            entityType: 'sync_disable_request',
            entityId: (string) $row->id,
        );

        return $row;
    }

    public function rejectSyncDisable(User $actor, string $companyId, string $requestId): SyncDisableRequest
    {
        $row = SyncDisableRequest::query()->find($requestId);
        if ($row === null || $row->company_id !== $companyId) {
            throw new NotFoundException('Request not found');
        }
        if ($row->status !== 'pending') {
            throw new ValidationAppException('Request is not pending');
        }
        $row->status = 'rejected';
        $row->resolved_by_id = $actor->id;
        $row->resolved_at = CarbonImmutable::now('UTC');
        $row->save();
        $this->audit->write(
            action: 'sync.disable_rejected',
            userId: $actor->id,
            companyId: $companyId,
            deviceId: $row->device_id,
            entityType: 'sync_disable_request',
            entityId: (string) $row->id,
        );

        return $row;
    }

    public function raiseDeviceInactive(?Device $device): never
    {
        if ($device !== null) {
            $approved = SyncDisableRequest::query()
                ->where('device_id', $device->id)
                ->where('status', 'approved')
                ->first();
            if ($approved !== null) {
                throw new UnauthorizedException(
                    'Synchronization disabled by administrator',
                    ['reason' => 'sync_disable_approved'],
                );
            }
        }
        throw new UnauthorizedException('Device is revoked or blocked');
    }

    /**
     * @return list<string>
     */
    public function roleNames(User $user, ?string $companyId): array
    {
        if ($user->is_super_admin) {
            return ['Super Admin'];
        }
        if ($companyId === null) {
            return [];
        }
        $membership = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->first();
        if ($membership === null || $membership->role_id === null) {
            return [];
        }
        $role = Role::query()->find($membership->role_id);

        return $role ? [$role->name] : [];
    }

    public function userPublic(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'is_super_admin' => (bool) $user->is_super_admin,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    public function devicePublic(Device $device): array
    {
        return [
            'id' => (string) $device->id,
            'device_identifier' => (string) $device->device_identifier,
            'device_name' => $device->device_name,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'status' => $device->status,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }

    public function createUser(
        string $name,
        string $email,
        string $password,
        ?string $phone = null,
        string $status = 'active',
        bool $isSuperAdmin = false,
    ): User {
        if ($this->getUserByEmail($email) !== null) {
            throw new ValidationAppException('Email already registered');
        }

        return User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'phone' => $phone,
            'password_hash' => Hash::make($password),
            'status' => $status,
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    public function revokeUserSessions(string $userId): void
    {
        $now = CarbonImmutable::now('UTC');
        AuthSession::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->get()
            ->each(function (AuthSession $session) use ($now): void {
                $session->status = 'revoked';
                $session->revoked_at = $now;
                $session->save();
            });
    }

    public function setRolePermissions(string $roleId, array $codes): void
    {
        RolePermission::query()->where('role_id', $roleId)->delete();
        if ($codes === []) {
            return;
        }
        $perms = \App\Models\Permission::query()->whereIn('code', $codes)->get();
        foreach ($perms as $perm) {
            RolePermission::query()->create([
                'role_id' => $roleId,
                'permission_id' => $perm->id,
            ]);
        }
    }
}
