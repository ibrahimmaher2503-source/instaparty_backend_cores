<?php

declare(strict_types=1);

namespace App\Modules\Tax\Application\Actions;

use App\Modules\Tax\Domain\Contracts\TaxRateRepository;
use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use App\Modules\Tax\Domain\Models\TaxRate;

class ResolveTaxRateForBookingAction
{
    public function __construct(
        private readonly TaxRateRepository $repository,
    ) {}

    /**
     * Resolve the most-specific active tax rate for the given product type.
     * Product-type-specific match beats a null product_types (applies-to-all) entry.
     */
    public function execute(string $productType): ?TaxRate
    {
        $rates = $this->repository->activeForDate(now())
            ->filter(fn (TaxRate $r) => in_array($r->applies_to, [TaxAppliesTo::All, TaxAppliesTo::Customer], true));

        // Most-specific: rate whose product_types array includes this type
        $specific = $rates->first(
            fn (TaxRate $r) => is_array($r->product_types) && in_array($productType, $r->product_types, true)
        );

        if ($specific) {
            return $specific;
        }

        // Fallback: rate that applies to all product types (null product_types)
        return $rates->first(fn (TaxRate $r) => $r->product_types === null);
    }
}
