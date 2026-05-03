<?php

declare(strict_types=1);

return [
    'reason' => [
        'earn'         => 'نقاط مكتسبة على حجز مكتمل',
        'redeem'       => 'نقاط مستردة على حجز',
        'reversal'     => 'نقاط معكوسة بسبب استرداد مدفوع',
        'void_release' => 'نقاط محجوزة تم الإفراج عنها — تم إلغاء الحجز',
    ],
    'errors' => [
        'insufficient_balance'   => 'ليس لديك نقاط كافية لإتمام عملية الاسترداد.',
        'below_min_threshold'    => 'تحتاج إلى :min نقطة على الأقل للاسترداد.',
        'exceeds_max_pct'        => 'لا يمكن أن يتجاوز الاسترداد :pct٪ من إجمالي طلبك.',
        'cross_vendor_forbidden' => 'لا يمكن استخدام النقاط المكتسبة من بائع مع بائع آخر.',
        'no_active_program'      => 'لا يمتلك هذا البائع برنامج ولاء نشط.',
        'program_not_found'      => 'برنامج الولاء غير موجود.',
        'redemption_not_found'   => 'الاسترداد غير موجود.',
        'already_redeemed'       => 'يوجد استرداد نشط بالفعل لهذا الحجز.',
    ],
    'status' => [
        'active'   => 'نشط',
        'paused'   => 'موقوف مؤقتاً',
        'archived' => 'مؤرشف',
    ],
];
