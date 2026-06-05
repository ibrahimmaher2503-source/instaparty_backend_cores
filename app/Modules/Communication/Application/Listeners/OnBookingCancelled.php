<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingCancelled implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('vendors.vendor');

        // When EVERY vendor rejected, the customer already received
        // booking.rejected per rejection — a booking.cancelled on top would be
        // noise, and the rejecting vendors need no notice of their own outcome.
        $allRejected = $booking->vendors->isNotEmpty()
            && $booking->vendors->every(
                fn ($vendor): bool => $vendor->sub_status === VendorSubStatus::Rejected
            );

        if ($allRejected) {
            return;
        }

        $context = [
            'booking_id' => $booking->public_id,
            'event_date' => $booking->event_starts_at?->format('Y-m-d') ?? '',
        ];

        if ($booking->customer_id !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.cancelled',
                    channel: $channel,
                    audience: NotificationAudience::Customer,
                    eventCategory: EventCategory::Booking,
                    userId: $booking->customer_id,
                    context: $context,
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }

        // Vendors still engaged with the booking learn it was cancelled.
        $engaged = [
            VendorSubStatus::Pending, VendorSubStatus::Accepted,
            VendorSubStatus::Modified, VendorSubStatus::InProgress,
        ];

        foreach ($booking->vendors as $bookingVendor) {
            $vendorUserId = $bookingVendor->vendor?->user_id;

            if ($vendorUserId === null || ! in_array($bookingVendor->sub_status, $engaged, true)) {
                continue;
            }

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.cancelled',
                channel: NotificationChannel::Push,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Booking,
                userId: $vendorUserId,
                context: $context,
                referenceType: 'booking',
                referenceId: $booking->id,
            ));
        }
    }
}
