<?php

namespace NexaBiz\Audit\Services;

use Carbon\CarbonImmutable;
use NexaBiz\Audit\Contracts\AuditWriter;
use NexaBiz\Audit\Models\AuditLog;

class AuditService implements AuditWriter
{
    public function write(
        string $action,
        ?string $userId = null,
        ?string $companyId = null,
        ?string $deviceId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'device_id' => $deviceId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ?? [],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 512) : null,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
