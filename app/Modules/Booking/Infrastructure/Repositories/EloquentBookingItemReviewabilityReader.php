<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Reviews\Domain\Contracts\BookingItemReviewabilityReader;
use Illuminate\Support\Facades\DB;

class EloquentBookingItemReviewabilityReader implements BookingItemReviewabilityReader
{
    public function isReviewable(string $bookingItemPublicId, int $userId): bool
    {
        return $this->resolveReviewableContext($bookingItemPublicId, $userId) !== null;
    }

    public function resolveReviewableContext(string $bookingItemPublicId, int $userId): ?array
    {
        $row = DB::table('booking_items')
            ->join('booking_vendors', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->join('bookings', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->where('booking_items.public_id', $bookingItemPublicId)
            ->where('bookings.customer_id', $userId)
            ->where('booking_items.item_status', 'completed')
            ->whereNull('bookings.deleted_at')
            ->select([
                'booking_items.id as booking_item_id',
                'booking_items.service_id',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'booking_item_id' => (int) $row->booking_item_id,
            'service_id' => (int) $row->service_id,
        ];
    }
}
