<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(public readonly string $key, string $message = 'Idempotency-Key has been reused with a different request payload.')
    {
        parent::__construct($message);
    }
}
