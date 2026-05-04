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

    'event_keys' => [
        'booking_confirmed' => 'تم تأكيد الحجز',
        'booking_submitted' => 'تم تقديم الحجز',
        'review_requested' => 'طلب تقييم',
        'digital_delivered' => 'تم تسليم العنصر الرقمي',
        'payment_captured' => 'تم تحصيل الدفعة',
        'refund_completed' => 'تم استرداد المبلغ',
    ],

    'categories' => [
        'booking' => 'الحجز',
        'marketing' => 'التسويق',
        'system' => 'النظام',
        'chat' => 'الدردشة',
        'payment' => 'الدفع',
        'review' => 'التقييم',
    ],

    'nav' => [
        'campaigns' => 'الحملات التسويقية',
        'notification_templates' => 'قوالب الإشعارات',
        'dispatches' => 'سجلات الإرسال',
        'preferences' => 'تفضيلات الإشعارات',
    ],

    'models' => [
        'campaign' => [
            'singular' => 'حملة',
            'plural' => 'الحملات التسويقية',
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
        'public_id' => 'المعرف العام',
        'template' => 'القالب',
        'channel' => 'قناة التوصيل',
        'status' => 'الحالة',
        'locale' => 'اللغة',
        'provider' => 'المزود',
        'user_id' => 'المستخدم',
        'event_category' => 'فئة الحدث',
        'is_enabled' => 'مفعّل',
        'quiet_hours_start' => 'بداية ساعات الهدوء',
        'quiet_hours_end' => 'نهاية ساعات الهدوء',
        'timezone' => 'المنطقة الزمنية',
    ],
];
