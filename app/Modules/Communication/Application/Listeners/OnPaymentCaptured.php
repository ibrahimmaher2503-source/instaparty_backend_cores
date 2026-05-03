<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnPaymentCaptured implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $context = [
            'booking_id' => $event->bookingPublicId ?? '',
            'amount' => $event->amountFormatted ?? '',
            'vendor_name' => $event->vendorName ?? '',
        ];

        // Customer: push + email
        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'payment.captured',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Payment,
                userId: $event->customerId,
                context: $context,
                referenceType: 'payment',
                referenceId: $event->paymentId ?? null,
            ));
        }

        // Vendor: push only
        $this->dispatcher->execute(new DispatchNotificationDTO(
            eventKey: 'payment.captured',
            channel: NotificationChannel::Push,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::Payment,
            userId: $event->vendorUserId,
            context: $context,
            referenceType: 'payment',
            referenceId: $event->paymentId ?? null,
        ));
    }
}
