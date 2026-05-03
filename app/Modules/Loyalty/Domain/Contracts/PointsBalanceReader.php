<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

interface PointsBalanceReader
{
    /**
     * Returns available (spendable) points for a customer with a specific vendor.
     * Available = sum(points) - sum(points_held) for pending redemptions.
     */
    public function availableFor(int $customerId, int $vendorProfileId): int;
}
