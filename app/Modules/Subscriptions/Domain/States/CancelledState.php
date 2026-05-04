<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

class CancelledState extends SubscriptionState
{
    public static string $name = 'cancelled';
}
