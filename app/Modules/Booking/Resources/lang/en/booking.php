<?php

declare(strict_types=1);

return [
    'nav' => [
        'bookings' => 'Bookings',
        'negotiation_monitor' => 'Negotiation Monitoring',
        'modifications' => 'Booking Modifications',
        'state_transitions' => 'State Changes',
    ],

    'models' => [
        'booking' => [
            'singular' => 'Booking',
            'plural' => 'Bookings',
        ],
        'negotiation_monitor' => [
            'singular' => 'Negotiation Monitor',
            'plural' => 'Negotiation Monitoring',
        ],
        'modification' => [
            'singular' => 'Booking Modification',
            'plural' => 'Booking Modifications',
        ],
        'state_transition' => [
            'singular' => 'State Change',
            'plural' => 'State Changes',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'reference_no' => 'Reference Number',
        'customer_id' => 'Customer',
        'occasion_id' => 'Occasion',
        'lifecycle_status' => 'Booking Status',
        'payment_status' => 'Payment Status',
        'fulfillment_status' => 'Fulfillment Status',
        'total' => 'Total',
        'submitted_at' => 'Submitted At',
        'nearest_deadline' => 'Nearest Due Date',
        'booking_vendor' => 'Booking Vendor',
        'proposed_by' => 'Proposed By',
        'proposal_kind' => 'Proposal Type',
        'status' => 'Status',
        'expires_at' => 'Expires At',
        'transitionable_type' => 'Related Entity Type',
        'transitionable_id' => 'Related Entity ID',
        'from_state' => 'Previous State',
        'to_state' => 'New State',
        'triggered_by' => 'Triggered By',
    ],

    'lifecycle_status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'vendor_review' => 'Pending Vendor Review',
        'customer_review' => 'Pending Customer Review',
        'confirmed' => 'Confirmed',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'payment_status' => [
        'pending' => 'Pending',
        'authorized' => 'Authorized',
        'captured' => 'Captured',
        'failed' => 'Payment Failed',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
        'voided' => 'Voided',
    ],

    'fulfillment_status' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
];