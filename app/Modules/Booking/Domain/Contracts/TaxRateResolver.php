<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

interface TaxRateResolver
{
    /**
     * Returns the resolved VAT rate in basis points (e.g. 1400 = 14%)
     * for a given product type. Returns 0 if no active rate applies.
     */
    public function resolveRateBpsForProductType(string $productType): int;
}
