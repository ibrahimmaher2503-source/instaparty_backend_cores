<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Loyalty;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Loyalty\Domain\Contracts\VendorLookup;

final class EloquentVendorLookup implements VendorLookup
{
    public function isApproved(int $vendorProfileId): bool
    {
        return VendorProfile::query()
            ->whereKey($vendorProfileId)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->exists();
    }
}
