<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Events\CustomerModificationDecided;
use App\Modules\Booking\Domain\Events\VendorAccepted;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WriteNegotiationSnapshotListener
{
    public function handle(
        BookingSubmittedToVendor|VendorAccepted|VendorModificationProposed|VendorRejected|CustomerModificationDecided|BookingConfirmed|BookingCancelled $event
    ): void {
        $booking = match (true) {
            $event instanceof BookingSubmittedToVendor => $event->booking,
            $event instanceof VendorAccepted => $event->bookingVendor->booking,
            $event instanceof VendorModificationProposed => $event->modification->bookingVendor?->booking,
            $event instanceof VendorRejected => $event->bookingVendor->booking,
            $event instanceof CustomerModificationDecided => $event->modification->bookingVendor?->booking,
            $event instanceof BookingConfirmed => $event->booking,
            $event instanceof BookingCancelled => $event->booking,
        };

        if ($booking === null) {
            return;
        }

        $triggerKind = match (true) {
            $event instanceof BookingSubmittedToVendor => 'booking_submitted',
            $event instanceof VendorAccepted => 'vendor_responded',
            $event instanceof VendorModificationProposed => 'vendor_responded',
            $event instanceof VendorRejected => 'vendor_responded',
            $event instanceof CustomerModificationDecided => 'vendor_responded',
            $event instanceof BookingConfirmed => 'booking_confirmed',
            $event instanceof BookingCancelled => 'booking_cancelled',
        };

        $latestVersion = DB::table('booking_snapshots')
            ->where('booking_id', $booking->id)
            ->max('version') ?? 0;

        $booking->load(['vendors.items', 'address']);

        BookingSnapshot::create([
            'public_id' => (string) Str::ulid(),
            'booking_id' => $booking->id,
            'version' => $latestVersion + 1,
            'trigger_kind' => $triggerKind,
            'snapshot' => $this->buildSnapshot($booking),
        ]);
    }

    /** @return array<string,mixed> */
    private function buildSnapshot(Booking $booking): array
    {
        return [
            'booking' => [
                'public_id' => $booking->public_id,
                'lifecycle_status' => $booking->getRawOriginal('lifecycle_status'),
                'total_minor' => $booking->total_minor,
                'currency' => $booking->total_currency,
            ],
            'vendors' => $booking->vendors->map(fn (BookingVendor $v) => [
                'public_id' => $v->public_id,
                'vendor_profile_id' => $v->vendor_profile_id,
                'sub_status' => $v->sub_status->value,
                'subtotal_minor' => $v->subtotal_minor,
            ])->all(),
            'items' => $booking->vendors->flatMap(fn (BookingVendor $v) => $v->items)->map(fn (BookingItem $i) => [
                'public_id' => $i->public_id,
                'product_type' => $i->product_type->value,
                'unit_price_minor' => $i->unit_price_minor,
                'quantity' => $i->quantity,
            ])->values()->all(),
        ];
    }
}
