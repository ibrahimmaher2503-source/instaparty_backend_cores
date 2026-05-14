<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Services;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;

class DiscountCalculator
{
    /**
     * Convert a number of points into a discount in minor units.
     *
     * Formula: floor(points * points_value_minor)
     */
    public function discountMinorFor(LoyaltyProgram $program, int $points): int
    {
        if ($points <= 0) {
            return 0;
        }

        $valueMinor = (int) $program->points_value_minor;

        return max(0, (int) floor($points * $valueMinor));
    }
}
