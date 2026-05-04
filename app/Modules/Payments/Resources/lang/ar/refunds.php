<?php

declare(strict_types=1);

return [
    'nav' => [
        'refunds' => 'المبالغ المستردة',
    ],
    'models' => [
        'refund' => [
            'singular' => 'مبلغ مسترد',
            'plural' => 'المبالغ المستردة',
        ],
    ],
    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking' => 'الحجز',
        'payment' => 'الدفعة',
        'amount' => 'المبلغ',
        'reason_code' => 'سبب الاسترداد',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
    ],
    'reason_code' => [
        'customer_request' => 'طلب العميل',
        'vendor_cancellation' => 'إلغاء مقدم الخدمة',
        'service_unavailable' => 'الخدمة غير متاحة',
        'duplicate_charge' => 'رسوم مكررة',
        'admin_discretion' => 'قرار إداري',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],
    'policy' => [
        'allowed' => 'مسموح',
        'rental_window_closed' => 'انتهت مهلة استرداد الإيجار.',
        'rental_in_setup' => 'بدأ تجهيز الإيجار بالفعل.',
        'sale_in_preparation' => 'عنصر البيع قيد التحضير بالفعل.',
        'digital_post_delivery' => 'لا يمكن استرداد العناصر الرقمية بعد التسليم.',
    ],
    'errors' => [
        'partial_refund_unsupported' => 'الاسترداد الجزئي غير مدعوم في المرحلة الأولى.',
    ],
];
