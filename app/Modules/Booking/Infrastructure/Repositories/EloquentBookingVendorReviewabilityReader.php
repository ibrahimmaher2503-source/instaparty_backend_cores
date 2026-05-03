<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Reviews\Domain\Contracts\BookingVendorReviewabilityReader;
use Illuminate\Support\Facades\DB;

class EloquentBookingVendorReviewabilityReader implements BookingVendorReviewabilityReader
{
    public function isReviewable(string $bookingVendorPublicId, int $userId): bool
    {
        return $this->resolveReviewableContext($bookingVendorPublicId, $userId) !== null;
    }

    public function resolveReviewableContext(string $bookingVendorPublicId, int $userId): ?array
    {
        $row = DB::table('booking_vendors')
            ->join('bookings', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->where('booking_vendors.public_id', $bookingVendorPublicId)
            ->where('bookings.customer_id', $userId)
            ->whereNull('bookings.deleted_at')
            ->select([
                'booking_vendors.id as booking_vendor_id',
                'booking_vendors.vendor_profile_id',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $bookingVendorId = (int) $row->booking_vendor_id;

        // All items under this booking_vendor must be completed and non-deleted
        $totalItems = DB::table('booking_items')
            ->where('booking_vendor_id', $bookingVendorId)
            ->count();

        if ($totalItems === 0) {
            return null;
        }

        $completedItems = DB::table('booking_items')
            ->where('booking_vendor_id', $bookingVendorId)
            ->where('item_status', 'completed')
            ->count();

        if ($completedItems !== $totalItems) {
            return null;
        }

        return [
            'booking_vendor_id' => $bookingVendorId,
            'vendor_profile_id' => (int) $row->vendor_profile_id,
        ];
    }
}
