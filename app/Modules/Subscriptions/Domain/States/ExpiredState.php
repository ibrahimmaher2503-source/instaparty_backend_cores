<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

class ExpiredState extends SubscriptionState
{
    public static string $name = 'expired';
}
