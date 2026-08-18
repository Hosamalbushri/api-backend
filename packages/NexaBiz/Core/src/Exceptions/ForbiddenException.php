<?php

namespace NexaBiz\Core\Exceptions;

class ForbiddenException extends AppException
{
    public function __construct(string $message = 'Forbidden', array $details = [])
    {
        parent::__construct('forbidden', $message, 403, $details);
    }
}
