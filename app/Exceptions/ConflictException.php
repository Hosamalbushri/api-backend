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
    ) {
        parent::__construct('conflict', $message, 409, [
            'status' => 'conflict',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'server_version' => $serverVersion,
            'client_base_version' => $clientBaseVersion,
            'server_record' => $serverRecord,
            'server_updated_at' => $serverUpdatedAt,
        ]);
    }
}
