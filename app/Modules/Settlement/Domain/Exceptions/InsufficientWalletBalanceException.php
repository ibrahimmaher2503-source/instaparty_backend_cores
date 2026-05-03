<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public static function make(int $availableMinor, int $requestedMinor, string $currency): self
    {
        $available = number_format($availableMinor / 100, 2);
        $requested = number_format($requestedMinor / 100, 2);

        return new self(
            "Insufficient wallet balance. Available: {$available} {$currency}, requested: {$requested} {$currency}."
        );
    }
}
