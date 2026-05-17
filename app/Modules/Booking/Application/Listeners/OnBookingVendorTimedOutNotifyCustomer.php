<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingVendorTimedOut;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingVendorTimedOutNotifyCustomer implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(BookingVendorTimedOut $event): void
    {
        $booking = Booking::find($event->bookingId);
        if (! $booking) {
            return;
        }

        $customerId = $booking->customer_id;
        if (! $customerId) {
            return;
        }

        $this->dispatcher->dispatch(
            'booking.vendor.timed_out_by_admin.customer',
            $customerId,
            NotificationAudience::Customer,
            ['booking_id' => $event->bookingId, 'reason' => $event->reason],
            Booking::class,
            $event->bookingId,
        );
    }
}
