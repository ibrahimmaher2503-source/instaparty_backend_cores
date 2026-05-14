<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Services;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;

class PointsCalculator
{
    /**
     * Compute earnable points for a given net minor amount under a program.
     *
     * Formula:
     *   base = floor( (netMinor / 100) * points_per_currency_unit )
     *   total = floor( base * rule.multiplier )   if a rule is supplied
     */
    public function earnPointsFor(LoyaltyProgram $program, int $netMinor, ?LoyaltyRule $rule = null): int
    {
        if ($netMinor <= 0) {
            return 0;
        }

        $perUnit = (float) $program->points_per_currency_unit;
        $major = $netMinor / 100;

        $base = (int) floor($major * $perUnit);

        if ($rule !== null) {
            $multiplier = (float) $rule->multiplier;
            $base = (int) floor($base * $multiplier);
        }

        return max(0, $base);
    }
}
