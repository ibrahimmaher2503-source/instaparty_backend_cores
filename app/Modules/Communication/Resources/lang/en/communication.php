<?php

declare(strict_types=1);

return [
    'validation' => [
        'system_notifications_cannot_be_disabled' => 'System notifications cannot be disabled.',
        'quiet_hours_end_required' => 'Quiet hours end time is required when a start time is provided.',
        'invalid_time_format' => 'Time must be in HH:MM format.',
    ],

    'resource' => [
        'notification_templates' => 'Notification Templates',
        'event_key' => 'Event Key',
        'channel' => 'Delivery Channel',
        'audience' => 'Target Audience',
        'body' => 'Message Body',
        'subject' => 'Subject Line',
        'variables' => 'Variables',
        'is_active' => 'Active',
    ],

    'channels' => [
        'push' => 'Push Notification',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'in_app' => 'In-App Notification',
    ],

    'event_keys' => [
        'booking_confirmed' => 'Booking Confirmed',
        'booking_submitted' => 'Booking Submitted',
        'review_requested' => 'Review Requested',
        'digital_delivered' => 'Digital Item Delivered',
        'payment_captured' => 'Payment Captured',
        'refund_completed' => 'Refund Completed',
    ],

    'categories' => [
        'booking' => 'Bookings',
        'marketing' => 'Marketing',
        'system' => 'System',
        'chat' => 'Chat',
        'payment' => 'Payments',
        'review' => 'Reviews',
    ],

    'nav' => [
        'campaigns' => 'Campaigns',
        'notification_templates' => 'Notification Templates',
        'dispatches' => 'Dispatch Logs',
        'preferences' => 'Notification Preferences',
    ],

    'models' => [
        'campaign' => [
            'singular' => 'Campaign',
            'plural' => 'Campaigns',
        ],
        'notification_template' => [
            'singular' => 'Notification Template',
            'plural' => 'Notification Templates',
        ],
        'notification_dispatch' => [
            'singular' => 'Notification Dispatch Log',
            'plural' => 'Notification Dispatch Logs',
        ],
        'notification_preference' => [
            'singular' => 'Notification Preference',
            'plural' => 'Notification Preferences',
        ],
    ],

    'created_at' => 'Created At',

    'inbox' => [
        'severity' => 'Severity',
        'title' => 'Title',
        'source' => 'Source',
        'status' => 'Status',
        'received_at' => 'Received At',
        'snooze_duration' => 'Snooze Duration',
        'reassign_to' => 'Reassign To',
        'marked_read' => 'Marked as read.',
        'snoozed' => 'Item snoozed.',
        'reassigned' => 'Item reassigned.',
        'resolved' => 'Item resolved.',
        'batch_resolved' => ':count item(s) resolved.',
        'actions' => [
            'mark_read' => 'Mark as Read',
            'snooze' => 'Snooze',
            'reassign' => 'Reassign',
            'resolve' => 'Resolve',
            'batch_resolve' => 'Batch Resolve',
        ],
    ],

    'routing' => [
        'event_key' => 'Event Key',
        'severity' => 'Severity',
        'route_to_role' => 'Route to Role',
        'route_to_admin' => 'Route to Admin',
        'is_active' => 'Active',
        'target' => 'Route Target',
        'role_or_admin_hint' => 'Set either a role or a specific admin — not both.',
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'template' => 'Template',
        'channel' => 'Delivery Channel',
        'status' => 'Status',
        'locale' => 'Language',
        'provider' => 'Provider',
        'user_id' => 'User',
        'event_category' => 'Event Category',
        'is_enabled' => 'Enabled',
        'quiet_hours_start' => 'Quiet Hours Start',
        'quiet_hours_end' => 'Quiet Hours End',
        'timezone' => 'Timezone',
    ],
];
