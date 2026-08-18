<?php

return [
    'app_name' => env('APP_NAME', 'nexabiz-sync-experimental'),
    'app_env' => env('APP_ENV', env('NEXABIZ_APP_ENV', 'local')),

    'dev_api_token' => env('DEV_API_TOKEN', 'dev-sync-token-change-me'),
    'allow_dev_token' => filter_var(env('ALLOW_DEV_TOKEN', false), FILTER_VALIDATE_BOOLEAN),
    'default_company_id' => env('DEFAULT_COMPANY_ID', '00000000-0000-4000-8000-000000000001'),
    'default_user_id' => env('DEFAULT_USER_ID', '00000000-0000-4000-8000-000000000002'),
    'default_device_id' => env('DEFAULT_DEVICE_ID', '00000000-0000-4000-8000-000000000003'),

    'jwt_secret' => env('JWT_SECRET', 'dev-jwt-secret-change-me-please-use-long-random'),
    'jwt_algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'jwt_issuer' => env('JWT_ISSUER', 'nexabiz-experimental'),
    'access_token_ttl_seconds' => (int) env('ACCESS_TOKEN_TTL_SECONDS', 900),
    'refresh_token_ttl_seconds' => (int) env('REFRESH_TOKEN_TTL_SECONDS', 60 * 60 * 24 * 30),

    'sync_pull_limit' => (int) env('SYNC_PULL_LIMIT', 500),
    'cors_origins' => env('CORS_ORIGINS', '*'),
    'auth_rate_limit_per_minute' => (int) env('AUTH_RATE_LIMIT_PER_MINUTE', 20),

    'seed_admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
    'seed_admin_password' => env('SEED_ADMIN_PASSWORD', 'ChangeMeAdmin!123'),
    'seed_admin_name' => env('SEED_ADMIN_NAME', 'Platform Admin'),
    'seed_company_name' => env('SEED_COMPANY_NAME', 'Demo Company A'),
    'seed_company_code' => env('SEED_COMPANY_CODE', 'COMPANY-A'),
    'seed_company_id' => env('SEED_COMPANY_ID', '00000000-0000-4000-8000-000000000001'),
    'seed_on_boot' => filter_var(env('SEED_ON_BOOT', true), FILTER_VALIDATE_BOOLEAN),
];
