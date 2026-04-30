<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\SaleItemStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class SaleItemStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, InPreparationState::class)
            ->allowTransition(InPreparationState::class, ReadyState::class)
            ->allowTransition(ReadyState::class, DeliveredState::class);
    }
}
