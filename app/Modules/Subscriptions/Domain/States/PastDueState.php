<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

class PastDueState extends SubscriptionState
{
    public static string $name = 'past_due';
}
