<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\DigitalItemStatus;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class DigitalItemStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, SentState::class)
            ->allowTransition(SentState::class, RedeemedState::class);
    }
}
