<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\AlternativeVendorProposed;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnAlternativeVendorProposed implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(AlternativeVendorProposed $event): void
    {
        $booking = $event->booking;

        if ($booking->customer_id === null) {
            return;
        }

        $context = [
            'booking_id' => $booking->public_id,
            'intervention_id' => $event->intervention->public_id,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.alternative',
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
}
