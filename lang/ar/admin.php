<?php

declare(strict_types=1);

return [
    'nav' => [
        'groups' => [
            'geography' => 'الجغرافيا',
            'identity' => 'الهوية',
            'vendor_onboarding' => 'تسجيل الموردين',
            'catalog' => 'الكتالوج',
            'booking' => 'الحجوزات',
            'payments' => 'المدفوعات',
            'reports' => 'التقارير',
            'settings' => 'الإعدادات',
        ],
    ],

    'actions' => [
        'view' => 'عرض',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'create' => 'إنشاء',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'suspend' => 'إيقاف',
        'review' => 'مراجعة',
        'download' => 'تنزيل',
        'revoke' => 'سحب',
        'approve_for_type' => 'الموافقة على نوع',
        'revoke_type' => 'سحب الموافقة على نوع',
    ],

    'common' => [
        'id' => 'المعرف',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'status' => 'الحالة',
        'active' => 'مفعّل',
        'inactive' => 'معطّل',
        'yes' => 'نعم',
        'no' => 'لا',
        'no_results' => 'لا توجد نتائج',
        'untranslated' => 'غير مترجم',
    ],

    'empty_states' => [
        'no_records' => 'لا توجد سجلات',
        'no_pending_vendors' => 'لا يوجد موردون قيد المراجعة',
        'no_pending_vendors_description' => 'تمت مراجعة جميع تسجيلات الموردين.',
    ],
];
