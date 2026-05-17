<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Catalog\Domain\Events\ServiceChangeRequestRejected;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchServiceChangeRejectedNotificationListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ServiceChangeRequestRejected $event): void
    {
        $changeRequest = $event->changeRequest;

        $service = $changeRequest->service;
        if (! $service) {
            return;
        }

        $context = [
            'service_name' => $service->getTranslation('name', app()->getLocale(), false) ?: '',
            'service_type' => $service->product_type->label(),
            'request_id'   => $changeRequest->public_id,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'service.change_request.rejected',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $changeRequest->submitted_by,
                context: $context,
                referenceType: 'change_request',
                referenceId: $changeRequest->id,
            ));
        }
    }
}
