<?php

declare(strict_types=1);

namespace App\Modules\Tax\Domain\Enums;

enum TaxAppliesTo: string
{
    case Customer = 'customer';
    case Vendor   = 'vendor';
    case All      = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Vendor   => 'Vendor',
            self::All      => 'All',
        };
    }
}
