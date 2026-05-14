<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Shared\Domain\Events\ChangeRequestResubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchServiceResubmittedNotificationListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ChangeRequestResubmitted $event): void
    {
        $changeRequest = $event->changeRequest;
        $service = $event->subject;

        if (! $service instanceof Service) {
            return;
        }

        $vendor = $service->vendor;
        if (! $vendor) {
            return;
        }

        $context = [
            'service_name' => $service->getTranslation('name', app()->getLocale(), false) ?: '',
            'service_type' => $service->product_type->label(),
            'cycle_number' => $changeRequest->cycle_number,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'service.resubmitted',
                channel: $channel,
                audience: NotificationAudience::Admin,
                eventCategory: EventCategory::System,
                userId: null,
                context: $context,
                referenceType: 'change_request',
                referenceId: $changeRequest->id,
            ));
        }
    }
}
