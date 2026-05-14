<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchVendorChangesRequestedNotificationListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ChangeRequestCreated $event): void
    {
        $changeRequest = $event->changeRequest;

        if ($changeRequest->subject_type === 'vendor') {
            $vendor = $changeRequest->subject;

            $context = [
                'vendor_name' => $vendor->getTranslation('business_name', app()->getLocale(), false) ?: '',
                'cycle_number' => $changeRequest->cycle_number,
                'items_count' => $changeRequest->items()->count(),
            ];

            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'vendor.changes_requested',
                    channel: $channel,
                    audience: NotificationAudience::Vendor,
                    eventCategory: EventCategory::System,
                    userId: $vendor->user_id,
                    context: $context,
                    referenceType: 'change_request',
                    referenceId: $changeRequest->id,
                ));
            }
        } elseif ($changeRequest->subject_type === 'service') {
            $service = $changeRequest->subject;
            $vendor = $service->vendor;

            $context = [
                'vendor_name' => $vendor->getTranslation('business_name', app()->getLocale(), false) ?: '',
                'service_name' => $service->getTranslation('name', app()->getLocale(), false) ?: '',
                'cycle_number' => $changeRequest->cycle_number,
                'items_count' => $changeRequest->items()->count(),
            ];

            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'vendor.service_changes_requested',
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
}
