<?php

namespace App\Exceptions;

class TooManyRequestsException extends AppException
{
    public function __construct(string $message = 'Too many requests', int $retryAfter = 60)
    {
        parent::__construct('rate_limited', $message, 429, ['retry_after' => $retryAfter], $retryAfter);
    }
}
