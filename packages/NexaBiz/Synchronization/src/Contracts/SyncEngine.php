<?php

namespace NexaBiz\Synchronization\Contracts;

use Carbon\CarbonImmutable;
use NexaBiz\Synchronization\Models\SyncEntity;

interface SyncEngine
{
    /**
     * @param  array{operation_id: mixed, entity_type: mixed, entity_id: mixed, type: mixed, payload?: mixed, base_version?: mixed}  $op
     * @return array<string, mixed>
     */
    public function pushOperation(string $companyId, string $userId, string $deviceId, array $op): array;

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: bool}
     */
    public function pull(
        string $companyId,
        ?string $entityType,
        ?int $cursor,
        ?CarbonImmutable $since,
        int $limit,
    ): array;

    public function getMeta(string $companyId, string $entityType, string $entityId): ?SyncEntity;
}
