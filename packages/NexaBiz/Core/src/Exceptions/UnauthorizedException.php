<?php

namespace NexaBiz\Core\Exceptions;

class UnauthorizedException extends AppException
{
    public function __construct(string $message = 'Unauthorized', array $details = [])
    {
        parent::__construct('unauthorized', $message, 401, $details);
    }
}
