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
        'vendor_subtotal' => 'Vendor Subtotal',
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
        'status' => 'Status Overview',
        'customer' => 'Customer',
        'event' => 'Event Details',
        'kpis' => 'Overview',
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
        'create_booking' => 'Create Booking',
    ],

    'wizard' => [
        'step_event' => 'Event Details',
        'step_address' => 'Delivery Address',
        'step_service' => 'Service',
        'submit' => 'Create & Submit Booking',
    ],

    'create' => [
        'customer' => 'Customer',
        'occasion' => 'Occasion',
        'event_starts_at' => 'Event Start',
        'event_ends_at' => 'Event End',
        'guest_count' => 'Guest Count',
        'city' => 'City',
        'address_line' => 'Address Line',
        'address_building' => 'Building',
        'address_floor' => 'Floor',
        'address_apartment' => 'Apartment',
        'address_landmark' => 'Landmark',
        'recipient_name' => 'Recipient Name',
        'recipient_phone' => 'Recipient Phone (E.164)',
        'service' => 'Service',
        'quantity' => 'Quantity',
        'success' => 'Booking created and submitted to vendor.',
        'error' => 'Failed to create booking.',
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

    'product_type' => [
        'rental' => 'Rental',
        'sale' => 'Sale',
        'digital' => 'Digital',
    ],

    'intervention' => [
        'nav_label' => 'Booking Intervention',
        'page_title' => 'Troubled Bookings',

        // Table columns
        'columns' => [
            'reference' => 'Reference',
            'customer' => 'Customer',
            'product_type' => 'Product Type',
            'lifecycle_status' => 'Lifecycle',
            'payment_status' => 'Payment',
            'fulfillment_status' => 'Fulfillment',
            'trouble_type' => 'Trouble',
            'nearest_deadline' => 'Deadline',
            'total' => 'Total',
            'event_starts_at' => 'Event Starts',
            'event_ends_at' => 'Event Ends',
            'submitted_at' => 'Submitted',
            'guest_count' => 'Guests',
            'vendor' => 'Vendor',
            'sub_status' => 'Status',
            'response_deadline' => 'Response Deadline',
            'responded_at' => 'Responded At',
            'proposal_kind' => 'Proposal',
            'status' => 'Status',
            'proposed_at' => 'Proposed',
            'from_state' => 'From',
            'to_state' => 'To',
            'actor_type' => 'Actor',
            'transitioned_at' => 'When',
            'gateway' => 'Gateway',
            'amount' => 'Amount',
            'paid_at' => 'Paid At',
            'intervention_type' => 'Intervention',
            'admin' => 'Admin',
            'intervened_at' => 'When',
            'reason' => 'Reason',
        ],

        // Detail-page sections
        'sections' => [
            'summary' => 'Booking Summary',
            'status' => 'Status',
            'vendors' => 'Vendors',
            'modifications' => 'Open Modifications',
            'state_transitions' => 'State Transitions',
            'payments' => 'Payments',
            'customer_notes' => 'Customer Notes',
            'intervention_history' => 'Intervention History',
        ],

        // Trouble badges
        'trouble' => [
            'late_vendor_response' => 'Late Vendor',
            'all_vendors_rejected' => 'All Rejected',
            'customer_review_pending' => 'Review Pending',
            'stalled' => 'Stalled',
        ],

        // Action labels
        'actions' => [
            'send_vendor_reminder' => 'Send Reminder',
            'escalate_vendor_timeout' => 'Escalate Timeout',
            'extend_vendor_deadline' => 'Extend Response Deadline',
            'suggest_alternative_vendors' => 'Suggest Alternatives',
            'resume_customer_review' => 'Resume Review',
            'create_note' => 'Add Note',
            'freeze_chat' => 'Freeze Chat',
            'resume_chat' => 'Resume Chat',
            'view' => 'View',
        ],

        // Form field labels
        'fields' => [
            'note_optional' => 'Note (optional)',
            'note' => 'Note',
            'escalation_reason' => 'Reason for escalation',
            'reason' => 'Reason',
            'freeze_reason' => 'Reason for freezing',
            'resume_reason' => 'Reason for resuming',
            'suggest_vendors' => 'Suggest vendors',
            'extend_hours' => 'Extension (hours)',
        ],

        // Confirmation modals
        'confirm' => [
            'send_vendor_reminder' => 'Send vendor reminder?',
            'escalate_vendor_timeout' => 'Escalate vendor timeout? This will mark the vendor as timed out.',
            'extend_vendor_deadline' => 'Extend vendor response deadline? This will reset their deadline and allow them to accept or decline.',
            'suggest_alternative_vendors' => 'Suggest these vendors to the customer?',
            'resume_customer_review' => 'Send review reminder to the customer?',
            'create_note' => 'Save this note?',
            'freeze_chat' => 'Freeze the booking chat? All parties will be notified.',
            'resume_chat' => 'Resume the booking chat? All parties will be notified.',
        ],

        // Success/error toasts
        'success' => [
            'send_vendor_reminder' => 'Vendor reminder sent successfully.',
            'escalate_vendor_timeout' => 'Vendor escalated to timed out.',
            'extend_vendor_deadline' => 'Response deadline extended successfully.',
            'suggest_alternative_vendors' => 'Alternative vendors suggested to customer.',
            'resume_customer_review' => 'Customer review reminder sent.',
            'create_note' => 'Note saved.',
            'freeze_chat' => 'Booking chat frozen successfully.',
            'resume_chat' => 'Booking chat resumed successfully.',
        ],
        'error' => [
            'throttled' => 'This action was performed recently. Please wait before trying again.',
            'vendor_not_pending' => 'Vendor is no longer in pending status.',
            'deadline_not_passed' => 'Response deadline has not yet passed.',
            'no_open_modification' => 'No open modification found for this booking.',
            'chat_thread_not_found' => 'No chat thread found for this booking.',
            'chat_already_frozen' => 'The booking chat is already frozen.',
            'chat_not_frozen' => 'The booking chat is not currently frozen.',
            'no_vendor_past_deadline' => 'No vendor with a passed deadline was found.',
            'vendor_cannot_extend_deadline' => 'Vendor is not in a state that allows deadline extension.',
        ],
    ],

    'modification_actions' => [
        'details_section' => 'Modification Details',
        'proposed_by' => 'Proposed By',
        'vendor_explanation' => 'Vendor Explanation',
        'rejection_reason' => 'Rejection Reason',
        'rejection_reason_en' => 'Rejection Reason (English)',
        'rejection_reason_ar' => 'سبب الرفض (العربية)',
        'forward_to_customer' => 'Forward to Customer',
        'forward_confirm_title' => 'Forward Modification to Customer?',
        'forward_confirm_body' => 'This will send the modification proposal to the customer for review. The booking status will move to Customer Review.',
        'forwarded_success' => 'Modification forwarded to customer.',
        'withdraw' => 'Withdraw Modification',
        'withdraw_confirm_title' => 'Withdraw This Modification?',
        'withdraw_confirm_body' => 'This will cancel the modification proposal. This action cannot be undone.',
        'withdrawn_success' => 'Modification withdrawn.',
        'action_failed' => 'Action failed.',
    ],
    'validation' => [
        'lane_required' => 'The fulfillment step is required.',
        'lane_invalid' => 'The fulfillment step must be preparing, ready, in_progress, or completed.',
    ],
    'errors' => [
        'invalid_transition' => 'This fulfillment step is not allowed for the item in its current state.',
        'condition_photos_rental_only' => 'Condition photos can only be uploaded for rental items.',
        'response_deadline_expired' => 'Response deadline has expired. Contact admin to re-open the response window.',
        'payment_already_captured' => 'Payment has been captured. Modification requires admin intervention.',
        'booking_locked' => 'This booking is currently locked. Try again once the active operation releases it.',
        'booking_cancelled' => 'This booking has been cancelled and cannot be modified.',
        'booking_completed' => 'This booking is completed and cannot be modified.',
        'fulfillment_in_progress' => 'Fulfillment is in progress and the booking can no longer be modified.',
        'booking_not_modifiable' => 'This booking is no longer modifiable.',
        'below_coverage_minimum' => [
            'message' => 'One or more vendors require a higher subtotal in this delivery city.',
        ],
        'delivery_city_required' => [
            'message' => 'Delivery city is required.',
        ],
        'currency_mismatch' => [
            'message' => 'Coverage area currency does not match the booking currency.',
        ],
    ],
    'cancellation' => [
        'blocked' => [
            'already_cancelled' => 'This booking is already cancelled.',
            'booking_completed' => 'A completed booking cannot be cancelled.',
            'booking_active' => 'The booking is in progress and can no longer be cancelled.',
        ],
    ],
];
