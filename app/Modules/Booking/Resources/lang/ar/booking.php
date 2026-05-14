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
        'customer_phone' => 'رقم الهاتف',
        'occasion_id' => 'المناسبة',
        'guest_count' => 'عدد الضيوف',
        'event_starts_at' => 'بداية الحدث',
        'event_ends_at' => 'نهاية الحدث',
        'lifecycle_status' => 'حالة الحجز',
        'payment_status' => 'حالة الدفع',
        'fulfillment_status' => 'حالة التنفيذ',
        'total' => 'الإجمالي',
        'amount_paid' => 'المبلغ المدفوع',
        'submitted_at' => 'تاريخ الإرسال',
        'vendor' => 'المورد',
        'vendor_status' => 'حالة المورد',
        'service' => 'الخدمة',
        'product_type' => 'نوع المنتج',
        'quantity' => 'الكمية',
        'line_total' => 'إجمالي السطر',
        'item_status' => 'حالة العنصر',
        'response_deadline' => 'الموعد النهائي للرد',
        'city' => 'المدينة',
        'address_line' => 'العنوان',
        'building' => 'المبنى',
        'floor' => 'الطابق',
        'apartment' => 'الشقة',
        'landmark' => 'علامة مميزة',
        'recipient_name' => 'المستلم',
        'recipient_phone' => 'هاتف المستلم',
        'version' => 'الإصدار',
        'trigger_kind' => 'نوع الحدث',
        'actor' => 'المنفذ',
        'context' => 'السياق',
        'snapshot' => 'اللقطة',
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

    'sections' => [
        'summary' => 'ملخص الحجز',
    ],

    'relations' => [
        'vendors' => 'موردو الحجز',
        'items' => 'عناصر الحجز',
        'addresses' => 'عناوين الحجز',
        'payments' => 'المدفوعات',
        'snapshots' => 'لقطات الحجز',
        'state_transitions' => 'انتقالات الحالة',
    ],

    'actions' => [
        'edit' => 'تعديل',
        'force_cancel' => 'إلغاء إجباري',
        'add_admin_note' => 'إضافة ملاحظة إدارية',
        'save_changes' => 'حفظ التغييرات',
    ],

    'modals' => [
        'force_cancel_title' => 'إلغاء الحجز إجباريًا',
        'force_cancel_description' => 'سيؤدي ذلك إلى إلغاء الحجز وتسجيل أثر تدقيقي.',
        'force_cancel_reason' => 'السبب',
        'admin_note_body' => 'الملاحظة الإدارية',
    ],

    'notifications' => [
        'booking_updated' => 'تم تحديث الحجز بنجاح.',
        'force_cancelled' => 'تم إلغاء الحجز إجباريًا بنجاح.',
        'admin_note_added' => 'تمت إضافة الملاحظة الإدارية بنجاح.',
    ],

    'empty_states' => [
        'vendors' => 'لا توجد سجلات موردين بعد.',
        'items' => 'لا توجد عناصر للحجز بعد.',
        'addresses' => 'لا يوجد عنوان للحجز بعد.',
        'payments' => 'لا توجد مدفوعات بعد.',
        'snapshots' => 'لا توجد لقطات بعد.',
        'state_transitions' => 'لا توجد انتقالات حالة بعد.',
    ],

    'vendor_status' => [
        'pending' => 'قيد الانتظار',
        'accepted' => 'تم القبول',
        'modified' => 'تم التعديل',
        'rejected' => 'مرفوض',
        'cancelled' => 'ملغي',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'timed_out' => 'انتهت المهلة',
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
        'unpaid' => 'غير مدفوع',
        'partial' => 'مدفوع جزئيًا',
        'paid' => 'مدفوع',
        'refund_pending' => 'في انتظار الاسترداد',
        'partially_refunded' => 'تم رد المبلغ جزئيًا',
        'refunded' => 'مسترد',
    ],

    'fulfillment_status' => [
        'not_started' => 'لم يبدأ',
        'in_progress' => 'قيد التنفيذ',
        'partially_completed' => 'مكتمل جزئيًا',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],

    'placeholders' => [
        'none' => '—',
    ],
];
