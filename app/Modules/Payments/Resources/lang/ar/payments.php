<?php

declare(strict_types=1);

return [
    'navigation' => [
        'payments' => 'المدفوعات',
        'refunds' => 'المبالغ المستردة',
        'attempts' => 'محاولات الدفع',
        'webhook_logs' => 'سجلات Webhook',
        'idempotency_keys' => 'مفاتيح الإيدمبوتنسي',
    ],

    'models' => [
        'payment' => [
            'singular' => 'الدفعة',
            'plural' => 'المدفوعات',
        ],
        'payment_attempt' => [
            'singular' => 'محاولة الدفع',
            'plural' => 'محاولات الدفع',
        ],
        'webhook_log' => [
            'singular' => 'سجل Webhook',
            'plural' => 'سجلات Webhook',
        ],
        'idempotency_key' => [
            'singular' => 'مفتاح الإيدمبوتنسي',
            'plural' => 'مفاتيح الإيدمبوتنسي',
        ],
        'refund' => [
            'singular' => 'مبلغ مسترد',
            'plural' => 'المبالغ المستردة',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking' => 'الحجز',
        'gateway' => 'بوابة الدفع',
        'amount' => 'المبلغ',
        'method' => 'طريقة الدفع',
        'status' => 'الحالة',
        'captured_at' => 'تاريخ التحصيل',
        'payment' => 'الدفعة',
        'attempt_no' => 'رقم المحاولة',
        'http_status' => 'حالة HTTP',
        'event_type' => 'نوع الحدث',
        'signature_valid' => 'التوقيع صالح',
        'processed_at' => 'تاريخ المعالجة',
        'key' => 'المفتاح',
        'route' => 'المسار',
        'response_status' => 'حالة الاستجابة',
        'expires_at' => 'تاريخ الانتهاء',
    ],

    'event_types' => [
        'payment_captured' => 'تم تحصيل الدفعة',
        'payment_failed' => 'فشل الدفع',
        'payment_authorized' => 'تم تصريح الدفعة',
        'refund_completed' => 'تم استرداد المبلغ',
        'refund_failed' => 'فشل الاسترداد',
    ],

    'status' => [
        'pending' => 'قيد الانتظار',
        'authorized' => 'مُصرَّح به',
        'captured' => 'تم التحصيل',
        'failed' => 'فشل',
        'refunded' => 'مسترد',
    ],
];
