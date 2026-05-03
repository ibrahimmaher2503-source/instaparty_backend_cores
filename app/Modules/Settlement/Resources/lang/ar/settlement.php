<?php

declare(strict_types=1);

return [
    'errors' => [
        'insufficient_balance' => 'رصيد المحفظة غير كافٍ. المتاح: :available :currency، المطلوب: :requested :currency.',
        'existing_pending_withdrawal' => 'لديك طلب سحب قيد الانتظار بالفعل (:public_id). انتظر حتى تتم معالجته قبل تقديم طلب آخر.',
        'below_minimum_amount' => 'مبلغ السحب أقل من الحد الأدنى البالغ :minimum :currency.',
        'negative_balance_blocked' => 'رصيد محفظتك سالب. طلبات السحب محظورة حتى يتم استعادة الرصيد.',
        'withdrawal_not_found' => 'طلب السحب غير موجود.',
        'no_wallet_access' => 'ليس لديك صلاحية الوصول إلى هذه المحفظة.',
        'per_page_too_large' => 'يجب أن يكون per_page 100 أو أقل.',
        'invalid_iban' => 'رقم الآيبان المُدخل غير صالح.',
    ],
    'ledger' => [
        'commission_credit' => 'إيداع عمولة لعنصر الحجز رقم :booking_item_id',
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
        'calculated' => 'محسوب',
        'partially_reversed' => 'مُعاد جزئياً',
        'reversed' => 'مُعاد بالكامل',
    ],
    'settlement_run_status' => [
        'pending' => 'قيد الانتظار',
        'reconciled' => 'مُوفَّق',
        'disputed' => 'متنازع عليه',
    ],
];
