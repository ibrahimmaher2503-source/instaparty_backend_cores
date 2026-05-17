<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class UnbalancedTransactionException extends RuntimeException
{
    public function __construct(int $totalDebits, int $totalCredits)
    {
        parent::__construct(
            "Transaction entries are unbalanced: debits={$totalDebits}, credits={$totalCredits}. They must be equal."
        );
    }
}
