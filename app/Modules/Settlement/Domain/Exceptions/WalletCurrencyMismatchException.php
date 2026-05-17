<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

final class WalletCurrencyMismatchException extends RuntimeException
{
    public function __construct(int $walletId, string $walletCurrency, string $transactionCurrency)
    {
        parent::__construct(
            "Wallet {$walletId} uses currency {$walletCurrency} but transaction specifies {$transactionCurrency}."
        );
    }
}
