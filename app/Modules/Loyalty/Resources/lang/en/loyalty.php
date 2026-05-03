<?php

declare(strict_types=1);

return [
    'reason' => [
        'earn'         => 'Points earned on completed booking',
        'redeem'       => 'Points redeemed on booking',
        'reversal'     => 'Points reversed due to refund',
        'void_release' => 'Held points released — booking cancelled',
    ],
    'errors' => [
        'insufficient_balance'   => 'You do not have enough points to complete this redemption.',
        'below_min_threshold'    => 'You need at least :min points to redeem.',
        'exceeds_max_pct'        => 'Redemption cannot exceed :pct% of your order total.',
        'cross_vendor_forbidden' => 'Points earned with one vendor cannot be redeemed with another.',
        'no_active_program'      => 'This vendor does not have an active loyalty program.',
        'program_not_found'      => 'Loyalty program not found.',
        'redemption_not_found'   => 'Redemption not found.',
        'already_redeemed'       => 'A redemption is already active for this booking.',
    ],
    'status' => [
        'active'   => 'Active',
        'paused'   => 'Paused',
        'archived' => 'Archived',
    ],
];
