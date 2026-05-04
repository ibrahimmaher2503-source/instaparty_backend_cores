<?php

declare(strict_types=1);

return [
    'nav' => [
        'programs' => 'برامج الولاء',
        'rules' => 'قواعد الولاء',
        'redemptions' => 'عمليات استبدال النقاط',
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
            'singular' => 'عملية استبدال نقاط',
            'plural' => 'عمليات استبدال النقاط',
        ],
        'ledger_entry' => [
            'singular' => 'قيد في سجل النقاط',
            'plural' => 'قيود سجل النقاط',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'program' => 'البرنامج',
        'label' => 'التسمية',
        'min_points_to_redeem' => 'الحد الأدنى للاستبدال',
        'max_redeem_pct_bps' => 'أقصى نسبة استبدال',
        'is_active' => 'نشط',
        'effective_from' => 'ساري من',
        'customer' => 'العميل',
        'vendor' => 'المورّد',
        'points_held' => 'النقاط المحجوزة',
        'discount_minor' => 'قيمة الخصم',
        'status' => 'الحالة',
        'applied_at' => 'تاريخ التطبيق',
        'voided_at' => 'تاريخ الإلغاء',
        'reversed_at' => 'تاريخ العكس',
        'entry_type' => 'نوع القيد',
        'points' => 'النقاط',
        'reason' => 'السبب',
    ],

    'reason' => [
        'earn' => 'نقاط مكتسبة عند إكمال الحجز',
        'redeem' => 'نقاط مستبدلة على الحجز',
        'reversal' => 'نقاط معكوسة بسبب رد مبلغ',
        'void_release' => 'تم تحرير النقاط المحجوزة بسبب إلغاء الحجز',
    ],

    'errors' => [
        'insufficient_balance' => 'لا يوجد لديك رصيد نقاط كافٍ لإتمام عملية الاستبدال.',
        'below_min_threshold' => 'يجب أن يكون لديك :min نقطة على الأقل للاستبدال.',
        'exceeds_max_pct' => 'لا يمكن أن تتجاوز قيمة الاستبدال :pct% من إجمالي الطلب.',
        'cross_vendor_forbidden' => 'لا يمكن استخدام النقاط المكتسبة من مورّد مع مورّد آخر.',
        'no_active_program' => 'لا يوجد لدى هذا المورّد برنامج ولاء نشط.',
        'program_not_found' => 'برنامج الولاء غير موجود.',
        'redemption_not_found' => 'عملية استبدال النقاط غير موجودة.',
        'already_redeemed' => 'توجد بالفعل عملية استبدال نقاط نشطة لهذا الحجز.',
    ],

    'status' => [
        'active' => 'نشط',
        'paused' => 'موقوف مؤقتاً',
        'archived' => 'مؤرشف',
    ],
];