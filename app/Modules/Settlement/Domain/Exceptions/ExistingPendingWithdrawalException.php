<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

class ExistingPendingWithdrawalException extends RuntimeException
{
    public static function make(string $existingPublicId): self
    {
        return new self(
            "You already have a pending withdrawal request ({$existingPublicId}). Wait until it is processed before requesting another."
        );
    }
}
