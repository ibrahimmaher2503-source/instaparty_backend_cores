<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnPaymentCaptured implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $customer = DB::table('bookings')
            ->join('customer_profiles', 'bookings.customer_profile_id', '=', 'customer_profiles.id')
            ->where('bookings.id', $event->bookingId)
            ->select('customer_profiles.user_id as user_id', 'bookings.public_id as booking_public_id')
            ->first();

        if ($customer === null) {
            return;
        }

        $amount = number_format($event->amountMinor / 100, 2).' '.$event->amountCurrency;

        $context = [
            'booking_id' => $customer->booking_public_id,
            'amount' => $amount,
        ];

        // Customer: push + email
        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'payment.captured',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Payment,
                userId: $customer->user_id,
                context: $context,
                referenceType: 'payment',
                referenceId: $event->paymentId,
            ));
        }

        // Vendor: push only — one notification per vendor on the booking
        $vendorUsers = DB::table('booking_vendors')
            ->join('vendor_profiles', 'booking_vendors.vendor_profile_id', '=', 'vendor_profiles.id')
            ->where('booking_vendors.booking_id', $event->bookingId)
            ->select('vendor_profiles.user_id')
            ->get();

        foreach ($vendorUsers as $vendorUser) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'payment.captured',
                channel: NotificationChannel::Push,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Payment,
                userId: $vendorUser->user_id,
                context: $context,
                referenceType: 'payment',
                referenceId: $event->paymentId,
            ));
        }
    }
}
