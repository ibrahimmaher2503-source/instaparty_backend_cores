<?php

declare(strict_types=1);

return [
    'nav_group' => 'Subscriptions',
    'plans' => 'Subscription Plans',
    'vendor_subs' => 'Vendor Subscriptions',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
    'audit' => 'Audit Log',
    'override_tier' => 'Override Tier',
    'revoke_override' => 'Revoke Override',
    'override_reason_en' => 'Override Reason (English)',
    'override_reason_ar' => 'Override Reason (Arabic)',
    'override_expires_at' => 'Override Expires At (optional)',
    'past_due_stat' => 'Past-Due Subscriptions',
    'past_due_invoices_require_attention' => 'Invoices require immediate attention',
    'vendor' => 'Vendor',
    'expires_at' => 'Expires At',
    'plan' => [
        'free' => 'Free',
        'silver' => 'Silver',
        'gold' => 'Gold',
        'premium' => 'Premium',
    ],
    'status' => [
        'active' => 'Active',
        'past_due' => 'Past Due',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'superseded' => 'Superseded',
    ],
    'billing_cycle' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'none' => 'None',
    ],
    'errors' => [
        'limit_reached' => 'You have reached the :feature limit for your :plan plan. Upgrade to :unblocking_plan to unlock more.',
        'cannot_subscribe_free' => 'The Free plan is assigned automatically and cannot be subscribed to directly.',
        'subscription_not_cancellable' => 'Your subscription cannot be cancelled in its current state.',
    ],
];
