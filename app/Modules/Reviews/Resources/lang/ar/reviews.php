<?php

declare(strict_types=1);

return [
    'verified_customer' => 'عميل موثّق',
    'verified_customer_ar' => 'عميل موثّق',

    'nav' => [
        'service_reviews' => 'تقييمات الخدمات',
        'vendor_reviews' => 'تقييمات الموردين',
        'moderation_logs' => 'سجلات الإشراف',
        'review_moderation' => 'إشراف التقييمات',
    ],

    'models' => [
        'service_review' => [
            'singular' => 'تقييم خدمة',
            'plural' => 'تقييمات الخدمات',
        ],
        'vendor_review' => [
            'singular' => 'تقييم مورد',
            'plural' => 'تقييمات الموردين',
        ],
        'moderation_log' => [
            'singular' => 'سجل إشراف',
            'plural' => 'سجلات الإشراف',
        ],
        'review_response' => [
            'singular' => 'رد على تقييم',
            'plural' => 'الردود على التقييمات',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'service' => 'الخدمة',
        'vendor' => 'المورّد',
        'booking_item' => 'عنصر الحجز',
        'booking_vendor' => 'مورد الحجز',
        'review_type' => 'نوع التقييم',
        'rating' => 'التقييم',
        'locale' => 'اللغة',
        'moderation_status' => 'حالة الإشراف',
        'reviewer' => 'المقيّم',
        'review_id' => 'معرّف التقييم',
        'from_status' => 'من الحالة',
        'to_status' => 'إلى الحالة',
        'moderator' => 'المشرف',
    ],

    'moderation_status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'hidden' => 'مخفي',
    ],

    'review_type' => [
        'service' => 'تقييم خدمة',
        'vendor' => 'تقييم مورد',
    ],

    'errors' => [
        'booking_item_not_completed' => 'يجب أن يكون عنصر الحجز مكتملاً قبل تقديم التقييم.',
        'booking_vendor_items_not_all_completed' => 'يجب إكمال جميع عناصر هذا المورد قبل تقديم التقييم.',
        'review_already_exists' => 'يوجد تقييم بالفعل لهذا الحجز.',
        'forbidden_transition' => 'هذا الانتقال بين حالات الإشراف غير مسموح به.',
        'not_found' => 'لم يتم العثور على التقييم.',
        'forbidden' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
    ],

    'validation' => [
        'rating_required' => 'التقييم مطلوب.',
        'rating_out_of_range' => 'يجب أن يكون التقييم بين 1 و 5.',
        'body_too_long' => 'يجب ألا يتجاوز نص التقييم 2000 حرف.',
        'reason_required' => 'سبب الرفض مطلوب عند رفض التقييم.',
    ],

    'actions' => [
        'approve' => 'قبول',
        'reject' => 'رفض',
        'hide' => 'إخفاء',
        'restore' => 'استعادة',
    ],

    'labels' => [
        'rating' => 'التقييم',
        'body' => 'نص التقييم',
        'locale' => 'اللغة',
        'moderation_status' => 'حالة الإشراف',
        'reviewer' => 'المقيّم',
        'moderated_by' => 'تمت المراجعة بواسطة',
        'moderated_at' => 'تاريخ المراجعة',
        'rejection_reason' => 'سبب الرفض',
        'review_type' => 'نوع التقييم',
    ],

    'notifications' => [
        'approved_title' => 'تم قبول التقييم',
        'rejected_title' => 'تم رفض التقييم',
        'hidden_title' => 'تم إخفاء التقييم',
        'bulk_approved_title' => 'تم قبول التقييمات المحددة.',
    ],
];
