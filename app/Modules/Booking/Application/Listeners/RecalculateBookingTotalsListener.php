<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use Illuminate\Support\Facades\DB;

class RecalculateBookingTotalsListener
{
    public function handle(BookingItemAdded|BookingItemRemoved $event): void
    {
        $bookingVendorId = $event instanceof BookingItemAdded
            ? $event->item->booking_vendor_id
            : $event->bookingVendorId;

        $bookingId = $event instanceof BookingItemAdded
            ? ($event->item->bookingVendor ?? $event->item->load('bookingVendor')->bookingVendor)->booking_id
            : $event->bookingId;

        // Update booking_vendor subtotal
        DB::statement('
            UPDATE booking_vendors
            SET subtotal_minor = (
                SELECT COALESCE(SUM(line_total_minor), 0)
                FROM booking_items
                WHERE booking_vendor_id = booking_vendors.id
            )
            WHERE id = ?
        ', [$bookingVendorId]);

        // Update booking totals
        DB::statement('
            UPDATE bookings
            SET subtotal_minor = (
                    SELECT COALESCE(SUM(subtotal_minor), 0)
                    FROM booking_vendors
                    WHERE booking_id = bookings.id
                ),
                delivery_total_minor = (
                    SELECT COALESCE(SUM(delivery_fee_minor), 0)
                    FROM booking_vendors
                    WHERE booking_id = bookings.id
                ),
                total_minor = (
                    SELECT COALESCE(SUM(subtotal_minor), 0)
                    FROM booking_vendors
                    WHERE booking_id = bookings.id
                ) + (
                    SELECT COALESCE(SUM(delivery_fee_minor), 0)
                    FROM booking_vendors
                    WHERE booking_id = bookings.id
                ) - discount_total_minor
            WHERE id = ?
        ', [$bookingId]);
    }
}
