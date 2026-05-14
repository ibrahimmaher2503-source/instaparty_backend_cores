<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Loyalty;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
use RuntimeException;

final class EloquentBookingItemNetAmountReader implements BookingItemNetAmountReader
{
    /**
     * Net paid amount in minor units for a booking_item.
     *
     * The bookings schema currently has no item-level refund linkage column
     * (refunds live at booking level via amount_paid_minor). For Phase 1 we
     * treat `line_total_minor` as the net amount for the item — when the
     * Refunds module gains item-level granularity this method should subtract
     * the item's refunded portion.
     */
    public function netPaidMinorFor(int $bookingItemId): int
    {
        $item = BookingItem::query()->find($bookingItemId);

        if ($item === null) {
            throw new RuntimeException("Booking item {$bookingItemId} not found.");
        }

        return (int) $item->line_total_minor;
    }

    public function vendorProfileIdFor(int $bookingItemId): int
    {
        $item = BookingItem::query()
            ->with('bookingVendor:id,vendor_profile_id')
            ->find($bookingItemId);

        if ($item === null) {
            throw new RuntimeException("Booking item {$bookingItemId} not found.");
        }

        $vendor = $item->bookingVendor;
        if ($vendor === null) {
            throw new RuntimeException("Booking item {$bookingItemId} has no booking vendor.");
        }

        return (int) $vendor->vendor_profile_id;
    }

    public function productTypeFor(int $bookingItemId): string
    {
        $item = BookingItem::query()->find($bookingItemId);

        if ($item === null) {
            throw new RuntimeException("Booking item {$bookingItemId} not found.");
        }

        return $item->product_type->value;
    }
}
