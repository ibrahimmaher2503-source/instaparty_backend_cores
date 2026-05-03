<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\States\Redemption;

class Reversed extends RedemptionState
{
    public static string $name = 'reversed';
}
