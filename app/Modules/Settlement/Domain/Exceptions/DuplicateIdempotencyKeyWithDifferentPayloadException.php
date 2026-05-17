<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class DuplicateIdempotencyKeyWithDifferentPayloadException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct(
            "Idempotency key '{$key}' was already used with a different payload. Reuse with the same payload to replay the original result."
        );
    }
}
