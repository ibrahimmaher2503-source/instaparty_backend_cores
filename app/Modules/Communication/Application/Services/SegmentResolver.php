<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use App\Modules\Booking\Domain\Contracts\BookingHistoryReader;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Support\Collection;

class SegmentResolver
{
    private const ALLOWED_KEYS = [
        'booked_product_type',
        'booked_within_days',
        'governorate_id',
        'preferred_locale',
    ];

    public function __construct(
        private readonly BookingHistoryReader $reader,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  string  $targetLocale  'ar'|'en'|'both'
     * @return Collection<int, int>
     */
    public function resolve(array $filters, string $targetLocale = 'both'): Collection
    {
        if (empty($filters)) {
            throw new InvalidSegmentFilterException('segment_filters must not be empty — campaigns must target a specific segment.');
        }

        $unknown = array_diff(array_keys($filters), self::ALLOWED_KEYS);
        if (! empty($unknown)) {
            throw new InvalidSegmentFilterException('Unknown segment filter key(s): '.implode(', ', $unknown));
        }

        $productType = isset($filters['booked_product_type'])
            ? ProductType::from((string) $filters['booked_product_type'])
            : null;

        $withinDays = isset($filters['booked_within_days'])
            ? (int) $filters['booked_within_days']
            : null;

        $governorateId = isset($filters['governorate_id'])
            ? (int) $filters['governorate_id']
            : null;

        $preferredLocale = $targetLocale !== 'both' ? $targetLocale : null;

        if (isset($filters['preferred_locale'])) {
            $preferredLocale = (string) $filters['preferred_locale'];
        }

        return $this->reader->customersWithBookingsMatching(
            productType: $productType,
            withinDays: $withinDays,
            governorateId: $governorateId,
            preferredLocale: $preferredLocale,
        );
    }
}
