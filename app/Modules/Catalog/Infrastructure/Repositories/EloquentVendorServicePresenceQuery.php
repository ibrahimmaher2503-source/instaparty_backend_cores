<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Catalog\Domain\Models\Service;

/**
 * The (vendor_profile_id, slug) UNIQUE index on services already covers queries
 * on vendor_profile_id as the leading column, so both exists() calls are index-only.
 */
class EloquentVendorServicePresenceQuery implements VendorServicePresenceQuery
{
    public function hasAnyService(int $vendorProfileId): bool
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->exists();
    }

    public function hasServiceInReviewOrPublished(int $vendorProfileId): bool
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->whereIn('status', ['pending_review', 'published'])
            ->exists();
    }
}
