<?php

declare(strict_types=1);

return [
    'navigation' => [
        'payments' => 'Payments',
        'refunds' => 'Refunds',
        'attempts' => 'Payment Attempts',
        'webhook_logs' => 'Webhook Logs',
        'idempotency_keys' => 'Idempotency Keys',
    ],

    'models' => [
        'payment' => [
            'singular' => 'Payment',
            'plural' => 'Payments',
        ],
        'payment_attempt' => [
            'singular' => 'Payment Attempt',
            'plural' => 'Payment Attempts',
        ],
        'webhook_log' => [
            'singular' => 'Webhook Log',
            'plural' => 'Webhook Logs',
        ],
        'idempotency_key' => [
            'singular' => 'Idempotency Key',
            'plural' => 'Idempotency Keys',
        ],
        'refund' => [
            'singular' => 'Refund',
            'plural' => 'Refunds',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'booking' => 'Booking',
        'gateway' => 'Payment Gateway',
        'amount' => 'Amount',
        'method' => 'Payment Method',
        'status' => 'Status',
        'captured_at' => 'Captured At',
        'payment' => 'Payment',
        'attempt_no' => 'Attempt Number',
        'http_status' => 'HTTP Status',
        'event_type' => 'Event Type',
        'signature_valid' => 'Valid Signature',
        'processed_at' => 'Processed At',
        'key' => 'Key',
        'route' => 'Route',
        'response_status' => 'Response Status',
        'expires_at' => 'Expires At',
    ],

    'event_types' => [
        'payment_captured' => 'Payment Captured',
        'payment_failed' => 'Payment Failed',
        'payment_authorized' => 'Payment Authorized',
        'refund_completed' => 'Refund Completed',
        'refund_failed' => 'Refund Failed',
    ],

    'status' => [
        'pending' => 'Pending',
        'authorized' => 'Authorized',
        'captured' => 'Captured',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],
];
