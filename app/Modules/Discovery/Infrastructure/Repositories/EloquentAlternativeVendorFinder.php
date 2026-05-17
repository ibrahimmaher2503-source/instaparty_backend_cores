<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Collection;

final class EloquentAlternativeVendorFinder implements AlternativeVendorFinder
{
    public function findCandidates(Booking $booking, ?int $limit = null): Collection
    {
        $limit ??= config('booking.intervention.suggest_max_candidates', 5);
        $governorateId = $booking->address?->governorate_id;
        $productType = $booking->items->first()?->product_type?->value;
        $eventStartsAt = $booking->event_starts_at;
        $eventEndsAt = $booking->event_ends_at;
        $existingVendorIds = $booking->vendors()->pluck('vendor_profile_id')->toArray();
        $categoryIds = $booking->items()->pluck('category_id')->filter()->unique()->toArray();

        return VendorProfile::query()
            ->where('approval_status', 'approved')
            ->whereNull('deleted_at')
            ->when($productType, fn ($q) => $q->whereHas('approvedProductTypes', fn ($q2) =>
                $q2->where('product_type', $productType)
            ))
            ->when($governorateId, fn ($q) => $q->whereHas('coverageAreas', fn ($q2) =>
                $q2->where('governorate_id', $governorateId)
            ))
            ->when(! empty($categoryIds), fn ($q) => $q->whereHas('services', fn ($q2) =>
                $q2->where('status', 'published')->whereIn('category_id', $categoryIds)
            ))
            ->when($eventStartsAt && $eventEndsAt, fn ($q) => $q->whereDoesntHave('services', fn ($q2) =>
                $q2->whereHas('availabilityBlocks', fn ($q3) =>
                    $q3->where('starts_at', '<', $eventEndsAt)
                       ->where('ends_at', '>', $eventStartsAt)
                )
            ))
            ->whereNotIn('id', $existingVendorIds)
            ->withCount(['services' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('services_count')
            ->orderByDesc('rating_avg')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function validateCandidates(Booking $booking, array $vendorProfileIds): void
    {
        $candidates = $this->findCandidates($booking, PHP_INT_MAX)
            ->pluck('id')
            ->toArray();

        $invalid = array_diff($vendorProfileIds, $candidates);
        if (! empty($invalid)) {
            throw new \InvalidArgumentException(
                'The following vendor IDs do not pass the candidate filter: '.implode(', ', $invalid)
            );
        }
    }
}
