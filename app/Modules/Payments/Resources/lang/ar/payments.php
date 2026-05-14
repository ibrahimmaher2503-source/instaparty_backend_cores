<?php

declare(strict_types=1);

return [
    'navigation' => [
        'payments' => 'المدفوعات',
        'refunds' => 'طلبات الاسترداد',
        'attempts' => 'محاولات الدفع',
        'webhook_logs' => 'سجلات Webhook',
        'idempotency_keys' => 'مفاتيح منع التكرار',
    ],

    'models' => [
        'payment' => [
            'singular' => 'عملية دفع',
            'plural' => 'المدفوعات',
        ],
        'payment_attempt' => [
            'singular' => 'محاولة دفع',
            'plural' => 'محاولات الدفع',
        ],
        'webhook_log' => [
            'singular' => 'سجل Webhook',
            'plural' => 'سجلات Webhook',
        ],
        'idempotency_key' => [
            'singular' => 'مفتاح منع التكرار',
            'plural' => 'مفاتيح منع التكرار',
        ],
        'refund' => [
            'singular' => 'طلب استرداد',
            'plural' => 'طلبات الاسترداد',
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
        'payment' => 'عملية الدفع',
        'attempt_no' => 'رقم المحاولة',
        'http_status' => 'حالة HTTP',
        'event_type' => 'نوع الحدث',
        'signature_valid' => 'التوقيع صحيح',
        'processed_at' => 'تاريخ المعالجة',
        'key' => 'المفتاح',
        'route' => 'المسار',
        'response_status' => 'حالة الاستجابة',
        'expires_at' => 'ينتهي في',
    ],

    'status' => [
        'pending' => 'قيد الانتظار',
        'authorized' => 'تم التفويض',
        'captured' => 'تم التحصيل',
        'failed' => 'فشل',
        'refunded' => 'تم رد المبلغ',
    ],
];
