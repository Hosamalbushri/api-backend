<?php

namespace Tests\Unit;

use NexaBiz\Core\Support\ProductionSettings;
use Tests\TestCase;

class ProductionSettingsTest extends TestCase
{
    public function test_development_allows_dev_defaults(): void
    {
        config([
            'nexabiz.app_env' => 'development',
            'nexabiz.allow_dev_token' => true,
            'nexabiz.jwt_secret' => 'dev-jwt-secret-change-me-please-use-long-random',
            'nexabiz.cors_origins' => '*',
            'nexabiz.seed_admin_password' => 'ChangeMeAdmin!123',
        ]);
        ProductionSettings::assertSafeForEnvironment();
        $this->assertTrue(true);
    }

    public function test_productionish_rejects_dev_token(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => true,
            'nexabiz.jwt_secret' => str_repeat('a', 32),
            'nexabiz.cors_origins' => 'https://app.example.com',
            'nexabiz.seed_admin_password' => 'StrongPass!not-default-99',
            'nexabiz.auth_rate_limit_per_minute' => 20,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ALLOW_DEV_TOKEN');
        ProductionSettings::assertSafeForEnvironment();
    }

    public function test_productionish_rejects_wildcard_cors(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => false,
            'nexabiz.jwt_secret' => str_repeat('a', 32),
            'nexabiz.cors_origins' => '*',
            'nexabiz.seed_admin_password' => 'StrongPass!not-default-99',
            'nexabiz.auth_rate_limit_per_minute' => 20,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CORS_ORIGINS');
        ProductionSettings::assertSafeForEnvironment();
    }

    public function test_productionish_rejects_placeholder_jwt(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => false,
            'nexabiz.jwt_secret' => 'dev-jwt-secret-change-me-please-use-long-random',
            'nexabiz.cors_origins' => 'https://app.example.com',
            'nexabiz.seed_admin_password' => 'StrongPass!not-default-99',
            'nexabiz.auth_rate_limit_per_minute' => 20,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET');
        ProductionSettings::assertSafeForEnvironment();
    }

    public function test_productionish_rejects_default_seed_password(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => false,
            'nexabiz.jwt_secret' => str_repeat('a', 32),
            'nexabiz.cors_origins' => 'https://app.example.com',
            'nexabiz.seed_admin_password' => 'ChangeMeAdmin!123',
            'nexabiz.auth_rate_limit_per_minute' => 20,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD');
        ProductionSettings::assertSafeForEnvironment();
    }

    public function test_production_accepts_hardened_settings(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => false,
            'nexabiz.jwt_secret' => str_repeat('a', 32),
            'nexabiz.cors_origins' => 'https://app.example.com',
            'nexabiz.seed_admin_password' => 'StrongPass!not-default-99',
            'nexabiz.auth_rate_limit_per_minute' => 20,
        ]);
        ProductionSettings::assertSafeForEnvironment();
        $this->assertTrue(true);
    }

    public function test_productionish_rejects_disabled_auth_rate_limit(): void
    {
        config([
            'nexabiz.app_env' => 'production',
            'nexabiz.allow_dev_token' => false,
            'nexabiz.jwt_secret' => str_repeat('a', 32),
            'nexabiz.cors_origins' => 'https://app.example.com',
            'nexabiz.seed_admin_password' => 'StrongPass!not-default-99',
            'nexabiz.auth_rate_limit_per_minute' => 0,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AUTH_RATE_LIMIT_PER_MINUTE');
        ProductionSettings::assertSafeForEnvironment();
    }
}
