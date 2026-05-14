<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case None = 'none';

    public function monthsToAdd(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
            self::None => 0,
        };
    }
}
