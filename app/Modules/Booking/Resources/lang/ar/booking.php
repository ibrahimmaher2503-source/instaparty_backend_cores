<?php

declare(strict_types=1);

return [
    'nav' => [
        'bookings' => 'الحجوزات',
        'negotiation_monitor' => 'مراقبة التفاوض',
        'modifications' => 'التعديلات',
        'state_transitions' => 'انتقالات الحالة',
    ],

    'models' => [
        'booking' => [
            'singular' => 'حجز',
            'plural' => 'الحجوزات',
        ],
        'negotiation_monitor' => [
            'singular' => 'مراقبة التفاوض',
            'plural' => 'مراقبة التفاوض',
        ],
        'modification' => [
            'singular' => 'تعديل حجز',
            'plural' => 'تعديلات الحجوزات',
        ],
        'state_transition' => [
            'singular' => 'انتقال حالة',
            'plural' => 'انتقالات الحالة',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرف العام',
        'reference_no' => 'رقم المرجع',
        'customer_id' => 'العميل',
        'occasion_id' => 'المناسبة',
        'lifecycle_status' => 'حالة دورة الحياة',
        'payment_status' => 'حالة الدفع',
        'fulfillment_status' => 'حالة التنفيذ',
        'total' => 'الإجمالي',
        'submitted_at' => 'تاريخ الإرسال',
        'nearest_deadline' => 'أقرب موعد نهائي',
        'booking_vendor' => 'مورد الحجز',
        'proposed_by' => 'مقدم الاقتراح',
        'proposal_kind' => 'نوع الاقتراح',
        'status' => 'الحالة',
        'expires_at' => 'ينتهي في',
        'transitionable_type' => 'نوع الكيان',
        'transitionable_id' => 'معرف الكيان',
        'from_state' => 'من الحالة',
        'to_state' => 'إلى الحالة',
        'triggered_by' => 'تم التشغيل بواسطة',
    ],

    'lifecycle_status' => [
        'draft' => 'مسودة',
        'submitted' => 'مُرسل',
        'vendor_review' => 'مراجعة المورد',
        'customer_review' => 'مراجعة العميل',
        'confirmed' => 'مؤكد',
        'active' => 'نشط',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    'payment_status' => [
        'pending' => 'قيد الانتظار',
        'authorized' => 'مصرح به',
        'captured' => 'متحصل',
        'failed' => 'فشل',
        'refunded' => 'مسترد',
        'partially_refunded' => 'مسترد جزئياً',
        'voided' => 'ملغي',
    ],

    'fulfillment_status' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    'force_cancel' => 'إلغاء إجباري',
    'force_cancel_reason' => 'سبب الإلغاء',
    'force_cancel_confirm_heading' => 'إلغاء هذا الحجز إجبارياً؟',
    'force_cancel_confirm_description' => 'سيؤدي ذلك إلى إلغاء الحجز فوراً وبدء أي استرداد مناسب. لا يمكن التراجع عن هذا الإجراء.',
    'force_cancelled_successfully' => 'تم إلغاء الحجز إجبارياً بنجاح.',
];
