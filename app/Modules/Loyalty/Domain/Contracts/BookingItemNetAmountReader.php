<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

interface BookingItemNetAmountReader
{
    /**
     * Returns net paid amount in minor units (piastres) for a booking_item,
     * after refunds and excluding platform commission.
     */
    public function netPaidMinorFor(int $bookingItemId): int;

    public function vendorProfileIdFor(int $bookingItemId): int;

    public function productTypeFor(int $bookingItemId): string;
}
