<?php

namespace NexaBiz\Initialization\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NexaBiz\Audit\Contracts\AuditWriter;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Core\Http\Controllers\Controller;
use NexaBiz\Identity\Services\Authorization;
use NexaBiz\Identity\Support\AuthContext;
use NexaBiz\Identity\Support\PermissionsCatalog;
use NexaBiz\Initialization\Services\BootstrapService;
use NexaBiz\Initialization\Support\MasterEntities;

class BootstrapController extends Controller
{
    public function __construct(
        private readonly BootstrapService $bootstrap,
        private readonly Authorization $authorization,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * GET /api/v1/bootstrap — does the authenticated user's company hold
     * initialization data, and what does a fresh device need to know?
     */
    public function status(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [
                PermissionsCatalog::SETTINGS_VIEW,
                PermissionsCatalog::SYNC_VIEW,
                PermissionsCatalog::SYNC_EXECUTE,
            ],
            true,
        );
        $companyId = $auth->requireCompanyId();

        $status = $this->bootstrap->status($companyId);

        $this->audit->write(
            action: 'bootstrap.status',
            userId: $auth->userId(),
            companyId: $companyId,
            deviceId: $auth->deviceId,
            metadata: ['initialized' => $status['initialized']],
        );

        return response()->json(['data' => [
            'initialized' => $status['initialized'],
            'company' => [
                'id' => (string) $companyId,
                'name' => (string) ($auth->user->memberships()
                    ->where('company_id', $companyId)
                    ->first()?->company?->name ?? ''),
            ],
            'initialization' => $status['initialization'],
            'counts' => $status['counts'],
            'snapshot' => $status['snapshot'],
            'server' => [
                'version' => (string) config('nexabiz.app_version', '1.0.0'),
                'api_version' => 'v1',
            ],
        ]]);
    }

    /**
     * GET /api/v1/bootstrap/data — one page of one master entity type.
     *
     * Query: entity_type (required), cursor (opaque keyset), limit.
     * The snapshot bound (taken_at) is supplied by the client from the
     * status response so every page belongs to the same logical snapshot.
     */
    public function data(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [
                PermissionsCatalog::SETTINGS_VIEW,
                PermissionsCatalog::SYNC_VIEW,
                PermissionsCatalog::SYNC_EXECUTE,
            ],
            true,
        );
        $companyId = $auth->requireCompanyId();

        $entityType = (string) $request->query('entity_type', '');
        if (! MasterEntities::isMaster($entityType)) {
            throw new ValidationAppException(
                'entity_type must be one of the initialization types',
                ['supported' => MasterEntities::types()],
            );
        }

        $takenAtRaw = (string) $request->query('taken_at', '');
        if ($takenAtRaw === '') {
            throw new ValidationAppException('taken_at is required');
        }
        try {
            $takenAt = CarbonImmutable::parse($takenAtRaw)->utc();
        } catch (\Throwable) {
            throw new ValidationAppException('taken_at must be an ISO8601 timestamp');
        }

        $limit = (int) $request->query('limit', (string) (int) config('nexabiz.page_size'));
        $limit = min(max($limit, 1), 1000);

        $cursor = $request->query('cursor');

        $page = $this->bootstrap->page($companyId, $entityType, $cursor === null ? null : (string) $cursor, $limit, $takenAt);

        return response()->json(['data' => [
            'entity_type' => $entityType,
            'items' => $page['items'],
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
        ]]);
    }

    private function context(Request $request): AuthContext
    {
        /** @var AuthContext|null $auth */
        $auth = $request->attributes->get('auth_context');
        if ($auth === null) {
            abort(401);
        }

        return $auth;
    }
}
