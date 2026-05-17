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

    'intervention' => [
        'nav_label' => 'تدخل الحجوزات',
        'page_title' => 'الحجوزات الإشكالية',
        'columns' => [
            'reference' => 'المرجع',
            'customer' => 'العميل',
            'product_type' => 'نوع المنتج',
            'lifecycle_status' => 'حالة الحجز',
            'payment_status' => 'حالة الدفع',
            'fulfillment_status' => 'حالة التنفيذ',
            'trouble_type' => 'نوع المشكلة',
            'nearest_deadline' => 'الموعد النهائي',
            'total' => 'الإجمالي',
        ],
        'trouble' => [
            'late_vendor_response' => 'تأخر المورد',
            'all_vendors_rejected' => 'رفض الجميع',
            'customer_review_pending' => 'مراجعة معلقة',
            'stalled' => 'متوقف',
        ],
        'actions' => [
            'send_vendor_reminder' => 'إرسال تذكير',
            'escalate_vendor_timeout' => 'تصعيد انتهاء المهلة',
            'suggest_alternative_vendors' => 'اقتراح بدائل',
            'resume_customer_review' => 'استئناف المراجعة',
            'create_note' => 'إضافة ملاحظة',
            'freeze_chat' => 'تجميد المحادثة',
            'resume_chat' => 'استئناف المحادثة',
            'view' => 'عرض',
        ],
        'confirm' => [
            'send_vendor_reminder' => 'إرسال تذكير للمورد؟',
            'escalate_vendor_timeout' => 'تصعيد انتهاء مهلة المورد؟ سيتم تحديد حالة المورد كمنتهية المهلة.',
            'suggest_alternative_vendors' => 'اقتراح هؤلاء الموردين للعميل؟',
            'resume_customer_review' => 'إرسال تذكير مراجعة للعميل؟',
            'create_note' => 'حفظ هذه الملاحظة؟',
            'freeze_chat' => 'تجميد محادثة الحجز؟ سيتم إخطار جميع الأطراف.',
            'resume_chat' => 'استئناف محادثة الحجز؟ سيتم إخطار جميع الأطراف.',
        ],
        'success' => [
            'send_vendor_reminder' => 'تم إرسال تذكير المورد بنجاح.',
            'escalate_vendor_timeout' => 'تم تصعيد المورد إلى حالة انتهاء المهلة.',
            'suggest_alternative_vendors' => 'تم اقتراح موردين بديلين للعميل.',
            'resume_customer_review' => 'تم إرسال تذكير مراجعة العميل.',
            'create_note' => 'تم حفظ الملاحظة.',
            'freeze_chat' => 'تم تجميد محادثة الحجز بنجاح.',
            'resume_chat' => 'تم استئناف محادثة الحجز بنجاح.',
        ],
        'error' => [
            'throttled' => 'تم تنفيذ هذا الإجراء مؤخراً. يرجى الانتظار قبل المحاولة مرة أخرى.',
            'vendor_not_pending' => 'المورد لم يعد في حالة الانتظار.',
            'deadline_not_passed' => 'لم ينقضِ الموعد النهائي للاستجابة بعد.',
            'no_open_modification' => 'لا يوجد تعديل مفتوح لهذا الحجز.',
            'chat_thread_not_found' => 'لم يتم العثور على محادثة لهذا الحجز.',
            'chat_already_frozen' => 'محادثة الحجز مجمدة بالفعل.',
            'chat_not_frozen' => 'محادثة الحجز غير مجمدة حالياً.',
        ],
    ],

    'errors' => [
        'response_deadline_expired' => 'انتهت مهلة الرد. تواصل مع الإدارة لإعادة فتح نافذة الرد.',
        'payment_already_captured'  => 'تم تحصيل الدفع. التعديل يتطلب تدخل المسؤول.',
        'booking_locked'            => 'هذا الحجز مغلق حاليًا. حاول مرة أخرى بعد انتهاء العملية الجارية.',
        'booking_cancelled'         => 'تم إلغاء هذا الحجز ولا يمكن تعديله.',
        'booking_completed'         => 'تم إكمال هذا الحجز ولا يمكن تعديله.',
        'fulfillment_in_progress'   => 'التنفيذ جارٍ ولا يمكن تعديل الحجز.',
        'booking_not_modifiable'    => 'لم يعد هذا الحجز قابلاً للتعديل.',
    ],
];
