<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorProfileResubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchVendorResubmittedNotificationListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorProfileResubmitted $event): void
    {
        $vendor = $event->vendorProfile;
        $changeRequest = $event->changeRequest;

        $context = [
            'vendor_name' => $vendor->getTranslation('business_name', app()->getLocale(), false) ?: '',
            'cycle_number' => $changeRequest->cycle_number,
            'items_count' => $changeRequest->items()->count(),
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.resubmitted',
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
