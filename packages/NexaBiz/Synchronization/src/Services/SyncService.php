<?php

namespace NexaBiz\Synchronization\Services;

use NexaBiz\Core\Exceptions\ConflictException;
use NexaBiz\Core\Exceptions\NotFoundException;
use NexaBiz\Core\Exceptions\ValidationAppException;
use NexaBiz\Identity\Models\Company;
use NexaBiz\Synchronization\Contracts\SyncEngine;
use NexaBiz\Synchronization\Models\SyncChange;
use NexaBiz\Synchronization\Models\SyncEntity;
use NexaBiz\Synchronization\Models\SyncOperation;
use NexaBiz\Synchronization\Models\SyncSequence;
use NexaBiz\Synchronization\Support\SupportedEntities;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService implements SyncEngine
{
    /**
     * Generic experimental sync engine.
     *
     * Adapts to Flutter RemoteSyncApi / InMemoryRemoteSyncApi semantics:
     * - UUID identity
     * - server-authoritative versions
     * - soft deletes
     * - conflict when server.version > base_version (update/delete only)
     * - create is UUID ensure-exists (idempotent; never version-conflicts)
     * - idempotent by operation_id
     */
    public function ensureCompany(string $companyId): Company
    {
        $company = Company::query()->find($companyId);
        if ($company === null) {
            throw new NotFoundException('Company not found');
        }
        if (($company->status ?? 'active') !== 'active') {
            throw new ValidationAppException('Company is not active');
        }
        if (! SyncSequence::query()->find($companyId)) {
            SyncSequence::query()->create([
                'company_id' => $companyId,
                'next_value' => 1,
            ]);
        }

        return $company;
    }

    public function nextSequence(string $companyId): int
    {
        $seq = SyncSequence::query()->find($companyId);
        if ($seq === null) {
            $seq = SyncSequence::query()->create([
                'company_id' => $companyId,
                'next_value' => 1,
            ]);
        }
        $locked = SyncSequence::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();
        $value = (int) $locked->next_value;
        $locked->next_value = $value + 1;
        $locked->save();

        return $value;
    }

    public function getEntity(
        string $companyId,
        string $entityType,
        string $entityUuid,
        bool $forUpdate = false,
    ): ?SyncEntity {
        $query = SyncEntity::query()->where([
            'company_id' => $companyId,
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
        ]);
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function validateOperation(array $op): void
    {
        if (! SupportedEntities::isSupported($op['entity_type'])) {
            throw new ValidationAppException(
                'Unsupported entity_type: '.$op['entity_type'],
                ['supported' => SupportedEntities::sorted()],
            );
        }
        if ((int) $op['base_version'] < 0) {
            throw new ValidationAppException('base_version must be >= 0');
        }
        if ($op['entity_type'] === 'journal_entry' && ($op['type'] === 'create' || $op['type'] === 'update')) {
            $this->validateJournalEntryPayload($op['payload'] ?? []);
        }
    }

    private function validateJournalEntryPayload(array $payload): void
    {
        if (! is_array($payload)) {
            return;
        }
        $lines = $payload['lines'] ?? $payload['entries'] ?? [];
        if (! is_array($lines) || count($lines) === 0) {
            return;
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $debit = (float) ($line['debit'] ?? $line['debitAmount'] ?? 0);
            $credit = (float) ($line['credit'] ?? $line['creditAmount'] ?? 0);
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new ValidationAppException(
                sprintf('Unbalanced journal entry: total debit (%.2f) != total credit (%.2f)', $totalDebit, $totalCredit),
                [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                ]
            );
        }
    }

    public function payloadForStore(
        array $op,
        bool $deleted,
        int $version,
        CarbonImmutable $updatedAt,
        ?CarbonImmutable $deletedAt,
    ): array {
        $payload = $op['payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        } else {
            $payload = json_decode(json_encode($payload), true) ?? [];
        }
        if (($op['entity_type'] ?? '') === 'inventory_item') {
            $payload['id'] = (string) $op['entity_id'];
        } else {
            $payload['uuid'] = (string) $op['entity_id'];
        }
        $payload['version'] = $version;
        $payload['updatedAt'] = $this->epochMs($updatedAt);
        if ($deleted) {
            $payload['deleted'] = true;
            if ($deletedAt !== null) {
                $payload['deletedAt'] = $this->epochMs($deletedAt);
            }
        } elseif (array_key_exists('deleted', $payload)) {
            unset($payload['deleted']);
        }

        return $payload;
    }

    public function ackFromEntity(SyncEntity $entity, ?string $operationId = null): array
    {
        $payload = $entity->payload ?? [];

        return [
            'entity_id' => (string) $entity->entity_uuid,
            'remote_version' => (int) $entity->version,
            'remote_updated_at' => $entity->updated_at?->toIso8601String(),
            'server_payload' => is_array($payload) ? $payload : [],
            'status' => 'success',
            'operation_id' => $operationId,
        ];
    }

    public function recordChange(string $companyId, SyncEntity $entity, string $operation): int
    {
        $sequence = $this->nextSequence($companyId);
        SyncChange::query()->create([
            'sequence' => $sequence,
            'company_id' => $companyId,
            'entity_type' => $entity->entity_type,
            'entity_uuid' => $entity->entity_uuid,
            'operation' => $operation,
            'version' => $entity->version,
            'payload' => $entity->payload ?? [],
            'deleted' => $entity->deleted_at !== null,
        ]);

        return $sequence;
    }

    public function saveOperationResult(
        string $companyId,
        ?string $userId,
        ?string $deviceId,
        array $op,
        string $status,
        array $result,
    ): void {
        SyncOperation::query()->create([
            'company_id' => $companyId,
            'operation_id' => $op['operation_id'],
            'entity_type' => $op['entity_type'],
            'entity_uuid' => $op['entity_id'],
            'operation_type' => $op['type'],
            'status' => $status,
            'result' => $result,
            'user_id' => $userId,
            'device_id' => $deviceId,
            'processed_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function getPriorOperation(string $companyId, string $operationId): ?SyncOperation
    {
        return SyncOperation::query()
            ->where('company_id', $companyId)
            ->where('operation_id', $operationId)
            ->first();
    }

    public function pushOperation(
        string $companyId,
        string $userId,
        string $deviceId,
        array $op,
    ): array {
        $this->ensureCompany($companyId);
        $this->validateOperation($op);

        $prior = $this->getPriorOperation($companyId, $op['operation_id']);
        if ($prior !== null) {
            Log::channel('sync')->info('PUSH duplicate operation_id={id} entity_type={type} status={status}', [
                'id' => $op['operation_id'],
                'type' => $op['entity_type'],
                'status' => $prior->status,
            ]);
            if ($prior->status === 'success') {
                return $prior->result;
            }
            if ($prior->status === 'conflict') {
                $details = $prior->result ?? [];
                throw new ConflictException(
                    $details['message'] ?? 'Synchronization conflict',
                    $details['entity_type'] ?? $op['entity_type'],
                    $details['entity_id'] ?? (string) $op['entity_id'],
                    (int) ($details['server_version'] ?? 0),
                    (int) ($details['client_base_version'] ?? $op['base_version']),
                    $details['server_record'] ?? [],
                    $details['server_updated_at'] ?? null,
                );
            }
            throw new ValidationAppException('Previous operation ended with status='.$prior->status);
        }

        Log::channel('sync')->info(
            'PUSH received operation_id={id} entity_type={type} entity_id={eid} type={op} base_version={bv}',
            [
                'id' => $op['operation_id'],
                'type' => $op['entity_type'],
                'eid' => $op['entity_id'],
                'op' => $op['type'],
                'bv' => $op['base_version'],
            ],
        );

        try {
            $ack = $this->applyMutation($companyId, $op);
        } catch (ConflictException $exc) {
            $this->saveOperationResult($companyId, $userId, $deviceId, $op, 'conflict', [
                'message' => $exc->getMessage(),
                ...$exc->details,
            ]);
            Log::channel('sync')->info('PUSH conflict operation_id={id} entity_type={type} entity_id={eid}', [
                'id' => $op['operation_id'],
                'type' => $op['entity_type'],
                'eid' => $op['entity_id'],
            ]);
            throw $exc;
        }

        $this->saveOperationResult($companyId, $userId, $deviceId, $op, 'success', $ack);
        Log::channel('sync')->info(
            'PUSH success operation_id={id} entity_type={type} entity_id={eid} version={v}',
            [
                'id' => $op['operation_id'],
                'type' => $op['entity_type'],
                'eid' => $op['entity_id'],
                'v' => $ack['remote_version'],
            ],
        );

        return $ack;
    }

    public function raiseConflict(SyncEntity $entity, array $op): never
    {
        throw new ConflictException(
            "Remote version {$entity->version} > base {$op['base_version']}",
            $op['entity_type'],
            (string) $op['entity_id'],
            (int) $entity->version,
            (int) $op['base_version'],
            $entity->payload ?? [],
            $entity->updated_at?->toIso8601String(),
            $op['operation_id'] ?? null,
        );
    }

    public function applyMutation(string $companyId, array $op): array
    {
        $existing = $this->getEntity($companyId, $op['entity_type'], $op['entity_id'], true);
        $now = CarbonImmutable::now('UTC');

        // Enforce posted journal entry immutability
        if ($existing !== null && $op['entity_type'] === 'journal_entry') {
            $payload = $existing->payload ?? [];
            $isPosted = ($payload['isPosted'] ?? false) === true || ($payload['status'] ?? '') === 'posted';
            if ($isPosted && ($op['type'] === 'update' || $op['type'] === 'delete')) {
                throw new ValidationAppException('Posted journal entries are immutable and cannot be updated or deleted.');
            }
        }

        if ($op['type'] === 'create') {
            if ($existing !== null) {
                return $this->ackFromEntity($existing, $op['operation_id']);
            }
            $version = ((int) ($op['base_version'] ?: 0)) + 1;
            $payload = $this->payloadForStore($op, false, $version, $now, null);
            $entity = SyncEntity::query()->create([
                'company_id' => $companyId,
                'entity_type' => $op['entity_type'],
                'entity_uuid' => $op['entity_id'],
                'version' => $version,
                'payload' => $payload,
                'deleted_at' => null,
            ]);
            $this->recordChange($companyId, $entity, 'create');

            return $this->ackFromEntity($entity, $op['operation_id']);
        }

        if ($existing !== null && (int) $existing->version > (int) $op['base_version']) {
            $this->raiseConflict($existing, $op);
        }

        if ($existing === null) {
            if ($op['type'] === 'delete') {
                $version = ((int) ($op['base_version'] ?: 0)) + 1;
                $payload = $this->payloadForStore($op, true, $version, $now, $now);
                $entity = SyncEntity::query()->create([
                    'company_id' => $companyId,
                    'entity_type' => $op['entity_type'],
                    'entity_uuid' => $op['entity_id'],
                    'version' => $version,
                    'payload' => $payload,
                    'deleted_at' => $now,
                ]);
                $this->recordChange($companyId, $entity, 'delete');

                return $this->ackFromEntity($entity, $op['operation_id']);
            }
            if ($op['type'] === 'update') {
                $version = ((int) ($op['base_version'] ?: 0)) + 1;
                $payload = $this->payloadForStore($op, false, $version, $now, null);
                $entity = SyncEntity::query()->create([
                    'company_id' => $companyId,
                    'entity_type' => $op['entity_type'],
                    'entity_uuid' => $op['entity_id'],
                    'version' => $version,
                    'payload' => $payload,
                    'deleted_at' => null,
                ]);
                $this->recordChange($companyId, $entity, 'create');
                Log::channel('sync')->info(
                    'PUSH upsert-as-create operation_id={id} entity_type={type} entity_id={eid} version={v}',
                    [
                        'id' => $op['operation_id'],
                        'type' => $op['entity_type'],
                        'eid' => $op['entity_id'],
                        'v' => $version,
                    ],
                );

                return $this->ackFromEntity($entity, $op['operation_id']);
            }
            throw new NotFoundException(
                "Entity {$op['entity_type']}/{$op['entity_id']} not found for {$op['type']}"
            );
        }

        $nextVersion = (int) $existing->version + 1;
        $deleted = $op['type'] === 'delete';
        $deletedAt = $deleted ? $now : null;
        $payload = $this->payloadForStore($op, $deleted, $nextVersion, $now, $deletedAt);
        $existing->version = $nextVersion;
        $existing->payload = $payload;
        $existing->updated_at = $now;
        if ($deleted) {
            $existing->deleted_at = $deletedAt;
        } elseif ($existing->deleted_at !== null) {
            $existing->deleted_at = null;
            unset($payload['deleted'], $payload['deletedAt']);
            $existing->payload = $payload;
        }
        $existing->save();
        $this->recordChange($companyId, $existing, $op['type']);

        return $this->ackFromEntity($existing, $op['operation_id']);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: bool}
     */
    public function pull(
        string $companyId,
        ?string $entityType,
        ?int $cursor,
        ?CarbonImmutable $since,
        int $limit,
    ): array {
        $this->ensureCompany($companyId);
        if ($entityType !== null && $entityType !== '' && ! SupportedEntities::isSupported($entityType)) {
            throw new ValidationAppException(
                'Unsupported entity_type: '.$entityType,
                ['supported' => SupportedEntities::sorted()],
            );
        }

        Log::channel('sync')->info('PULL requested company={c} entity_type={t} cursor={cur} since={s} limit={l}', [
            'c' => $companyId,
            't' => $entityType,
            'cur' => $cursor,
            's' => $since?->toIso8601String(),
            'l' => $limit,
        ]);

        $this->backfillSyncChangesFromEntities($companyId);

        $query = SyncChange::query()->where('company_id', $companyId);
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        if ($cursor !== null) {
            $query->where('sequence', '>', $cursor);
        } elseif ($since !== null) {
            $query->where('created_at', '>', $since);
        }
        $rows = $query->orderBy('sequence')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $changes = $rows->map(function (SyncChange $row) {
            return [
                'entity_id' => (string) $row->entity_uuid,
                'entity_type' => $row->entity_type,
                'version' => (int) $row->version,
                'updated_at' => $row->created_at?->toIso8601String(),
                'payload' => $row->payload ?? [],
                'deleted' => (bool) $row->deleted,
                'sequence' => (int) $row->sequence,
                'operation' => $row->operation,
            ];
        })->values()->all();

        if ($rows->isNotEmpty()) {
            $nextCursor = (int) $rows->last()->sequence;
        } elseif ($cursor !== null) {
            $nextCursor = $cursor;
        } else {
            $seq = SyncSequence::query()->find($companyId);
            $nextCursor = ($seq && $seq->next_value > 1) ? ((int) $seq->next_value - 1) : 0;
        }

        Log::channel('sync')->info('PULL returned n={n} next_cursor={c} has_more={h}', [
            'n' => count($changes),
            'c' => $nextCursor,
            'h' => $hasMore,
        ]);

        return [$changes, $nextCursor, $hasMore];
    }

    public function getMeta(string $companyId, string $entityType, string $entityId): ?SyncEntity
    {
        $this->ensureCompany($companyId);
        if (! SupportedEntities::isSupported($entityType)) {
            throw new ValidationAppException('Unsupported entity_type: '.$entityType);
        }

        return $this->getEntity($companyId, $entityType, $entityId);
    }

    public function databaseOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function backfillSyncChangesFromEntities(string $companyId): void
    {
        $existingTracked = SyncChange::query()
            ->where('company_id', $companyId)
            ->select(['entity_type', 'entity_uuid'])
            ->get()
            ->map(fn ($row) => $row->entity_type . ':' . $row->entity_uuid)
            ->toBase()
            ->flip();

        $entities = SyncEntity::query()->where('company_id', $companyId)->get();
        $missingCount = 0;

        foreach ($entities as $entity) {
            $key = $entity->entity_type . ':' . $entity->entity_uuid;
            if (! isset($existingTracked[$key])) {
                $this->recordChange($companyId, $entity, 'create');
                $missingCount++;
            }
        }

        if ($missingCount > 0) {
            Log::channel('sync')->info('Backfilled {count} missing sync_changes rows for company={c}', [
                'count' => $missingCount,
                'c' => $companyId,
            ]);
        }
    }

    private function epochMs(CarbonImmutable $dt): int
    {
        return (int) round(((float) $dt->format('U.u')) * 1000);
    }
}
