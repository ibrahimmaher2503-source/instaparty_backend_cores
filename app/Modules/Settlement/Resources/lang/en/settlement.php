<?php

declare(strict_types=1);

return [
    'nav' => [
        'commissions' => 'Commissions',
        'commission_rules' => 'Commission Rules',
        'wallet_ledger' => 'Wallet Ledger',
        'withdrawals_queue' => 'Withdrawal Requests',
        'settlement_runs' => 'Settlement Runs',
        'ledger_groups' => 'Ledger Groups',
        'reconciliation_runs' => 'Reconciliation Runs',
        'reconciliation_findings' => 'Reconciliation Findings',
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
        'ledger_group' => [
            'singular' => 'Ledger Group',
            'plural' => 'Ledger Groups',
        ],
        'reconciliation_run' => [
            'singular' => 'Reconciliation Run',
            'plural' => 'Reconciliation Runs',
        ],
        'reconciliation_finding' => [
            'singular' => 'Reconciliation Finding',
            'plural' => 'Reconciliation Findings',
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
        'kind' => 'Kind',
        'currency' => 'Currency',
        'direction' => 'Direction',
        'entry_type' => 'Entry Type',
        'amount' => 'Amount',
        'running_balance' => 'Running Balance',
        'group_id' => 'Group',
        'correlation_id' => 'Correlation ID',
        'causation_id' => 'Causation ID',
        'initiator_type' => 'Initiator Type',
        'related_entity' => 'Related Entity',
        'related_id' => 'Related ID',
        'scope_type' => 'Scope',
        'wallets_scanned' => 'Wallets Scanned',
        'findings_count' => 'Findings',
        'auto_repaired_count' => 'Auto-Repaired',
        'manual_review_count' => 'Manual Review',
        'started_at' => 'Started At',
        'completed_at' => 'Completed At',
        'finding_type' => 'Finding Type',
        'severity' => 'Severity',
        'resource_type' => 'Resource Type',
        'resource_id' => 'Resource ID',
        'resolution' => 'Resolution',
        'detected_at' => 'Detected At',
        'run_id' => 'Run',
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
        // Phase 4.11 — Finance Audit
        'withdrawal_state'     => 'Cannot perform this action in the current state.',
        'mark_paid_failed'     => 'Could not mark as paid.',
        'wallet_locked_retry'  => 'Wallet is currently locked — please retry in a few seconds.',
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
    'dispute_oversight' => 'Dispute Oversight',
    'pending_refunds' => 'Pending Refunds',
    'pending_withdrawals' => 'Pending Withdrawals',
    'total_disputed_amount' => 'Total Disputed Amount',
    'pending_refunds_section' => 'Pending Refunds',
    'pending_withdrawals_section' => 'Pending Withdrawals',
    'ref' => 'Payment Ref',
    'status' => 'Status',
    'amount' => 'Amount',
    'reason' => 'Reason',
    'created_at' => 'Created At',
    'withdrawal_id' => 'Withdrawal ID',
    'owner' => 'Owner',

    'ledger_group' => [
        'detail' => 'Group Details',
        'entries' => 'Ledger Entries',
    ],

    // Phase 4.11 — Withdrawal Proof & Finance Audit
    'notifications' => [
        'withdrawal_approved' => 'Withdrawal approved.',
        'withdrawal_paid'     => 'Withdrawal marked as paid.',
    ],

    'fields' => [
        'bank_transfer_reference' => 'Bank transfer reference',
        'transfer_proof'          => 'Transfer proof',
        'payment_note_en'         => 'Payment note (English)',
        'payment_note_ar'         => 'Payment note (Arabic)',
        'approved_at'             => 'Approved at',
        'approved_by'             => 'Approved by',
        'paid_by'                 => 'Paid by',
    ],

    'validation' => [
        'bank_transfer_reference_required' => 'Bank transfer reference is required.',
        'bank_transfer_reference_duplicate' => 'This reference has already been used for this vendor.',
        'proof_required'                    => 'Transfer proof file is required.',
        'proof_format'                      => 'Proof must be PDF, JPEG, or PNG and at most 10 MB.',
    ],

    'actions' => [
        'approve'            => 'Approve',
        'approve_description' => 'Approve this withdrawal request. The vendor will be notified.',
        'mark_paid'          => 'Mark Paid',
        'show_causal_chain' => 'Show Causal Chain',
        'causal_chain_title' => 'Full Causal Chain',
        'causal_chain_entries' => ':count ledger entries in this chain',
        'close' => 'Close',
        'mark_ignored' => 'Mark as Ignored',
        'mark_ignored_heading' => 'Mark Finding as Ignored (Known Issue)',
        'mark_ignored_description' => 'This finding will be marked as a known issue and suppressed from the manual review queue.',
        'ignore_reason' => 'Reason',
        'marked_ignored_success' => 'Finding marked as ignored.',
    ],

    'findings' => [
        'unresolved' => 'Unresolved',
        'auto_repaired' => 'Auto-Repaired',
        'ignored' => 'Ignored (Known Issue)',
    ],

    'reconciliation_run' => [
        'summary' => 'Run Summary',
    ],

    'reconciliation_dashboard' => [
        'nav_label' => 'Reconciliation Dashboard',
        'title' => 'Reconciliation Dashboard',
        'open_high_findings' => 'Open High-Severity Findings',
        'auto_repaired_today' => 'Auto-Repaired Today',
        'runs_last_7_days' => 'Runs (Last 7 Days)',
        'run_trend_heading' => '7-Day Run Trend',
        'date' => 'Date',
        'runs' => 'Runs',
    ],
];
