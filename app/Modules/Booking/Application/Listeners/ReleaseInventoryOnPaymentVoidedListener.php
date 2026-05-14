<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use Illuminate\Support\Facades\DB;

class ReleaseInventoryOnPaymentVoidedListener
{
    /**
     * Handles both PaymentVoided and PaymentAbandoned events.
     * Both carry a bookingId and signal that the payment will not complete,
     * so any held inventory reservations should be released.
     */
    public function handle(object $event): void
    {
        DB::table('service_inventory_reservations')
            ->join('booking_items', 'booking_items.id', '=', 'service_inventory_reservations.booking_item_id')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->where('booking_vendors.booking_id', $event->bookingId)
            ->where('service_inventory_reservations.status', 'held')
            ->update([
                'service_inventory_reservations.status' => 'released',
                'service_inventory_reservations.released_at' => now(),
                'service_inventory_reservations.release_reason' => 'payment_voided_or_abandoned',
                'service_inventory_reservations.updated_at' => now(),
            ]);
    }
}
