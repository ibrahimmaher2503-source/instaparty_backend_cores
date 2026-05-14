<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchServiceChangesRequestedNotificationListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ChangeRequestCreated $event): void
    {
        $changeRequest = $event->changeRequest;

        if ($changeRequest->subject_type !== 'service') {
            return;
        }

        $service = $changeRequest->subject;

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
            'items_count' => $changeRequest->items()->count(),
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'service.changes_requested',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $vendor->user_id,
                context: $context,
                referenceType: 'change_request',
                referenceId: $changeRequest->id,
            ));
        }
    }
}
