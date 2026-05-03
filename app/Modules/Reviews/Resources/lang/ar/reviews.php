<?php

declare(strict_types=1);

return [
    'verified_customer'    => 'Verified Customer',
    'verified_customer_ar' => 'عميل موثق',

    'moderation_status' => [
        'pending'  => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'hidden'   => 'مخفي',
    ],

    'review_type' => [
        'service' => 'تقييم الخدمة',
        'vendor'  => 'تقييم المورد',
    ],

    'errors' => [
        'booking_item_not_completed'              => 'يجب أن يكون عنصر الحجز مكتملاً قبل تقديم التقييم.',
        'booking_vendor_items_not_all_completed'  => 'يجب إكمال جميع عناصر هذا المورد قبل تقديم التقييم.',
        'review_already_exists'                   => 'يوجد تقييم بالفعل لهذا الحجز.',
        'forbidden_transition'                    => 'هذا الانتقال في حالة الإشراف غير مسموح به.',
        'not_found'                               => 'لم يتم العثور على التقييم.',
        'forbidden'                               => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
    ],

    'validation' => [
        'rating_required'     => 'التقييم مطلوب.',
        'rating_out_of_range' => 'يجب أن يكون التقييم بين 1 و 5.',
        'body_too_long'       => 'يجب ألا يتجاوز نص التقييم 2000 حرف.',
        'reason_required'     => 'السبب مطلوب عند رفض التقييم.',
    ],

    'actions' => [
        'approve'  => 'قبول',
        'reject'   => 'رفض',
        'hide'     => 'إخفاء',
        'restore'  => 'استعادة',
    ],

    'labels' => [
        'rating'             => 'التقييم',
        'body'               => 'نص التقييم',
        'locale'             => 'اللغة',
        'moderation_status'  => 'الحالة',
        'reviewer'           => 'المقيِّم',
        'moderated_by'       => 'راجعه',
        'moderated_at'       => 'تاريخ المراجعة',
        'rejection_reason'   => 'سبب الرفض',
        'review_type'        => 'نوع التقييم',
    ],

    'notifications' => [
        'approved_title'  => 'تم قبول التقييم',
        'rejected_title'  => 'تم رفض التقييم',
        'hidden_title'    => 'تم إخفاء التقييم',
    ],
];
