<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\AdminSuggestedAlternativeVendors;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnAdminSuggestedAlternativeVendorsNotifyCustomer implements ShouldQueue
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(AdminSuggestedAlternativeVendors $event): void
    {
        $booking = Booking::find($event->bookingId);
        if (! $booking) {
            return;
        }

        // Include candidate vendor public_ids in payload (never internal IDs)
        $vendorPublicIds = VendorProfile::whereIn('id', $event->vendorProfileIds)
            ->pluck('public_id')
            ->toArray();

        $this->dispatcher->dispatch(
            'booking.alternatives.suggested',
            $booking->customer_id,
            NotificationAudience::Customer,
            [
                'booking_id'        => $booking->id,
                'vendor_public_ids' => $vendorPublicIds,
                'reason'            => $event->reason,
            ],
            Booking::class,
            $event->bookingId,
        );
    }
}
