<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Support\Collection;

interface BookingHistoryReader
{
    /**
     * Return a deduplicated Collection of user_id integers for customers who
     * match ALL provided filters. Null filters are ignored (not applied).
     * Excludes users with status 'suspended' or 'banned'.
     * Excludes vendor users (only customer_profiles rows included).
     *
     * @return Collection<int, int>
     */
    public function customersWithBookingsMatching(
        ?ProductType $productType,
        ?int $withinDays,
        ?int $governorateId,
        ?string $preferredLocale,
    ): Collection;
}
