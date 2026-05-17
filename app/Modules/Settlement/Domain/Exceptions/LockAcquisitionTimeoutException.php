<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class LockAcquisitionTimeoutException extends RuntimeException
{
    public function __construct(int $walletId)
    {
        parent::__construct(
            "Could not acquire lock on wallet {$walletId} within the wait window. The operation is retryable."
        );
    }
}
