<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use Illuminate\Support\Str;

class WriteInitialBookingSnapshotListener
{
    public function handle(BookingDraftCreated $event): void
    {
        $booking = $event->booking->load('address');

        BookingSnapshot::create([
            'public_id' => (string) Str::ulid(),
            'booking_id' => $booking->id,
            'version' => 1,
            'trigger_kind' => 'booking_created',
            'snapshot' => [
                'booking' => [
                    'public_id' => $booking->public_id,
                    'lifecycle_status' => $booking->lifecycle_status->value,
                    'event_starts_at' => $booking->event_starts_at?->toIso8601String(),
                    'total_minor' => 0,
                ],
                'vendors' => [],
                'items' => [],
                'address' => $booking->address ? [
                    'city_id' => $booking->address->city_id,
                    'address_line' => $booking->address->address_line,
                ] : null,
            ],
        ]);
    }
}
