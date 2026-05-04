<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class SubscriptionState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveState::class)
            ->allowTransition(ActiveState::class, PastDueState::class)
            ->allowTransition(ActiveState::class, CancelledState::class)
            ->allowTransition(ActiveState::class, SupersededState::class)
            ->allowTransition(PastDueState::class, ActiveState::class)   // renewal success recovers
            ->allowTransition(PastDueState::class, ExpiredState::class)
            ->allowTransition(PastDueState::class, CancelledState::class);
    }
}
