<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RemoveItemFromBookingAction
{
    public function execute(int $bookingId, int $customerId, string $itemPublicId): array
    {
        return DB::transaction(function () use ($bookingId, $customerId, $itemPublicId): array {
            $booking = Booking::find($bookingId);

            if ($booking === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Booking not found');
            }
            if ($booking->customer_id !== $customerId) {
                $this->abortWith(Response::HTTP_FORBIDDEN, 'Forbidden');
            }
            if ($booking->lifecycle_status !== LifecycleStatus::Draft) {
                $this->abortWith(Response::HTTP_CONFLICT, 'Booking is not in draft status');
            }

            $item = BookingItem::where('public_id', $itemPublicId)
                ->whereHas('bookingVendor', fn ($q) => $q->where('booking_id', $bookingId))
                ->first();

            if ($item === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Item not found');
            }

            $bookingVendorId = $item->booking_vendor_id;
            $removedLineTotal = $item->line_total_minor;

            // Release the inventory reservation
            DB::table('service_inventory_reservations')
                ->where('booking_item_id', $item->id)
                ->where('status', ReservationStatus::Held->value)
                ->update([
                    'status' => ReservationStatus::Released->value,
                    'updated_at' => now(),
                ]);

            $item->delete();

            // Remove booking_vendor if no items remain
            $remainingItems = BookingItem::where('booking_vendor_id', $bookingVendorId)->count();
            if ($remainingItems === 0) {
                BookingVendor::destroy($bookingVendorId);
            }

            DB::afterCommit(fn () => event(new BookingItemRemoved($bookingVendorId, $bookingId, $removedLineTotal)));

            $booking->refresh();

            return [
                'booking_public_id' => $booking->public_id,
                'removed_item_public_id' => $itemPublicId,
                'booking_total_minor' => $booking->total_minor,
                'booking_currency' => $booking->total_currency ?? 'EGP',
                'vendors' => [],
            ];
        });
    }

    private function abortWith(int $status, string $message): never
    {
        throw new HttpResponseException(
            response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['message' => $message]], $status)
        );
    }
}
