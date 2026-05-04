<?php

declare(strict_types=1);

return [
    'nav' => [
        'programs' => 'برامج الولاء',
        'rules' => 'قواعد الولاء',
        'redemptions' => 'استبدال النقاط',
        'ledger' => 'سجل النقاط',
    ],

    'models' => [
        'program' => [
            'singular' => 'برنامج ولاء',
            'plural' => 'برامج الولاء',
        ],
        'rule' => [
            'singular' => 'قاعدة ولاء',
            'plural' => 'قواعد الولاء',
        ],
        'redemption' => [
            'singular' => 'استبدال نقاط',
            'plural' => 'استبدالات النقاط',
        ],
        'ledger_entry' => [
            'singular' => 'قيد في سجل النقاط',
            'plural' => 'قيود سجل النقاط',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرف العام',
        'program' => 'البرنامج',
        'label' => 'التسمية',
        'min_points_to_redeem' => 'الحد الأدنى للاسترداد',
        'max_redeem_pct_bps' => 'الحد الأقصى لنسبة الاسترداد',
        'is_active' => 'نشط',
        'effective_from' => 'ساري منذ',
        'customer' => 'العميل',
        'vendor' => 'مقدم الخدمة',
        'points_held' => 'النقاط المحجوزة',
        'discount_minor' => 'مبلغ الخصم',
        'status' => 'الحالة',
        'applied_at' => 'تاريخ التطبيق',
        'voided_at' => 'تاريخ الإلغاء',
        'reversed_at' => 'تاريخ الاسترداد',
        'entry_type' => 'نوع القيد',
        'points' => 'النقاط',
        'reason' => 'السبب',
    ],

    'reason' => [
        'earn' => 'نقاط مكتسبة على حجز مكتمل',
        'redeem' => 'نقاط مستردة على حجز',
        'reversal' => 'نقاط معكوسة بسبب استرداد مدفوع',
        'void_release' => 'نقاط محجوزة تم الإفراج عنها — تم إلغاء الحجز',
    ],
    'errors' => [
        'insufficient_balance' => 'ليس لديك نقاط كافية لإتمام عملية الاسترداد.',
        'below_min_threshold' => 'تحتاج إلى :min نقطة على الأقل للاسترداد.',
        'exceeds_max_pct' => 'لا يمكن أن يتجاوز الاسترداد :pct٪ من إجمالي طلبك.',
        'cross_vendor_forbidden' => 'لا يمكن استخدام النقاط المكتسبة من بائع مع بائع آخر.',
        'no_active_program' => 'لا يمتلك هذا البائع برنامج ولاء نشط.',
        'program_not_found' => 'برنامج الولاء غير موجود.',
        'redemption_not_found' => 'الاسترداد غير موجود.',
        'already_redeemed' => 'يوجد استرداد نشط بالفعل لهذا الحجز.',
    ],
    'status' => [
        'active' => 'نشط',
        'paused' => 'موقوف مؤقتاً',
        'archived' => 'مؤرشف',
    ],
];
