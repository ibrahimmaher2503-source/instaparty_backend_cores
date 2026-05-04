<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\States;

class SupersededState extends SubscriptionState
{
    public static string $name = 'superseded';
}
