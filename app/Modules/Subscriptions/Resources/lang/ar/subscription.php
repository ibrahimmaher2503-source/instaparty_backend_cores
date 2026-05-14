<?php

declare(strict_types=1);

return [
    'nav_group' => 'الاشتراكات',
    'plans' => 'خطط الاشتراك',
    'vendor_subs' => 'اشتراكات الموردين',
    'invoices' => 'الفواتير',
    'payments' => 'المدفوعات',
    'audit' => 'سجل المراجعة',
    'override_tier' => 'تجاوز المستوى',
    'revoke_override' => 'إلغاء التجاوز',
    'override_reason_en' => 'سبب التجاوز (بالإنجليزية)',
    'override_reason_ar' => 'سبب التجاوز (بالعربية)',
    'override_expires_at' => 'تنتهي صلاحية التجاوز في (اختياري)',
    'past_due_stat' => 'اشتراكات متأخرة السداد',
    'past_due_invoices_require_attention' => 'الفواتير تتطلب اهتماماً فورياً',
    'vendor' => 'المورد',
    'expires_at' => 'تنتهي في',
    'plan' => [
        'free' => 'مجاني',
        'silver' => 'فضي',
        'gold' => 'ذهبي',
        'premium' => 'مميز',
    ],
    'status' => [
        'active' => 'نشط',
        'past_due' => 'متأخر السداد',
        'cancelled' => 'ملغى',
        'expired' => 'منتهي',
        'superseded' => 'مستبدل',
    ],
    'billing_cycle' => [
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
        'none' => 'بدون',
    ],
    'errors' => [
        'limit_reached' => 'لقد وصلت إلى حد :feature في خطة :plan. قم بالترقية إلى :unblocking_plan لفتح المزيد.',
        'cannot_subscribe_free' => 'تُضاف خطة المجاني تلقائياً ولا يمكن الاشتراك بها مباشرة.',
        'subscription_not_cancellable' => 'لا يمكن إلغاء اشتراكك في حالته الحالية.',
    ],
];
