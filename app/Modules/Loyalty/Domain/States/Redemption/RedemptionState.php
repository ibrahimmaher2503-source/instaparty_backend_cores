<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\States\Redemption;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class RedemptionState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Applied::class)
            ->allowTransition(Pending::class, Voided::class)
            ->allowTransition(Applied::class, Reversed::class);
    }
}
