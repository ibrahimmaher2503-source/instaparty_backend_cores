<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

interface VendorLookup
{
    public function isApproved(int $vendorProfileId): bool;
}
