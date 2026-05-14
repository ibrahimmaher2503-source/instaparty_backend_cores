<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Enums;

enum RuleKind: string
{
    case FirstBooking = 'first_booking';
    case CategoryBonus = 'category_bonus';
    case ThresholdBonus = 'threshold_bonus';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::FirstBooking => 'First booking bonus',
            self::CategoryBonus => 'Category bonus',
            self::ThresholdBonus => 'Threshold bonus',
            self::Referral => 'Referral bonus',
        };
    }
}
