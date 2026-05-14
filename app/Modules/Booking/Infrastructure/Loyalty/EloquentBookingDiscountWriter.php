<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Loyalty;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentBookingDiscountWriter implements BookingDiscountWriter
{
    /**
     * Apply a loyalty discount to a booking by writing
     * `loyalty_redeemed_minor` + `loyalty_redemption_public_id` and recomputing
     * `total_minor = subtotal + delivery - discount_total - loyalty_redeemed`.
     */
    public function applyLoyaltyDiscount(int $bookingId, int $discountMinor, string $currency, string $redemptionPublicId): int
    {
        return DB::transaction(function () use ($bookingId, $discountMinor, $currency, $redemptionPublicId): int {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new RuntimeException("Booking {$bookingId} not found.");
            }

            $newTotal = (int) $booking->subtotal_minor
                + (int) $booking->delivery_total_minor
                - (int) $booking->discount_total_minor
                - $discountMinor;

            if ($newTotal < 0) {
                throw new DomainException('loyalty.discount_exceeds_total');
            }

            $booking->forceFill([
                'loyalty_redeemed_minor' => $discountMinor,
                'loyalty_redeemed_currency' => $currency,
                'loyalty_redemption_public_id' => $redemptionPublicId,
                'total_minor' => $newTotal,
                'total_currency' => $currency,
            ])->save();

            return $newTotal;
        });
    }

    public function removeLoyaltyDiscount(int $bookingId, string $redemptionPublicId): void
    {
        DB::transaction(function () use ($bookingId, $redemptionPublicId): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new RuntimeException("Booking {$bookingId} not found.");
            }

            // Idempotent: only clear if this redemption is the one currently applied.
            if ($booking->loyalty_redemption_public_id !== $redemptionPublicId) {
                return;
            }

            $currency = (string) ($booking->total_currency ?: 'EGP');
            $newTotal = (int) $booking->subtotal_minor
                + (int) $booking->delivery_total_minor
                - (int) $booking->discount_total_minor;

            $booking->forceFill([
                'loyalty_redeemed_minor' => 0,
                'loyalty_redeemed_currency' => $currency,
                'loyalty_redemption_public_id' => null,
                'total_minor' => max(0, $newTotal),
                'total_currency' => $currency,
            ])->save();
        });
    }
}
