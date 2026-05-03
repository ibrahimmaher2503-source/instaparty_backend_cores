<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

interface BookingDiscountWriter
{
    /**
     * Applies a loyalty discount line to a draft booking.
     * Returns the updated booking total in minor units.
     * Throws if discount would push total below zero.
     */
    public function applyLoyaltyDiscount(int $bookingId, int $discountMinor, string $currency, string $redemptionPublicId): int;

    /**
     * Removes a loyalty discount line from a booking (on void/reversal).
     */
    public function removeLoyaltyDiscount(int $bookingId, string $redemptionPublicId): void;
}
