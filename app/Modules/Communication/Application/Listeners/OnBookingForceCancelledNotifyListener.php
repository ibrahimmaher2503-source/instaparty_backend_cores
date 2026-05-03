<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingForceCancelled;

class OnBookingForceCancelledNotifyListener
{
    public function handle(BookingForceCancelled $event): void
    {
        // TODO: dispatch notifications to customer and vendor
        // Uses event key: booking.force_cancelled
        // This is a stub — full notification implementation in Communication module
    }
}
