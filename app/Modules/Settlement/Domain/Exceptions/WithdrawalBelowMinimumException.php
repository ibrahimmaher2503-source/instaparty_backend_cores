<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

use RuntimeException;

class WithdrawalBelowMinimumException extends RuntimeException
{
    public static function make(int $requestedMinor, int $minimumMinor, string $currency): self
    {
        $requested = number_format($requestedMinor / 100, 2);
        $minimum = number_format($minimumMinor / 100, 2);

        return new self(
            "Withdrawal amount ({$requested} {$currency}) is below the minimum of {$minimum} {$currency}."
        );
    }
}
