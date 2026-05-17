<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingVendorTimedOut;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingVendorTimedOutNotifyVendor implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(BookingVendorTimedOut $event): void
    {
        $bookingVendor = BookingVendor::find($event->bookingVendorId);
        if (! $bookingVendor) {
            return;
        }

        $vendorUserId = $bookingVendor->vendor?->user_id;
        if (! $vendorUserId) {
            return;
        }

        $this->dispatcher->dispatch(
            'booking.vendor.timed_out_by_admin',
            $vendorUserId,
            NotificationAudience::Vendor,
            ['booking_id' => $event->bookingId, 'reason' => $event->reason],
            BookingVendor::class,
            $event->bookingVendorId,
        );
    }
}
