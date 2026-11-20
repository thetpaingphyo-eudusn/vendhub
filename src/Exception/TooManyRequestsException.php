<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class TooManyRequestsException extends RuntimeException
{
    public function __construct(
        string $message = 'Too many attempts. Please try again later.',
        public readonly int $retryAfter = 60,
    ) {
        parent::__construct($message);
    }
}
