<?php

declare(strict_types=1);

return [
    'validation' => [
        'system_notifications_cannot_be_disabled' => 'System notifications cannot be disabled.',
        'quiet_hours_end_required' => 'Quiet hours end time is required when start time is provided.',
        'invalid_time_format' => 'Time must be in HH:MM format.',
    ],

    'resource' => [
        'notification_templates' => 'Notification Templates',
        'event_key' => 'Event Key',
        'channel' => 'Channel',
        'audience' => 'Audience',
        'body' => 'Body',
        'subject' => 'Subject',
        'variables' => 'Variables',
        'is_active' => 'Active',
    ],

    'channels' => [
        'push' => 'Push Notification',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'in_app' => 'In-App',
    ],

    'categories' => [
        'booking' => 'Booking',
        'marketing' => 'Marketing',
        'system' => 'System',
        'chat' => 'Chat',
        'payment' => 'Payment',
        'review' => 'Review',
    ],
];
