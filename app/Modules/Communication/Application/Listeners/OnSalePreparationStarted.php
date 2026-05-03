<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnSalePreparationStarted implements ShouldQueue
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

        $this->dispatcher->execute(new DispatchNotificationDTO(
            eventKey: 'sale.preparation_started',
            channel: NotificationChannel::Push,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::Booking,
            userId: $event->customerId,
            context: $context,
            referenceType: 'booking',
            referenceId: $event->bookingId ?? null,
        ));
    }
}
