<?php

declare(strict_types=1);

return [
    'plan' => [
        'free'    => 'Free',
        'silver'  => 'Silver',
        'gold'    => 'Gold',
        'premium' => 'Premium',
    ],
    'status' => [
        'active'     => 'Active',
        'past_due'   => 'Past Due',
        'cancelled'  => 'Cancelled',
        'expired'    => 'Expired',
        'superseded' => 'Superseded',
    ],
    'billing_cycle' => [
        'monthly' => 'Monthly',
        'yearly'  => 'Yearly',
        'none'    => 'None',
    ],
    'errors' => [
        'limit_reached' => 'You have reached the :feature limit for your :plan plan. Upgrade to :unblocking_plan to unlock more.',
        'cannot_subscribe_free' => 'The Free plan is assigned automatically and cannot be subscribed to directly.',
        'subscription_not_cancellable' => 'Your subscription cannot be cancelled in its current state.',
    ],
];
