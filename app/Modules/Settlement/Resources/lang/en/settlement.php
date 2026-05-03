<?php

declare(strict_types=1);

return [
    'errors' => [
        'insufficient_balance' => 'Insufficient wallet balance. Available: :available :currency, requested: :requested :currency.',
        'existing_pending_withdrawal' => 'You already have a pending withdrawal request (:public_id). Wait until it is processed before requesting another.',
        'below_minimum_amount' => 'Withdrawal amount is below the minimum of :minimum :currency.',
        'negative_balance_blocked' => 'Your wallet balance is negative. Withdrawals are blocked until the balance is restored.',
        'withdrawal_not_found' => 'Withdrawal not found.',
        'no_wallet_access' => 'You do not have access to this wallet.',
        'per_page_too_large' => 'per_page must be 100 or less.',
        'invalid_iban' => 'The IBAN provided is not valid.',
    ],
    'ledger' => [
        'commission_credit' => 'Commission credit for booking item #:booking_item_id',
        'refund_debit' => 'Refund debit for booking item #:booking_item_id',
        'withdrawal_debit' => 'Withdrawal paid to bank account ending ...:iban_last3',
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
        'reversed' => 'Reversed',
    ],
    'settlement_run_status' => [
        'pending' => 'Pending',
        'reconciled' => 'Reconciled',
        'disputed' => 'Disputed',
    ],
];
