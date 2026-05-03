<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\RentalItemStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class RentalItemStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingDeliveryState::class)
            ->allowTransition(PendingDeliveryState::class, OutForDeliveryState::class)
            ->allowTransition(OutForDeliveryState::class, DeliveredState::class)
            ->allowTransition(DeliveredState::class, SetupCompleteState::class)
            ->allowTransition(SetupCompleteState::class, PickedUpState::class);
    }
}
