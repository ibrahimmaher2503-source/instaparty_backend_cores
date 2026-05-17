<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Intervention Thresholds
    |--------------------------------------------------------------------------
    |
    | Configurable thresholds for the AdminBookingInterventionResource actions.
    | All time values are in their stated unit (hours or minutes).
    |
    */
    'intervention' => [
        'stalled_threshold_hours'               => env('BOOKING_INTERVENTION_STALLED_HOURS', 48),
        'deadline_grace_period_minutes'         => env('BOOKING_INTERVENTION_GRACE_MINUTES', 0),
        'vendor_reminder_cooldown_minutes'      => env('BOOKING_INTERVENTION_VENDOR_REMINDER_COOLDOWN', 5),
        'customer_review_reminder_cooldown_hours' => env('BOOKING_INTERVENTION_REVIEW_REMINDER_COOLDOWN', 4),
        'suggest_max_candidates'                => env('BOOKING_INTERVENTION_SUGGEST_MAX_CANDIDATES', 5),
    ],

];
