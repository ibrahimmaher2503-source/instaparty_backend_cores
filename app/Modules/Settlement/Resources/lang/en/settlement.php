<?php

declare(strict_types=1);

return [
    'nav' => [
        'commissions' => 'Commissions',
        'commission_rules' => 'Commission Rules',
        'wallet_ledger' => 'Wallet Ledger',
        'withdrawals_queue' => 'Withdrawal Requests',
        'settlement_runs' => 'Settlement Runs',
    ],

    'models' => [
        'commission' => [
            'singular' => 'Commission',
            'plural' => 'Commissions',
        ],
        'commission_rule' => [
            'singular' => 'Commission Rule',
            'plural' => 'Commission Rules',
        ],
        'wallet' => [
            'singular' => 'Wallet',
            'plural' => 'Wallets',
        ],
        'withdrawal' => [
            'singular' => 'Withdrawal Request',
            'plural' => 'Withdrawal Requests',
        ],
        'settlement_run' => [
            'singular' => 'Settlement Run',
            'plural' => 'Settlement Runs',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'booking_item' => 'Booking Item',
        'payment' => 'Payment',
        'vendor' => 'Vendor',
        'product_type' => 'Product Type',
        'gross_amount' => 'Gross Amount',
        'commission_amount' => 'Commission Amount',
        'vendor_share' => 'Vendor Share',
        'status' => 'Status',
        'period_start' => 'Period Start',
        'period_end' => 'Period End',
        'total_gross' => 'Total Gross Amount',
        'total_commission' => 'Total Commission',
        'total_vendor_share' => 'Total Vendor Share',
    ],

    'status' => [
        'calculated' => 'Calculated',
        'partially_reversed' => 'Partially Reversed',
        'reversed' => 'Fully Reversed',
        'pending' => 'Pending',
        'reconciled' => 'Reconciled',
        'disputed' => 'Disputed',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'rejected' => 'Rejected',
    ],

    'errors' => [
        'insufficient_balance' => 'Insufficient wallet balance. Available: :available :currency, requested: :requested :currency.',
        'existing_pending_withdrawal' => 'You already have a pending withdrawal request (:public_id). Please wait until it is processed before submitting another request.',
        'below_minimum_amount' => 'The withdrawal amount is below the minimum of :minimum :currency.',
        'negative_balance_blocked' => 'Your wallet balance is negative. Withdrawals are blocked until the balance is restored.',
        'withdrawal_not_found' => 'Withdrawal request not found.',
        'no_wallet_access' => 'You do not have access to this wallet.',
        'per_page_too_large' => 'per_page must be 100 or less.',
        'invalid_iban' => 'The provided IBAN is not valid.',
    ],

    'ledger' => [
        'commission_credit' => 'Commission credit for booking item #:booking_item_id',
        'refund_debit' => 'Refund debit for booking item #:booking_item_id',
        'withdrawal_debit' => 'Withdrawal paid to bank account ending in ...:iban_last3',
        'manual_adjustment' => 'Manual adjustment: :note',
    ],

    'withdrawal_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'rejected' => 'Rejected',
    ],

    'commission_status' => [
        'calculated' => 'Calculated',
        'partially_reversed' => 'Partially Reversed',
        'reversed' => 'Fully Reversed',
    ],

    'settlement_run_status' => [
        'pending' => 'Pending',
        'reconciled' => 'Reconciled',
        'disputed' => 'Disputed',
    ],
];
