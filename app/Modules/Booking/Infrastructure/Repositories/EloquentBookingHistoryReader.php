<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Contracts\BookingHistoryReader;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentBookingHistoryReader implements BookingHistoryReader
{
    /**
     * @return Collection<int, int>
     */
    public function customersWithBookingsMatching(
        ?ProductType $productType,
        ?int $withinDays,
        ?int $governorateId,
        ?string $preferredLocale,
    ): Collection {
        $query = DB::table('bookings')
            ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('users', 'bookings.user_id', '=', 'users.id')
            ->join('customer_profiles', 'users.id', '=', 'customer_profiles.user_id')
            ->whereNotIn('users.status', ['suspended', 'banned'])
            ->select('bookings.user_id');

        if ($productType !== null) {
            $query->where('booking_items.product_type', $productType->value);
        }

        if ($withinDays !== null) {
            $query->where('bookings.created_at', '>=', now()->subDays($withinDays));
        }

        if ($governorateId !== null) {
            $query->join('booking_addresses', 'bookings.id', '=', 'booking_addresses.booking_id')
                ->where('booking_addresses.governorate_id', $governorateId);
        }

        if ($preferredLocale !== null && $preferredLocale !== 'both') {
            $query->where('users.preferred_locale', $preferredLocale);
        }

        return $query->distinct()->pluck('bookings.user_id');
    }
}
