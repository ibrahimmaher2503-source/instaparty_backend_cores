<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorRejected implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorRejected $event): void
    {
        $bookingVendor = $event->bookingVendor;
        $booking = $bookingVendor->booking;

        if ($booking === null || $booking->customer_id === null) {
            return;
        }

        $vendor = $bookingVendor->vendor;
        $vendorName = is_array($vendor?->business_name)
            ? ($vendor->business_name['en'] ?? '')
            : (string) ($vendor?->business_name ?? '');

        $reason = $bookingVendor->rejection_reason;

        $context = [
            'booking_id' => $booking->public_id,
            'vendor_name' => $vendorName,
            'rejection_reason' => is_array($reason) ? ($reason['en'] ?? $reason['ar'] ?? '') : '',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.rejected',
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
