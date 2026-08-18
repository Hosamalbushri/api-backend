<?php

namespace App\Auth;

use App\Models\AuthSession;
use App\Models\User;

final class AuthContext
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public User $user,
        public ?AuthSession $session,
        public ?string $companyId,
        public ?string $deviceId,
        public array $permissions,
        public bool $isDevToken = false,
    ) {}

    public function userId(): string
    {
        return (string) $this->user->id;
    }

    public function requireCompanyId(): string
    {
        if ($this->companyId === null) {
            throw new \App\Exceptions\ValidationAppException('Company context required');
        }

        return $this->companyId;
    }

    public function permissionSet(): array
    {
        return array_fill_keys($this->permissions, true);
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions, true);
    }
}
