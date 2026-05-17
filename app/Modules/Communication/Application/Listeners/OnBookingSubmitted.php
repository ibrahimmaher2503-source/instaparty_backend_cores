<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingSubmitted implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(BookingSubmittedToVendor $event): void
    {
        $booking = $event->booking;
        $bookingVendor = $event->bookingVendor;
        $vendor = $bookingVendor->vendor;
        $customer = $booking->customer;

        $customerName = is_array($customer?->name)
            ? ($customer->name['en'] ?? '')
            : (string) ($customer?->name ?? '');

        $vendorName = is_array($vendor?->business_name)
            ? ($vendor->business_name['en'] ?? '')
            : (string) ($vendor?->business_name ?? '');

        $context = [
            'booking_id'    => $booking->public_id,
            'customer_name' => $customerName,
            'vendor_name'   => $vendorName,
            'event_date'    => $booking->event_starts_at?->format('Y-m-d') ?? '',
        ];

        // Customer: push + email
        if ($booking->customer_id !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.submitted',
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

        // Vendor: push + sms
        $vendorUserId = $vendor?->user_id;
        if ($vendorUserId !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.submitted',
                    channel: $channel,
                    audience: NotificationAudience::Vendor,
                    eventCategory: EventCategory::Booking,
                    userId: $vendorUserId,
                    context: $context,
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }
    }
}
