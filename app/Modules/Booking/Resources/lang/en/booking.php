<?php

declare(strict_types=1);

return [
    'nav' => [
        'bookings' => 'Bookings',
        'negotiation_monitor' => 'Negotiation Monitor',
        'modifications' => 'Modifications',
        'state_transitions' => 'State Transitions',
    ],

    'models' => [
        'booking' => [
            'singular' => 'Booking',
            'plural' => 'Bookings',
        ],
        'negotiation_monitor' => [
            'singular' => 'Negotiation Monitor',
            'plural' => 'Negotiation Monitor',
        ],
        'modification' => [
            'singular' => 'Booking Modification',
            'plural' => 'Booking Modifications',
        ],
        'state_transition' => [
            'singular' => 'State Transition',
            'plural' => 'State Transitions',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'reference_no' => 'Reference No',
        'customer_id' => 'Customer',
        'occasion_id' => 'Occasion',
        'lifecycle_status' => 'Lifecycle Status',
        'payment_status' => 'Payment Status',
        'fulfillment_status' => 'Fulfillment Status',
        'total' => 'Total',
        'submitted_at' => 'Submitted At',
        'nearest_deadline' => 'Nearest Deadline',
        'booking_vendor' => 'Booking Vendor',
        'proposed_by' => 'Proposed By',
        'proposal_kind' => 'Proposal Kind',
        'status' => 'Status',
        'expires_at' => 'Expires At',
        'transitionable_type' => 'Transitionable Type',
        'transitionable_id' => 'Transitionable ID',
        'from_state' => 'From State',
        'to_state' => 'To State',
        'triggered_by' => 'Triggered By',
    ],

    'lifecycle_status' => [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'vendor_review' => 'Vendor Review',
        'customer_review' => 'Customer Review',
        'confirmed' => 'Confirmed',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'payment_status' => [
        'unpaid' => 'Unpaid',
        'partial' => 'Partially Paid',
        'paid' => 'Paid',
        'refund_pending' => 'Refund Pending',
        'partially_refunded' => 'Partially Refunded',
        'refunded' => 'Refunded',
    ],

    'fulfillment_status' => [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'partially_completed' => 'Partially Completed',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    'force_cancel' => 'Force Cancel',
    'force_cancel_reason' => 'Cancellation Reason',
    'force_cancel_confirm_heading' => 'Force-cancel this booking?',
    'force_cancel_confirm_description' => 'This will immediately cancel the booking and initiate any applicable refunds. This action cannot be undone.',
    'force_cancelled_successfully' => 'Booking force-cancelled successfully.',

    'dashboard' => [
        'confirmed_today' => 'Confirmed Today',
        'submitted_today' => 'Submitted Today',
        'draft_bookings' => 'Draft Bookings',
    ],
];
