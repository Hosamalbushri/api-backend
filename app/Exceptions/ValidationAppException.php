<?php

namespace App\Exceptions;

class ValidationAppException extends AppException
{
    public function __construct(string $message, array $details = [])
    {
        parent::__construct('validation_error', $message, 422, $details);
    }
}
