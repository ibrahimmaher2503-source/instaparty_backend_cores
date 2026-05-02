<?php

return [
    'reason_code' => [
        'customer_request' => 'Customer request',
        'vendor_cancellation' => 'Vendor cancellation',
        'service_unavailable' => 'Service unavailable',
        'duplicate_charge' => 'Duplicate charge',
        'admin_discretion' => 'Admin discretion',
    ],
    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'policy' => [
        'allowed' => 'Allowed',
        'rental_window_closed' => 'Rental refund window is closed.',
        'rental_in_setup' => 'Rental setup has started.',
        'sale_in_preparation' => 'Sale item is already in preparation.',
        'digital_post_delivery' => 'Digital item cannot be refunded after delivery.',
    ],
    'errors' => [
        'partial_refund_unsupported' => 'Partial refunds are not supported in phase 1.',
    ],
];
