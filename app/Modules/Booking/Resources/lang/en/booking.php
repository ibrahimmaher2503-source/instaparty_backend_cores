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
        'customer_phone' => 'Phone',
        'occasion_id' => 'Occasion',
        'guest_count' => 'Guest Count',
        'event_starts_at' => 'Event Starts At',
        'event_ends_at' => 'Event Ends At',
        'lifecycle_status' => 'Booking Status',
        'payment_status' => 'Payment Status',
        'fulfillment_status' => 'Fulfillment Status',
        'total' => 'Total',
        'amount_paid' => 'Amount Paid',
        'submitted_at' => 'Submitted At',
        'vendor' => 'Vendor',
        'vendor_status' => 'Vendor Status',
        'service' => 'Service',
        'product_type' => 'Product Type',
        'quantity' => 'Quantity',
        'line_total' => 'Line Total',
        'item_status' => 'Item Status',
        'response_deadline' => 'Response Deadline',
        'city' => 'City',
        'address_line' => 'Address Line',
        'building' => 'Building',
        'floor' => 'Floor',
        'apartment' => 'Apartment',
        'landmark' => 'Landmark',
        'recipient_name' => 'Recipient',
        'recipient_phone' => 'Recipient Phone',
        'version' => 'Version',
        'trigger_kind' => 'Trigger Kind',
        'actor' => 'Actor',
        'context' => 'Context',
        'snapshot' => 'Snapshot',
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

    'sections' => [
        'summary' => 'Booking Summary',
    ],

    'relations' => [
        'vendors' => 'Booking Vendors',
        'items' => 'Booking Items',
        'addresses' => 'Booking Addresses',
        'payments' => 'Payments',
        'snapshots' => 'Booking Snapshots',
        'state_transitions' => 'State Transitions',
    ],

    'actions' => [
        'edit' => 'Edit',
        'force_cancel' => 'Force Cancel',
        'add_admin_note' => 'Add Admin Note',
        'save_changes' => 'Save Changes',
    ],

    'modals' => [
        'force_cancel_title' => 'Force cancel booking',
        'force_cancel_description' => 'This will cancel the booking and record an audit trail.',
        'force_cancel_reason' => 'Reason',
        'admin_note_body' => 'Admin note',
    ],

    'notifications' => [
        'booking_updated' => 'Booking updated successfully.',
        'force_cancelled' => 'Booking force-cancelled successfully.',
        'admin_note_added' => 'Admin note added successfully.',
    ],

    'empty_states' => [
        'vendors' => 'No vendor records yet.',
        'items' => 'No booking items yet.',
        'addresses' => 'No booking address yet.',
        'payments' => 'No payments yet.',
        'snapshots' => 'No snapshots yet.',
        'state_transitions' => 'No state transitions yet.',
    ],

    'vendor_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'modified' => 'Modified',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
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
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
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

    'placeholders' => [
        'none' => '—',
    ],
];
