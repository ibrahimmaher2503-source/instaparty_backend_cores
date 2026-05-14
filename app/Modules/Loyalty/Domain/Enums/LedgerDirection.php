<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Enums;

enum LedgerDirection: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Earn',
            self::Redeem => 'Redeem',
            self::Expire => 'Expire',
            self::Adjust => 'Adjust',
        };
    }
}
