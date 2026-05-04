<?php

declare(strict_types=1);

return [
    'nav' => [
        'bookings' => 'الحجوزات',
        'negotiation_monitor' => 'متابعة التفاوض',
        'modifications' => 'تعديلات الحجوزات',
        'state_transitions' => 'تغييرات الحالة',
    ],

    'models' => [
        'booking' => [
            'singular' => 'حجز',
            'plural' => 'الحجوزات',
        ],
        'negotiation_monitor' => [
            'singular' => 'متابعة تفاوض',
            'plural' => 'متابعة التفاوض',
        ],
        'modification' => [
            'singular' => 'تعديل حجز',
            'plural' => 'تعديلات الحجوزات',
        ],
        'state_transition' => [
            'singular' => 'تغيير حالة',
            'plural' => 'تغييرات الحالة',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'reference_no' => 'رقم المرجع',
        'customer_id' => 'العميل',
        'occasion_id' => 'المناسبة',
        'lifecycle_status' => 'حالة الحجز',
        'payment_status' => 'حالة الدفع',
        'fulfillment_status' => 'حالة التنفيذ',
        'total' => 'الإجمالي',
        'submitted_at' => 'تاريخ الإرسال',
        'nearest_deadline' => 'أقرب موعد مستحق',
        'booking_vendor' => 'مورد الحجز',
        'proposed_by' => 'مقدّم الاقتراح',
        'proposal_kind' => 'نوع الاقتراح',
        'status' => 'الحالة',
        'expires_at' => 'تاريخ الانتهاء',
        'transitionable_type' => 'نوع الكيان المرتبط',
        'transitionable_id' => 'معرّف الكيان المرتبط',
        'from_state' => 'الحالة السابقة',
        'to_state' => 'الحالة الجديدة',
        'triggered_by' => 'تم بواسطة',
    ],

    'lifecycle_status' => [
        'draft' => 'مسودة',
        'submitted' => 'تم الإرسال',
        'vendor_review' => 'قيد مراجعة المورد',
        'customer_review' => 'قيد مراجعة العميل',
        'confirmed' => 'مؤكد',
        'active' => 'نشط',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],

    'payment_status' => [
        'pending' => 'قيد الانتظار',
        'authorized' => 'تم التفويض',
        'captured' => 'تم التحصيل',
        'failed' => 'فشل الدفع',
        'refunded' => 'تم رد المبلغ',
        'partially_refunded' => 'تم رد المبلغ جزئياً',
        'voided' => 'مُبطلة',
    ],

    'fulfillment_status' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],
];