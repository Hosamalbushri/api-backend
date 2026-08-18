<?php

namespace NexaBiz\Core\Exceptions;

class PermissionDeniedException extends AppException
{
    public function __construct(string $message = 'Permission denied', array $details = [])
    {
        parent::__construct('permission_denied', $message, 403, $details);
    }
}
