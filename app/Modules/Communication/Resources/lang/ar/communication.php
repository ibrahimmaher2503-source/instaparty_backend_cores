<?php

declare(strict_types=1);

return [
    'validation' => [
        'system_notifications_cannot_be_disabled' => 'لا يمكن تعطيل إشعارات النظام.',
        'quiet_hours_end_required' => 'وقت انتهاء ساعات الهدوء مطلوب عند تحديد وقت البدء.',
        'invalid_time_format' => 'يجب أن يكون الوقت بصيغة HH:MM.',
    ],

    'resource' => [
        'notification_templates' => 'قوالب الإشعارات',
        'event_key' => 'مفتاح الحدث',
        'channel' => 'القناة',
        'audience' => 'الجمهور',
        'body' => 'النص',
        'subject' => 'الموضوع',
        'variables' => 'المتغيرات',
        'is_active' => 'مفعّل',
    ],

    'channels' => [
        'push' => 'إشعار فوري',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'email' => 'بريد إلكتروني',
        'in_app' => 'داخل التطبيق',
    ],

    'categories' => [
        'booking' => 'الحجز',
        'marketing' => 'التسويق',
        'system' => 'النظام',
        'chat' => 'الدردشة',
        'payment' => 'الدفع',
        'review' => 'التقييم',
    ],
];
