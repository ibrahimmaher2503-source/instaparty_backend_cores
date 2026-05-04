<?php

declare(strict_types=1);

return [
    'nav' => [
        'commissions' => 'العمولات',
        'commission_rules' => 'قواعد العمولات',
        'wallet_ledger' => 'سجل المحفظة',
        'withdrawals_queue' => 'طلبات السحب',
        'settlement_runs' => 'دورات التسوية',
    ],

    'models' => [
        'commission' => [
            'singular' => 'عمولة',
            'plural' => 'العمولات',
        ],
        'commission_rule' => [
            'singular' => 'قاعدة عمولة',
            'plural' => 'قواعد العمولات',
        ],
        'wallet' => [
            'singular' => 'محفظة',
            'plural' => 'المحافظ',
        ],
        'withdrawal' => [
            'singular' => 'طلب سحب',
            'plural' => 'طلبات السحب',
        ],
        'settlement_run' => [
            'singular' => 'دورة تسوية',
            'plural' => 'دورات التسوية',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking_item' => 'عنصر الحجز',
        'payment' => 'عملية الدفع',
        'vendor' => 'المورّد',
        'product_type' => 'نوع المنتج',
        'gross_amount' => 'إجمالي المبلغ',
        'commission_amount' => 'قيمة العمولة',
        'vendor_share' => 'مستحقات المورد',
        'status' => 'الحالة',
        'period_start' => 'بداية الفترة',
        'period_end' => 'نهاية الفترة',
        'total_gross' => 'إجمالي المبالغ',
        'total_commission' => 'إجمالي العمولات',
        'total_vendor_share' => 'إجمالي مستحقات الموردين',
    ],

    'status' => [
        'calculated' => 'محسوبة',
        'partially_reversed' => 'معكوسة جزئياً',
        'reversed' => 'معكوسة بالكامل',
        'pending' => 'قيد الانتظار',
        'reconciled' => 'تمت المطابقة',
        'disputed' => 'محل نزاع',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
    ],

    'errors' => [
        'insufficient_balance' => 'رصيد المحفظة غير كافٍ. المتاح: :available :currency، والمطلوب: :requested :currency.',
        'existing_pending_withdrawal' => 'لديك طلب سحب قيد الانتظار بالفعل (:public_id). يرجى انتظار معالجته قبل تقديم طلب جديد.',
        'below_minimum_amount' => 'مبلغ السحب أقل من الحد الأدنى وهو :minimum :currency.',
        'negative_balance_blocked' => 'رصيد محفظتك سالب. تم إيقاف طلبات السحب حتى تتم تسوية الرصيد.',
        'withdrawal_not_found' => 'طلب السحب غير موجود.',
        'no_wallet_access' => 'ليس لديك صلاحية الوصول إلى هذه المحفظة.',
        'per_page_too_large' => 'يجب ألا تزيد قيمة per_page عن 100.',
        'invalid_iban' => 'رقم IBAN المُدخل غير صالح.',
    ],

    'ledger' => [
        'commission_credit' => 'إضافة عمولة لعنصر الحجز رقم :booking_item_id',
        'refund_debit' => 'خصم استرداد لعنصر الحجز رقم :booking_item_id',
        'withdrawal_debit' => 'سحب مدفوع إلى الحساب البنكي المنتهي بـ ...:iban_last3',
        'manual_adjustment' => 'تعديل يدوي: :note',
    ],

    'withdrawal_status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
    ],

    'commission_status' => [
        'calculated' => 'محسوبة',
        'partially_reversed' => 'معكوسة جزئياً',
        'reversed' => 'معكوسة بالكامل',
    ],

    'settlement_run_status' => [
        'pending' => 'قيد الانتظار',
        'reconciled' => 'تمت المطابقة',
        'disputed' => 'محل نزاع',
    ],
];