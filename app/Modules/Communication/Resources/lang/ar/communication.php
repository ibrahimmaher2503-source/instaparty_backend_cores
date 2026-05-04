<?php

declare(strict_types=1);

return [
    'validation' => [
        'system_notifications_cannot_be_disabled' => 'لا يمكن تعطيل إشعارات النظام.',
        'quiet_hours_end_required' => 'وقت انتهاء ساعات الهدوء مطلوب عند تحديد وقت البداية.',
        'invalid_time_format' => 'يجب أن يكون الوقت بصيغة HH:MM.',
    ],

    'resource' => [
        'notification_templates' => 'قوالب الإشعارات',
        'event_key' => 'مفتاح الحدث',
        'channel' => 'قناة الإرسال',
        'audience' => 'الجمهور المستهدف',
        'body' => 'محتوى الرسالة',
        'subject' => 'عنوان الرسالة',
        'variables' => 'المتغيرات',
        'is_active' => 'نشط',
    ],

    'channels' => [
        'push' => 'إشعار فوري',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'email' => 'بريد إلكتروني',
        'in_app' => 'داخل التطبيق',
    ],

    'categories' => [
        'booking' => 'الحجوزات',
        'marketing' => 'التسويق',
        'system' => 'النظام',
        'chat' => 'المحادثات',
        'payment' => 'المدفوعات',
        'review' => 'التقييمات',
    ],

    'nav' => [
        'campaigns' => 'الحملات',
        'notification_templates' => 'قوالب الإشعارات',
        'dispatches' => 'سجلات الإرسال',
        'preferences' => 'تفضيلات الإشعارات',
    ],

    'models' => [
        'campaign' => [
            'singular' => 'حملة',
            'plural' => 'الحملات',
        ],
        'notification_template' => [
            'singular' => 'قالب إشعار',
            'plural' => 'قوالب الإشعارات',
        ],
        'notification_dispatch' => [
            'singular' => 'سجل إرسال إشعار',
            'plural' => 'سجلات إرسال الإشعارات',
        ],
        'notification_preference' => [
            'singular' => 'تفضيل إشعار',
            'plural' => 'تفضيلات الإشعارات',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'template' => 'القالب',
        'channel' => 'قناة الإرسال',
        'status' => 'الحالة',
        'locale' => 'اللغة',
        'provider' => 'مزود الخدمة',
        'user_id' => 'المستخدم',
        'event_category' => 'تصنيف الحدث',
        'is_enabled' => 'مفعّل',
        'quiet_hours_start' => 'بداية ساعات الهدوء',
        'quiet_hours_end' => 'نهاية ساعات الهدوء',
        'timezone' => 'المنطقة الزمنية',
    ],
];