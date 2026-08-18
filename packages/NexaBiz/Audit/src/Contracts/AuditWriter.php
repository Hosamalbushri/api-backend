<?php

namespace NexaBiz\Audit\Contracts;

interface AuditWriter
{
    /**
     * Persist an audit row. Never log secrets (passwords, tokens, JWTs).
     *
     * @param  array<string, mixed>|null  $metadata
     */
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
    ): void;
}
