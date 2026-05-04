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
