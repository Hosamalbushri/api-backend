<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditService;
use App\Auth\AuthContext;
use App\Auth\Authorization;
use App\Auth\PermissionsCatalog;
use App\Exceptions\AppException;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationAppException;
use App\Http\Controllers\Controller;
use App\Sync\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function __construct(
        private readonly SyncService $sync,
        private readonly Authorization $authorization,
        private readonly AuditService $audit,
    ) {}

    public function push(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $body = $request->validate([
            'entity_type' => 'required|string',
            'operation' => 'required|array',
            'operation.operation_id' => 'required|uuid',
            'operation.entity_type' => 'required|string',
            'operation.entity_id' => 'required|uuid',
            'operation.type' => 'required|in:create,update,delete',
            'operation.payload' => 'nullable|array',
            'operation.base_version' => 'nullable|integer',
        ]);
        $op = $this->normalizeOperation($body['operation']);
        if ($op['entity_type'] !== $body['entity_type']) {
            $op['entity_type'] = $body['entity_type'];
        }
        $companyId = $auth->requireCompanyId();
        try {
            $this->authorization->requireSyncOperationPermission(
                $auth->permissions,
                $op['entity_type'],
                $op['type'],
            );
        } catch (AppException $exc) {
            $this->audit->write(
                action: 'sync.authorization_failure',
                userId: $auth->userId(),
                companyId: $companyId,
                deviceId: $auth->deviceId,
                entityType: $op['entity_type'],
                entityId: (string) $op['entity_id'],
                metadata: [
                    'operation' => $op['type'],
                    'error' => $exc->errorCode,
                    'message' => $exc->getMessage(),
                ],
            );
            throw $exc;
        }

        $ack = DB::transaction(fn () => $this->sync->pushOperation(
            companyId: $companyId,
            userId: $auth->userId(),
            deviceId: $auth->deviceId ?: $auth->userId(),
            op: $op,
        ));

        return response()->json($ack);
    }

    public function pushBatch(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $body = $request->validate([
            'operations' => 'required|array|min:1',
            'operations.*.operation_id' => 'required|uuid',
            'operations.*.entity_type' => 'required|string',
            'operations.*.entity_id' => 'required|uuid',
            'operations.*.type' => 'required|in:create,update,delete',
            'operations.*.payload' => 'nullable|array',
            'operations.*.base_version' => 'nullable|integer',
        ]);
        $companyId = $auth->requireCompanyId();
        $results = [];
        DB::beginTransaction();
        try {
            foreach ($body['operations'] as $raw) {
                $op = $this->normalizeOperation($raw);
                try {
                    $this->authorization->requireSyncOperationPermission(
                        $auth->permissions,
                        $op['entity_type'],
                        $op['type'],
                    );
                    $ack = DB::transaction(fn () => $this->sync->pushOperation(
                        companyId: $companyId,
                        userId: $auth->userId(),
                        deviceId: $auth->deviceId ?: $auth->userId(),
                        op: $op,
                    ));
                    $results[] = [
                        'operation_id' => (string) $op['operation_id'],
                        'status' => 'success',
                        'ack' => $ack,
                    ];
                } catch (ConflictException $exc) {
                    $results[] = [
                        'operation_id' => (string) $op['operation_id'],
                        'status' => 'conflict',
                        'conflict' => $exc->details,
                        'error' => ['code' => $exc->errorCode, 'message' => $exc->getMessage()],
                    ];
                } catch (AppException $exc) {
                    if ($exc->errorCode === 'permission_denied') {
                        $this->audit->write(
                            action: 'sync.authorization_failure',
                            userId: $auth->userId(),
                            companyId: $companyId,
                            deviceId: $auth->deviceId,
                            entityType: $op['entity_type'],
                            entityId: (string) $op['entity_id'],
                            metadata: [
                                'operation' => $op['type'],
                                'error' => $exc->errorCode,
                                'message' => $exc->getMessage(),
                            ],
                        );
                    }
                    $results[] = [
                        'operation_id' => (string) $op['operation_id'],
                        'status' => 'error',
                        'error' => [
                            'code' => $exc->errorCode,
                            'message' => $exc->getMessage(),
                            'details' => $exc->details,
                        ],
                    ];
                } catch (\Throwable $exc) {
                    $results[] = [
                        'operation_id' => (string) $op['operation_id'],
                        'status' => 'error',
                        'error' => ['code' => 'server_error', 'message' => $exc->getMessage()],
                    ];
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['results' => $results]);
    }

    public function pull(Request $request): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::SYNC_EXECUTE, PermissionsCatalog::SYNC_VIEW],
            true,
        );
        $validated = $request->validate([
            'entity_type' => 'nullable|string',
            'cursor' => 'nullable|integer|min:0',
            'since' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:2000',
        ]);
        $since = isset($validated['since'])
            ? CarbonImmutable::parse($validated['since'])->utc()
            : null;
        $limit = $validated['limit'] ?? (int) config('nexabiz.sync_pull_limit');
        [$changes, $nextCursor, $hasMore] = $this->sync->pull(
            companyId: $auth->requireCompanyId(),
            entityType: $validated['entity_type'] ?? null,
            cursor: isset($validated['cursor']) ? (int) $validated['cursor'] : null,
            since: $since,
            limit: (int) $limit,
        );

        return response()->json([
            'changes' => $changes,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    public function meta(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $auth = $this->context($request);
        $this->authorization->requirePermissions(
            $auth->permissions,
            [PermissionsCatalog::SYNC_EXECUTE, PermissionsCatalog::SYNC_VIEW],
            true,
        );
        $entity = $this->sync->getMeta(
            companyId: $auth->requireCompanyId(),
            entityType: $entityType,
            entityId: $entityId,
        );
        if ($entity === null) {
            return response()->json(null);
        }

        return response()->json([
            'entity_id' => (string) $entity->entity_uuid,
            'version' => (int) $entity->version,
            'updated_at' => $entity->updated_at?->toIso8601String(),
            'payload' => $entity->payload,
        ]);
    }

    private function normalizeOperation(array $op): array
    {
        if (! isset($op['type'])) {
            throw new ValidationAppException('operations must not be empty');
        }

        return [
            'operation_id' => $op['operation_id'],
            'entity_type' => $op['entity_type'],
            'entity_id' => $op['entity_id'],
            'type' => $op['type'],
            'payload' => $op['payload'] ?? [],
            'base_version' => (int) ($op['base_version'] ?? 0),
        ];
    }

    private function context(Request $request): AuthContext
    {
        return $request->attributes->get('auth_context');
    }
}
