<?php

return [
    'reason_code' => [
        'customer_request' => 'طلب العميل',
        'vendor_cancellation' => 'إلغاء من البائع',
        'service_unavailable' => 'الخدمة غير متاحة',
        'duplicate_charge' => 'خصم مكرر',
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
        'rental_window_closed' => 'انتهت نافذة استرجاع التأجير.',
        'rental_in_setup' => 'بدأ تجهيز عنصر التأجير.',
        'sale_in_preparation' => 'عنصر البيع قيد التحضير.',
        'digital_post_delivery' => 'لا يمكن استرجاع المنتج الرقمي بعد التسليم.',
    ],
    'errors' => [
        'partial_refund_unsupported' => 'الاسترجاع الجزئي غير مدعوم في المرحلة الأولى.',
    ],
];
