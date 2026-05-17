<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingChatResumed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

final class OnBookingChatResumedNotifyParties implements ShouldQueue
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(BookingChatResumed $event): void
    {
        $context = ['booking_id' => $event->bookingId, 'reason' => $event->reason];

        $booking = DB::table('bookings')->where('id', $event->bookingId)->first();
        if ($booking?->customer_id) {
            $this->dispatcher->dispatch(
                'booking.chat.resumed',
                (int) $booking->customer_id,
                NotificationAudience::Customer,
                $context,
                Booking::class,
                $event->bookingId,
            );
        }

        $vendorUserIds = DB::table('booking_vendors')
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'booking_vendors.vendor_profile_id')
            ->where('booking_vendors.booking_id', $event->bookingId)
            ->pluck('vendor_profiles.user_id');

        foreach ($vendorUserIds as $userId) {
            $this->dispatcher->dispatch(
                'booking.chat.resumed',
                (int) $userId,
                NotificationAudience::Vendor,
                $context,
                Booking::class,
                $event->bookingId,
            );
        }
    }
}
