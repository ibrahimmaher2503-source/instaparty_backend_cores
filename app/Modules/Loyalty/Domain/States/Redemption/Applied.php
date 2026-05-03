<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\States\Redemption;

class Applied extends RedemptionState
{
    public static string $name = 'applied';
}
