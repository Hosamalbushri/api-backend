<?php

namespace App\Support;

class ProductionSettings
{
    public static function isProductionish(?string $env = null): bool
    {
        $env = strtolower(trim($env ?? (string) config('nexabiz.app_env')));

        return in_array($env, ['production', 'prod', 'staging'], true);
    }

    public static function assertSafeForEnvironment(): void
    {
        $env = (string) config('nexabiz.app_env');
        if (! self::isProductionish($env)) {
            return;
        }

        if (config('nexabiz.allow_dev_token')) {
            throw new \RuntimeException("ALLOW_DEV_TOKEN must be false when APP_ENV is '{$env}'");
        }

        $secret = (string) config('nexabiz.jwt_secret');
        if (
            str_starts_with($secret, 'dev-jwt-secret')
            || $secret === 'REPLACE_WITH_OPENSSL_RAND_HEX_32'
            || strlen(trim($secret)) < 32
        ) {
            throw new \RuntimeException("JWT_SECRET must be a unique secret (≥ 32 chars) for '{$env}'");
        }

        $cors = trim((string) config('nexabiz.cors_origins'));
        if ($cors === '' || $cors === '*') {
            throw new \RuntimeException("CORS_ORIGINS=* (or empty) is not allowed in '{$env}'");
        }

        $seedPassword = (string) config('nexabiz.seed_admin_password');
        if (in_array($seedPassword, ['ChangeMeAdmin!123', 'REPLACE_STRONG_PASSWORD'], true)) {
            throw new \RuntimeException("SEED_ADMIN_PASSWORD must be changed before '{$env}' deploy");
        }

        if ((int) config('nexabiz.auth_rate_limit_per_minute') <= 0) {
            throw new \RuntimeException("AUTH_RATE_LIMIT_PER_MINUTE must be > 0 in '{$env}'");
        }
    }
}
