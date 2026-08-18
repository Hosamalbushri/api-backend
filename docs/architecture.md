# NexaBiz Laravel architecture

This document describes the **Laravel synchronization backend** in `backend-laravel/` after the modular restructuring. Flutter remains the system of record for Products, Inventory, Sales, and Accounting. This API is identity plus generic JSON replication.

## Architecture before

A single Laravel application with domain classes under `app/`:

- `app/Auth/*` — JWT, RBAC, admin safety
- `app/Sync/*` — push/pull/meta engine
- `app/Audit/*` — audit writer
- `app/Http/Controllers/Api/V1/*` — mixed identity and sync HTTP
- One migration creating every table
- Working HTTP contracts matching the Python FastAPI backend

That layout was functional and tested, but identity, sync, and shared infrastructure lived in one namespace with no package boundary.

## Architecture after

```text
Laravel application (thin shell)
├── app/                  AppServiceProvider, base Controller
├── config/nexabiz.php    Environment-facing settings
├── database/migrations   Cache and queue tables only
└── packages/NexaBiz/
    ├── Core/             Exceptions, middleware, health, production guards
    ├── Audit/            AuditWriter contract + AuditService
    ├── Identity/         Users, JWT, RBAC, companies, devices, admin APIs
    └── Synchronization/  Generic sync engine, jobs remain unused (Python had none)
```

### Application layer (what stays in Laravel)

- HTTP kernel / exception JSON envelope (`bootstrap/app.php`)
- Environment files, Docker, queue/cache/session config
- `config/nexabiz.php` as the operator-facing merge of module defaults
- Framework tables: `cache`, `jobs`

### Domain modules

| Module | Responsibility | Owns |
| --- | --- | --- |
| **Core** | Cross-cutting HTTP/security/health | Exceptions, CorrelationId, AuthRateLimit, SecurityHeaders, ProductionSettings, `/health` |
| **Audit** | Append-only audit rows | `audit_logs`, `AuditWriter` |
| **Identity** | Who the caller is, what they may do | Users, companies, roles, permissions, devices, sessions, JWT, admin routes |
| **Synchronization** | Multi-device JSON replication | `sync_*` tables, `SyncEngine`, push/pull/meta |

Modules that were **not** created (they are Flutter local ERP domains, not server tables): Products, Inventory, Sales, Purchases, Accounting, Notifications, Reporting.

### Shared infrastructure

Core + Laravel framework. Audit is a small shared capability consumed through `AuditWriter`.

### Dependency rules

```text
Core          → (Laravel only)
Audit         → Core
Identity      → Core, Audit (AuditWriter)
Synchronization → Core, Audit (AuditWriter), Identity (AuthContext, Company, Authorization)
```

Forbidden: Identity must not import Synchronization models. Identity publishes `CompanyProvisioned`; Synchronization listens and creates `sync_sequences`.

### Communication

| Need | Mechanism |
| --- | --- |
| Write an audit row | `AuditWriter` contract |
| Push/pull/meta | `SyncEngine` contract |
| New company exists | `CompanyProvisioned` event |
| Authz for sync entity types | `Authorization` in Identity (permission catalog) |
| Long-running sync jobs | **Not used** — Python and Flutter expect synchronous HTTP |

## Public module APIs

- **Audit:** `NexaBiz\Audit\Contracts\AuditWriter`
- **Identity:** `AuthService`, `Authorization`, `AuthContext`, `CompanyProvisioned`, HTTP under `/api/v1/auth|users|roles|companies|devices`
- **Synchronization:** `NexaBiz\Synchronization\Contracts\SyncEngine`, HTTP under `/api/v1/sync/*`

## Configuration

Environment variables stay in `.env` / `.env.example`. Module defaults live in:

- `packages/NexaBiz/Core/src/Config/core.php`
- `packages/NexaBiz/Identity/src/Config/identity.php`
- `packages/NexaBiz/Synchronization/src/Config/synchronization.php`

They merge into `config('nexabiz.*')`. `config/nexabiz.php` remains the deployment-facing file and wins on key conflicts.

Never commit secrets. Production/staging boot refuses `ALLOW_DEV_TOKEN`, short JWT secrets, `CORS_ORIGINS=*`, default seed passwords, and a disabled auth rate limit.

## Database

| Module | Tables |
| --- | --- |
| Identity | companies, users, permissions, roles, role_permissions, company_users, devices, auth_sessions, sync_disable_requests |
| Audit | audit_logs |
| Synchronization | sync_sequences, sync_entities, sync_changes, sync_operations |
| Laravel | cache, jobs |

Migrations are owned by each module. Do not edit an applied production migration; add a new one.

## Queues and scheduler

`QUEUE_CONNECTION=sync`. There is no Celery-equivalent worker for replication. Do not add `sync:run` cron jobs unless the Flutter contract changes.

Operational command: `php artisan nexabiz:check-production`  
Identity seed: `php artisan nexabiz:identity:seed`

## APIs

Unchanged Flutter contracts:

- `POST /api/v1/sync/push`
- `POST /api/v1/sync/push/batch`
- `GET /api/v1/sync/pull`
- `GET /api/v1/sync/meta/{type}/{id}`
- `POST /api/v1/auth/login` (and refresh/logout/me/switch-company)
- Admin users/roles/companies/devices

JSON errors remain `{ "error": { "code", "message", "details" } }`.

## Testing

```bash
cd backend-laravel
php artisan test
vendor/bin/pint --test
```

Feature tests live in `tests/Feature`. Module unit tests live under `packages/NexaBiz/*/tests`.

## Deployment

Requires PHP 8.3. Composer `config.platform.php` is `8.3.32` so installs stay compatible with this runtime (Symfony 7.4, not Symfony 8).

1. `composer install --no-dev --optimize-autoloader`
2. Set production `.env` (`APP_DEBUG=false`, strong `JWT_SECRET`, explicit `CORS_ORIGINS`, `ALLOW_DEV_TOKEN=false`, `SEED_ON_BOOT=false` after first seed)
3. `php artisan migrate --force`
4. `php artisan nexabiz:identity:seed` (or first-boot seed if enabled)
5. `php artisan nexabiz:check-production`
6. `php artisan config:cache` / `route:cache`
7. Serve PHP-FPM or `docker compose up --build`

No queue worker is required for sync. Use one if you later add mail or report jobs.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 401 on sync | Bearer JWT vs `ALLOW_DEV_TOKEN`; company comes from the session, not `X-Company-Id` |
| 409 conflict | Client `base_version` behind server version (update/delete) |
| Duplicate class / wrong namespace | `composer dump-autoload` after pulling package moves |
| Seed users missing | `php artisan nexabiz:identity:seed` |
| Production boot exception | `php artisan nexabiz:check-production` |
