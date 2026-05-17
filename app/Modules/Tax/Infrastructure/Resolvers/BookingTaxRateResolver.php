<?php

declare(strict_types=1);

namespace App\Modules\Tax\Infrastructure\Resolvers;

use App\Modules\Booking\Domain\Contracts\TaxRateResolver;
use App\Modules\Tax\Application\Actions\ResolveTaxRateForBookingAction;

class BookingTaxRateResolver implements TaxRateResolver
{
    public function __construct(
        private readonly ResolveTaxRateForBookingAction $action,
    ) {}

    public function resolveRateBpsForProductType(string $productType): int
    {
        $taxRate = $this->action->execute($productType);

        return $taxRate?->rate_bps ?? 0;
    }
}
