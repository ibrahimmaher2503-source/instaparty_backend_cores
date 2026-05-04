<?php

declare(strict_types=1);

return [
    'plan' => [
        'free'    => 'مجاني',
        'silver'  => 'فضي',
        'gold'    => 'ذهبي',
        'premium' => 'مميز',
    ],
    'status' => [
        'active'     => 'نشط',
        'past_due'   => 'متأخر السداد',
        'cancelled'  => 'ملغى',
        'expired'    => 'منتهي',
        'superseded' => 'مستبدل',
    ],
    'billing_cycle' => [
        'monthly' => 'شهري',
        'yearly'  => 'سنوي',
        'none'    => 'بدون',
    ],
    'errors' => [
        'limit_reached' => 'لقد وصلت إلى حد :feature في خطة :plan. قم بالترقية إلى :unblocking_plan لفتح المزيد.',
        'cannot_subscribe_free' => 'تُضاف خطة المجاني تلقائياً ولا يمكن الاشتراك بها مباشرة.',
        'subscription_not_cancellable' => 'لا يمكن إلغاء اشتراكك في حالته الحالية.',
    ],
];
