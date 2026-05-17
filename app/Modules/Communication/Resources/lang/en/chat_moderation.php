<?php

declare(strict_types=1);

return [
    // Status pills
    'status' => [
        'open' => 'Open',
        'locked' => 'Locked',
        'closed' => 'Closed',
        'frozen' => 'Frozen by admin',
    ],

    // Action labels
    'actions' => [
        'freeze' => 'Freeze thread',
        'unfreeze' => 'Unfreeze thread',
        'resolve_flag' => 'Resolve flag',
        'mark_off_platform' => 'Mark as off-platform attempt',
        'escalate' => 'Escalate to admin inbox',
    ],

    // Modal field labels
    'fields' => [
        'reason_en' => 'Reason (English)',
        'reason_ar' => 'Reason (Arabic)',
        'category' => 'Category',
        'decision' => 'Decision',
        'note_en' => 'Note (English)',
        'note_ar' => 'Note (Arabic)',
        'note' => 'Note',
        'severity' => 'Severity',
        'summary_en' => 'Summary (English)',
        'summary_ar' => 'Summary (Arabic)',
        'summary' => 'Summary',
    ],

    // Category options
    'categories' => [
        'off_platform_contact' => 'Off-platform contact attempt',
        'policy_violation' => 'Policy violation',
        'harassment' => 'Harassment',
        'other' => 'Other',
    ],

    // Resolution decisions
    'decisions' => [
        'upheld_redact' => 'Upheld — redact message',
        'upheld_warn' => 'Upheld — warn user',
        'upheld_block' => 'Upheld — block user',
        'dismissed_false_positive' => 'Dismissed — false positive',
    ],

    // Flag types
    'flag_types' => [
        'phone' => 'Phone number',
        'email' => 'Email address',
        'profanity' => 'Profanity',
        'external_link' => 'External link',
        'other' => 'Other',
    ],

    // Severity options
    'severities' => [
        'info' => 'Informational',
        'warning' => 'Warning',
        'critical' => 'Critical',
    ],

    // 409 / business-rule errors
    'errors' => [
        'unfreeze_forbidden_outside_window' => 'This thread can no longer be unfrozen because the booking is past the review window.',
        'flag_already_resolved' => 'This flag has already been resolved and cannot be resolved again.',
    ],

    // Audit timeline labels
    'audit' => [
        'event_actor' => 'Actor',
        'event_reason' => 'Reason',
        'event_at' => 'When',
        'event_action' => 'Action',
    ],

    // Escalation
    'escalation_title' => 'Chat moderation flag escalated to admin inbox',

    // Redacted placeholder
    'redacted_placeholder' => '<message redacted by moderation>',

    // Navigation
    'nav' => [
        'restricted_chat' => 'Restricted Chat',
    ],

    'columns' => [
        'booking_ref' => 'Booking',
        'customer' => 'Customer',
        'vendor' => 'Vendor',
        'status' => 'Status',
        'flag_count' => 'Open flags',
        'last_message_at' => 'Last message',
        'frozen_by' => 'Frozen by',
        'frozen_at' => 'Frozen at',
        'product_type' => 'Product type',
    ],

    // Sections in detail view
    'sections' => [
        'booking_context' => 'Booking context',
        'messages' => 'Read-only messages',
        'message_body' => 'Message body',
        'audit_timeline' => 'Audit timeline',
    ],

    // Firestore message body placeholder (content lives in Firestore, not MySQL)
    'firestore_placeholder' => 'content in Firestore',

    // Filter labels
    'filters' => [
        'has_open_flags' => 'Has open flags',
        'product_type' => 'Product type',
        'last_message_from' => 'Last message from',
        'last_message_to' => 'Last message to',
    ],
];
