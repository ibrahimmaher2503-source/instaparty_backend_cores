<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum HoldType: string
{
    case Cart = 'cart';
    case Payment = 'payment';

    public function ttlMinutes(): int
    {
        return match ($this) {
            self::Cart    => 15,
            self::Payment => 1440,
        };
    }
}
