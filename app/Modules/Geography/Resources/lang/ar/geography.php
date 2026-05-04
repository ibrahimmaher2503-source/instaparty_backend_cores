<?php

declare(strict_types=1);

return [
    'country' => 'الدولة',
    'governorate' => 'المحافظة',
    'region' => 'المنطقة',
    'city' => 'المدينة',

    // Plurals (for Filament navigation labels and model labels)
    'countries' => 'الدول',
    'governorates' => 'المحافظات',
    'regions' => 'المناطق',
    'cities' => 'المدن',

    // Table column labels
    'columns' => [
        'name' => 'الاسم',
        'name_en' => 'الاسم (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'code' => 'الرمز',
        'iso2' => 'ISO2',
        'iso3' => 'ISO3',
        'default_currency' => 'العملة الافتراضية',
        'default_locale' => 'اللغة الافتراضية',
        'default_timezone' => 'المنطقة الزمنية الافتراضية',
        'phone_code' => 'رمز الهاتف',
        'country' => 'الدولة',
        'governorate' => 'المحافظة',
        'region' => 'المنطقة',
        'sort_order' => 'ترتيب الفرز',
        'is_active' => 'نشط',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'created_at' => 'تاريخ الإنشاء',
    ],

    'errors' => [
        'delete_region_has_cities' => 'لا يمكن حذف هذه المنطقة لأنها تحتوي على مدن مرتبطة بها.',
        'delete_governorate_has_regions' => 'لا يمكن حذف هذه المحافظة لأنها تحتوي على مناطق مرتبطة بها.',
    ],

    // Filter labels
    'filters' => [
        'is_active' => 'مفعّل',
        'country' => 'الدولة',
        'governorate' => 'المحافظة',
        'region' => 'المنطقة',
    ],
];
