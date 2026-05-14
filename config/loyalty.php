<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum points a single user can earn per (user × vendor × day).
    |--------------------------------------------------------------------------
    */
    'max_points_per_day' => env('LOYALTY_MAX_POINTS_PER_DAY', 50000),

    /*
    |--------------------------------------------------------------------------
    | Throttle for the customer-facing redemption endpoint.
    |--------------------------------------------------------------------------
    */
    'redemption_throttle' => [
        'max_attempts' => env('LOYALTY_REDEMPTION_MAX_ATTEMPTS', 20),
        'decay_minutes' => env('LOYALTY_REDEMPTION_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | When the daily loyalty:expire command should run.
    |--------------------------------------------------------------------------
    */
    'expire_command_hour' => env('LOYALTY_EXPIRE_COMMAND_HOUR', '03:00'),

    /*
    |--------------------------------------------------------------------------
    | Default currency used when a program is created without one explicitly.
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('LOYALTY_DEFAULT_CURRENCY', 'EGP'),
];
