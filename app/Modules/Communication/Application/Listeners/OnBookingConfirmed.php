<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingConfirmed implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $context = [
            'booking_id' => $event->bookingPublicId ?? '',
            'vendor_name' => $event->vendorName ?? '',
            'event_date' => $event->eventDate ?? '',
        ];

        // Customer: push + email + sms
        foreach ([NotificationChannel::Push, NotificationChannel::Email, NotificationChannel::Sms] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.confirmed',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Booking,
                userId: $event->customerId,
                context: $context,
                referenceType: 'booking',
                referenceId: $event->bookingId ?? null,
            ));
        }

        // Vendor: push only
        $this->dispatcher->execute(new DispatchNotificationDTO(
            eventKey: 'booking.confirmed',
            channel: NotificationChannel::Push,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::Booking,
            userId: $event->vendorUserId,
            context: $context,
            referenceType: 'booking',
            referenceId: $event->bookingId ?? null,
        ));
    }
}
