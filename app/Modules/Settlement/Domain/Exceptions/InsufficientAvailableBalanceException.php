<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class InsufficientAvailableBalanceException extends RuntimeException
{
    public function __construct(int $walletId, int $available, int $requested)
    {
        parent::__construct(
            "Wallet {$walletId} has only {$available} piastres available but {$requested} piastres were requested."
        );
    }
}
