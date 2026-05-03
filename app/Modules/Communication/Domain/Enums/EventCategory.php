<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum EventCategory: string
{
    case Booking = 'booking';
    case Marketing = 'marketing';
    case System = 'system';
    case Chat = 'chat';
    case Payment = 'payment';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Marketing => 'Marketing',
            self::System => 'System',
            self::Chat => 'Chat',
            self::Payment => 'Payment',
            self::Review => 'Review',
        };
    }

    public function canBeDisabled(): bool
    {
        return $this !== self::System;
    }
}
