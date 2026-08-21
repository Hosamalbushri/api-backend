<?php

namespace App\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(string $message = 'Not found', array $details = [])
    {
        parent::__construct('not_found', $message, 404, $details);
    }
}
