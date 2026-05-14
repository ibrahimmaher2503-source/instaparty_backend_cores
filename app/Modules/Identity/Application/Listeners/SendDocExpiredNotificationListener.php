<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDocExpiredNotificationListener implements ShouldQueue
{
    public function __construct(private readonly DispatchNotificationAction $dispatchNotification) {}

    public function handle(VendorAutoSuspended $event): void
    {
        $this->dispatchNotification->execute(new DispatchNotificationDTO(
            eventKey: 'vendor.doc_expired',
            channel: NotificationChannel::InApp,
            audience: NotificationAudience::Vendor,
            eventCategory: EventCategory::System,
            userId: $event->vendorProfile->user_id,
            context: [
                'doc_type' => __('identity.document_type.'.$event->document->doc_type->value),
                'expiry_date' => $event->document->expires_at->format('Y-m-d'),
                'vendor_name' => $event->vendorProfile->getTranslation('business_name', 'en'),
            ]
        ));
    }
}
