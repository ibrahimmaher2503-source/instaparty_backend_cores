<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ServiceFieldClassification: string
{
    case Shared = 'shared';
    case Rental = 'rental';
    case Sale = 'sale';
    case Digital = 'digital';
    case Media = 'media';
    case Availability = 'availability';
    case PricingTier = 'pricing_tier';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Shared => 'gray',
            self::Rental => 'warning',
            self::Sale => 'success',
            self::Digital => 'info',
            self::Media => 'primary',
            self::Availability => 'secondary',
            self::PricingTier => 'danger',
        };
    }
}
