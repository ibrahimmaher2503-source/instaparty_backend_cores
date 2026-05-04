<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Contracts;

interface CommissionTierLookup
{
    /** Returns the commission discount in basis points for the vendor's current effective tier, or null if no tier discount applies. */
    public function resolveTierBps(int $vendorProfileId): ?int;
}
