<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ProductType: string
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Rental => __('catalog.product_type_rental'),
            self::Sale => __('catalog.product_type_sale'),
            self::Digital => __('catalog.product_type_digital'),
        };
    }
}
