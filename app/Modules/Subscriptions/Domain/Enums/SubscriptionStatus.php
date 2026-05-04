<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

enum SubscriptionStatus: string
{
    case Active     = 'active';
    case PastDue    = 'past_due';
    case Cancelled  = 'cancelled';
    case Expired    = 'expired';
    case Superseded = 'superseded';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Expired, self::Superseded => true,
            default => false,
        };
    }

    public function isActiveForGating(): bool
    {
        return match ($this) {
            self::Active, self::PastDue => true,
            default => false,
        };
    }
}
