<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum ProductType: string
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Rental => __('shared.product_type.rental'),
            self::Sale => __('shared.product_type.sale'),
            self::Digital => __('shared.product_type.digital'),
        };
    }
}
