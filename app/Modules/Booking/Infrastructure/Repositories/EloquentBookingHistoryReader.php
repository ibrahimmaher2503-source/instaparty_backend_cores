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
            ->join('booking_vendors', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->join('booking_items', 'booking_vendors.id', '=', 'booking_items.booking_vendor_id')
            ->join('users', 'bookings.customer_id', '=', 'users.id')
            ->whereNotIn('users.status', ['suspended', 'banned'])
            ->select('bookings.customer_id');

        if ($productType !== null) {
            $query->where('booking_items.product_type', $productType->value);
        }

        if ($withinDays !== null) {
            $query->where('bookings.created_at', '>=', now()->subDays($withinDays));
        }

        if ($governorateId !== null) {
            $query->join('booking_addresses', 'bookings.id', '=', 'booking_addresses.booking_id')
                ->join('cities', 'booking_addresses.city_id', '=', 'cities.id')
                ->where('cities.governorate_id', $governorateId);
        }

        if ($preferredLocale !== null && $preferredLocale !== 'both') {
            $query->where('users.preferred_locale', $preferredLocale);
        }

        return $query->distinct()->pluck('bookings.customer_id');
    }
}
