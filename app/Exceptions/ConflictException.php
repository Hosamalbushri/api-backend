<?php

namespace App\Exceptions;

class ConflictException extends AppException
{
    public function __construct(
        string $message,
        string $entityType,
        string $entityId,
        int $serverVersion,
        int $clientBaseVersion,
        array $serverRecord,
        ?string $serverUpdatedAt = null,
        ?string $operationId = null,
        array $conflictFields = [],
    ) {
        parent::__construct('conflict', $message, 409, [
            'status' => 'conflict',
            'operation_id' => $operationId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'base_version' => $clientBaseVersion,
            'server_version' => $serverVersion,
            'client_base_version' => $clientBaseVersion,
            'server_record' => $serverRecord,
            'server_payload' => $serverRecord,
            'server_updated_at' => $serverUpdatedAt,
            'conflict_fields' => $conflictFields,
            'resolution_required' => true,
        ]);
    }
}
