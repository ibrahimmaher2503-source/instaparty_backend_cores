<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\States\Redemption;

class Voided extends RedemptionState
{
    public static string $name = 'voided';
}
