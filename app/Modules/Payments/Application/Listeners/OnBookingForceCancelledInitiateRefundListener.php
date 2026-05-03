<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingForceCancelled;

class OnBookingForceCancelledInitiateRefundListener
{
    public function handle(BookingForceCancelled $event): void
    {
        // TODO: dispatch InitiateRefundJob or call refund service
        // Refund logic per product type handled by RefundPolicyService
        // This is a stub — full refund implementation is in the Payments module
    }
}
