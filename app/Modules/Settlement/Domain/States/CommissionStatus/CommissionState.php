<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\States\CommissionStatus;

use App\Modules\Settlement\Domain\States\CommissionStatus\Transitions\FullyReverseCommissionTransition;
use App\Modules\Settlement\Domain\States\CommissionStatus\Transitions\PartiallyReverseCommissionTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CommissionState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(CalculatedState::class)
            ->allowTransition(CalculatedState::class, PartiallyReversedState::class, PartiallyReverseCommissionTransition::class)
            ->allowTransition(CalculatedState::class, ReversedState::class, FullyReverseCommissionTransition::class)
            ->allowTransition(PartiallyReversedState::class, ReversedState::class, FullyReverseCommissionTransition::class);
    }
}
