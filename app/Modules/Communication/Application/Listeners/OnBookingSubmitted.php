<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingSubmitted implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $context = [
            'booking_id' => $event->bookingPublicId ?? '',
            'customer_name' => $event->customerName ?? '',
            'vendor_name' => $event->vendorName ?? '',
            'event_date' => $event->eventDate ?? '',
        ];

        // Customer: push + email
        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.submitted',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Booking,
                userId: $event->customerId,
                context: $context,
                referenceType: 'booking',
                referenceId: $event->bookingId ?? null,
            ));
        }

        // Vendor: push + sms
        foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.submitted',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Booking,
                userId: $event->vendorUserId,
                context: $context,
                referenceType: 'booking',
                referenceId: $event->bookingId ?? null,
            ));
        }
    }
}
