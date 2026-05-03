<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnRentalDeliveryScheduled implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $context = [
            'booking_id' => $event->bookingPublicId ?? '',
            'delivery_date' => $event->deliveryDate ?? '',
            'time_window' => $event->timeWindow ?? '',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'rental.delivery_scheduled',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Booking,
                userId: $event->customerId,
                context: $context,
                referenceType: 'booking',
                referenceId: $event->bookingId ?? null,
            ));
        }
    }
}
