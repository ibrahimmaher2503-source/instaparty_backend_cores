<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

use App\Modules\Subscriptions\Domain\States\Transitions\CancelSubscriptionTransition;
use App\Modules\Subscriptions\Domain\States\Transitions\ExpireSubscriptionTransition;
use App\Modules\Subscriptions\Domain\States\Transitions\MarkPastDueTransition;
use App\Modules\Subscriptions\Domain\States\Transitions\RenewSubscriptionTransition;
use App\Modules\Subscriptions\Domain\States\Transitions\SupersedeSubscriptionTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class SubscriptionState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ActiveState::class)
            ->allowTransition(ActiveState::class, PastDueState::class, MarkPastDueTransition::class)
            ->allowTransition(ActiveState::class, CancelledState::class, CancelSubscriptionTransition::class)
            ->allowTransition(ActiveState::class, SupersededState::class, SupersedeSubscriptionTransition::class)
            ->allowTransition(PastDueState::class, ActiveState::class, RenewSubscriptionTransition::class)
            ->allowTransition(PastDueState::class, ExpiredState::class, ExpireSubscriptionTransition::class)
            ->allowTransition(PastDueState::class, CancelledState::class, CancelSubscriptionTransition::class);
    }
}
