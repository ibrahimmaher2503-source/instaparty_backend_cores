<?php

declare(strict_types=1);

return [
    'rows' => [
        'profile' => [
            'label' => 'الملف التجاري',
            'cta'   => 'استكمال الملف التجاري',
        ],
        'banking' => [
            'label' => 'البيانات البنكية',
            'cta'   => 'إضافة البيانات البنكية',
        ],
        'docs_uploaded' => [
            'label' => 'المستندات المرفوعة',
            'cta'   => 'رفع المستندات المطلوبة',
        ],
        'docs_approved' => [
            'label' => 'اعتماد المستندات',
            'cta'   => 'رفع نسخة جديدة',
        ],
        'coverage' => [
            'label' => 'منطقة التغطية',
            'cta'   => 'إضافة منطقة تغطية',
        ],
        'hours' => [
            'label' => 'ساعات العمل',
            'cta'   => 'تحديد ساعات العمل',
        ],
        'service_drafted' => [
            'label' => 'تمت إضافة أول خدمة',
            'cta'   => 'أضف خدمتك الأولى',
        ],
        'service_submitted' => [
            'label' => 'تم إرسال الخدمة للمراجعة',
            'cta'   => 'أرسل خدمة للمراجعة',
        ],
        'approval_status' => [
            'label' => 'اعتماد الحساب',
            'cta'   => null,
        ],
        'approved_types' => [
            'label' => 'أنواع المنتجات المعتمدة',
            'cta'   => null,
        ],
    ],

    'status' => [
        'complete' => 'مكتمل',
        'pending'  => 'قيد الانتظار',
        'warning'  => 'يحتاج انتباهاً',
        'danger'   => 'يحتاج إجراء',
        'info'     => 'قيد المراجعة',
    ],

    'progress' => 'اكتمل :done من :total',

    'cta' => [
        'next_action'      => 'الخطوة التالية الموصى بها',
        'onboarding_done'  => 'اكتمل التسجيل',
        'resubmit_profile' => 'تعديل الملف وإعادة الإرسال',
        'upload_new_copy'  => 'رفع نسخة جديدة',
    ],

    'banner' => [
        'rejected_heading'          => 'تم رفض حسابك',
        'changes_requested_heading' => 'مطلوب تعديلات',
        'suspended_heading'         => 'الحساب موقوف',
        'suspended_since'           => 'موقوف منذ :date',
    ],

    'approved_types' => [
        'sub_text' => 'معتمد لـ: :types',
        'rental'   => 'إيجار',
        'sale'     => 'بيع',
        'digital'  => 'رقمي',
    ],

    'approval_status' => [
        'pending'           => 'في انتظار مراجعة الإدارة',
        'approved'          => 'الحساب معتمد',
        'changes_requested' => 'طلبت الإدارة تعديلات',
        'rejected'          => 'تم رفض الحساب',
    ],
];
