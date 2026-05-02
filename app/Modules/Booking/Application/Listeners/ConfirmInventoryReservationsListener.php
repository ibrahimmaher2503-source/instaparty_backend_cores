<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingConfirmed;
use Illuminate\Support\Facades\DB;

class ConfirmInventoryReservationsListener
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        DB::table('service_inventory_reservations')
            ->join('booking_items', 'booking_items.id', '=', 'service_inventory_reservations.booking_item_id')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->where('booking_vendors.booking_id', $booking->id)
            ->where('service_inventory_reservations.status', 'held')
            ->update([
                'service_inventory_reservations.status' => 'confirmed',
                'service_inventory_reservations.updated_at' => now(),
            ]);
    }
}
