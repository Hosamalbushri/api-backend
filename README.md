# NexaBiz Laravel Synchronization Backend

Native Laravel implementation of the Python FastAPI synchronization backend in `../backend`.

The Python service remains the behavioral source of truth. This application preserves:

- Flutter `RemoteSyncApi` contracts (`/api/v1/sync/push`, `/push/batch`, `/pull`, `/meta`)
- JWT identity, refresh-token rotation, RBAC, devices, and audit
- Idempotent `operation_id` ledger, per-tenant change sequences, version conflicts (HTTP 409)
- Production setting guards (`ALLOW_DEV_TOKEN`, `JWT_SECRET`, `CORS_ORIGINS`, seed password, auth rate limit)

Python used **no Celery workers and no scheduled sync jobs**. Laravel therefore uses `QUEUE_CONNECTION=sync` and does not invent background sync.

Architecture (modules, dependencies, deployment) is documented in [docs/architecture.md](docs/architecture.md).

## Quick start

```bash
cd backend-laravel
cp .env.example .env
php artisan key:generate
docker compose up --build
```

- API: http://localhost:8000
- Health: http://localhost:8000/health

Flutter dart-defines are unchanged (`SYNC_API_BASE_URL=http://127.0.0.1:8000`).

Local tests:

```bash
cd backend-laravel
php artisan test
vendor/bin/pint --test
```

## Mapping (Python → Laravel)

| Python | Laravel | Functionality | Status | Verified |
| --- | --- | --- | --- | --- |
| `app/sync/service.py` | `NexaBiz\Synchronization\Services\SyncService` | Push/pull/meta, versions, soft-delete, upsert-as-create | COMPLETE | tests |
| `app/sync/router.py` | `NexaBiz\Synchronization\Http\Controllers\SyncController` | HTTP contracts + savepoints for batch | COMPLETE | tests |
| `app/sync/schemas.py` | FormRequests + `SupportedEntities` | Entity types and payload shapes | COMPLETE | tests |
| `app/models/sync.py` | `NexaBiz\Synchronization\Models\*` | JSON payloads, sequences, operation ledger | COMPLETE | tests |
| `app/auth/service.py` | `NexaBiz\Identity\Services\AuthService` | Login/refresh/logout/switch-company/devices | COMPLETE | tests |
| `app/auth/deps.py` | `AuthenticateApi` middleware | JWT + optional dev token | COMPLETE | tests |
| `app/auth/tokens.py` | `JwtTokenService` | HS256 claims `sub/sid/typ/iss/cid/did/sa` | COMPLETE | tests |
| `app/auth/authorization.py` | `Authorization` | Permission expand + sync entity gates | COMPLETE | tests |
| `app/auth/permissions_catalog.py` | `PermissionsCatalog` | Codes, aliases, system roles | COMPLETE | tests |
| `app/auth/admin_safety.py` | `AdminSafety` | IDOR / last-admin / platform grants | COMPLETE | tests |
| `app/admin/router.py` | Identity HTTP controllers | Admin HTTP API | COMPLETE | tests |
| `app/audit/service.py` | `AuditService` via `AuditWriter` | Audit rows, no secrets | COMPLETE | tests |
| `app/core/exceptions.py` | `NexaBiz\Core\Exceptions\*` | Same `error.code` JSON envelope | COMPLETE | tests |
| `app/core/rate_limit.py` | `SlidingWindowLimiter` + `AuthRateLimit` | Login/refresh 429 | COMPLETE | tests |
| `app/core/config.py` | `ProductionSettings` | Production refuse-to-boot | COMPLETE | tests |
| `app/auth/seed.py` | `IdentitySeeder` | Idempotent permissions/roles/admin/Ahmed | COMPLETE | tests |
| Celery / cron | none (Python had none) | Sync remains request-synchronous | COMPLETE | n/a |

## Sync workflow (unchanged)

```text
Flutter SyncManager
→ POST /api/v1/sync/push  (Bearer JWT or ALLOW_DEV_TOKEN)
→ AuthenticateApi (company from session, never X-Company-Id)
→ require sync.execute + entity permission
→ SyncEngine.pushOperation
     duplicate operation_id → replay stored success/conflict
     lock entity row
     create: ensure UUID exists (never 409)
     update/delete: 409 if server.version > base_version
     missing update → upsert-as-create
     missing delete → tombstone
     increment version, append sync_changes via locked sync_sequences
     write sync_operations ledger
→ ack { entity_id, remote_version, remote_updated_at, server_payload }
```

Pull uses `sequence > cursor` (preferred) or `created_at > since`, page `limit+1` for `has_more`.

## Production

`APP_ENV=production|staging|prod` refuses:

- `ALLOW_DEV_TOKEN=true`
- short / placeholder `JWT_SECRET`
- `CORS_ORIGINS=*`
- default `SEED_ADMIN_PASSWORD`
- `AUTH_RATE_LIMIT_PER_MINUTE=0`

Run `php artisan nexabiz:check-production` before serving traffic.
