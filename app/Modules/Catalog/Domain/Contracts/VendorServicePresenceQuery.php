<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contracts;

interface VendorServicePresenceQuery
{
    public function hasAnyService(int $vendorProfileId): bool;

    public function hasServiceInReviewOrPublished(int $vendorProfileId): bool;
}
