<?php

declare(strict_types=1);

return [
    'nav' => [
        'programs' => 'Loyalty Programs',
        'rules' => 'Loyalty Rules',
        'redemptions' => 'Point Redemptions',
        'ledger' => 'Points Ledger',
    ],

    'models' => [
        'program' => [
            'singular' => 'Loyalty Program',
            'plural' => 'Loyalty Programs',
        ],
        'rule' => [
            'singular' => 'Loyalty Rule',
            'plural' => 'Loyalty Rules',
        ],
        'redemption' => [
            'singular' => 'Point Redemption',
            'plural' => 'Point Redemptions',
        ],
        'ledger_entry' => [
            'singular' => 'Points Ledger Entry',
            'plural' => 'Points Ledger Entries',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'program' => 'Program',
        'label' => 'Label',
        'min_points_to_redeem' => 'Minimum Points to Redeem',
        'max_redeem_pct_bps' => 'Maximum Redemption Percentage',
        'is_active' => 'Active',
        'effective_from' => 'Effective From',
        'customer' => 'Customer',
        'vendor' => 'Vendor',
        'points_held' => 'Held Points',
        'discount_minor' => 'Discount Amount',
        'status' => 'Status',
        'applied_at' => 'Applied At',
        'voided_at' => 'Voided At',
        'reversed_at' => 'Reversed At',
        'entry_type' => 'Entry Type',
        'points' => 'Points',
        'reason' => 'Reason',
    ],

    'reason' => [
        'earn' => 'Points earned when a booking is completed',
        'redeem' => 'Points redeemed on a booking',
        'reversal' => 'Points reversed due to a refund',
        'void_release' => 'Held points released because the booking was cancelled',
    ],

    'errors' => [
        'insufficient_balance' => 'You do not have enough points to complete this redemption.',
        'below_min_threshold' => 'You need at least :min points to redeem.',
        'exceeds_max_pct' => 'Redemption cannot exceed :pct% of the order total.',
        'cross_vendor_forbidden' => 'Points earned from one vendor cannot be redeemed with another vendor.',
        'no_active_program' => 'This vendor does not have an active loyalty program.',
        'program_not_found' => 'Loyalty program not found.',
        'redemption_not_found' => 'Point redemption not found.',
        'already_redeemed' => 'An active point redemption already exists for this booking.',
    ],

    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'archived' => 'Archived',
    ],
];
