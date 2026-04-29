<?php

declare(strict_types=1);

return [
    // General
    'vendor_profile' => 'الملف التجاري',
    'vendor_profiles' => 'الملفات التجارية',
    'vendor_document' => 'مستند المورد',
    'vendor_documents' => 'مستندات الموردين',
    'customer' => 'العميل',
    'vendor' => 'المورد',
    'admin' => 'المسؤول',

    // Navigation labels
    'nav' => [
        'queue' => 'قائمة الموافقات',
        'all_vendors' => 'جميع الموردين',
        'documents' => 'المستندات',
    ],

    // Infolist / form section titles
    'sections' => [
        'identity' => 'الهوية',
        'business_profile' => 'بيانات النشاط',
        'banking' => 'البيانات البنكية',
        'uploaded_documents' => 'المستندات المرفوعة',
        'approved_product_types' => 'أنواع المنتجات المعتمدة',
    ],

    // Table column labels
    'columns' => [
        'id' => 'المعرف',
        'business_name' => 'اسم النشاط',
        'business_type' => 'نوع النشاط',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'phone_verified' => 'تم التحقق من الهاتف',
        'docs' => 'المستندات',
        'vendor' => 'المورد',
        'doc_type' => 'نوع المستند',
        'file_name' => 'اسم الملف',
        'status' => 'الحالة',
        'approval_status' => 'حالة الموافقة',
        'created_at' => 'تاريخ الإنشاء',
        'download' => 'تنزيل',
        'slug' => 'المعرف النصي',
        'governorate' => 'المحافظة',
        'city' => 'المدينة',
        'bank_name' => 'اسم البنك',
        'bank_account_holder' => 'صاحب الحساب',
        'bank_iban' => 'رقم IBAN',
        'bank_swift_bic' => 'رمز SWIFT / BIC',
        'active_type_approvals' => 'الموافقات الفعّالة على الأنواع',
    ],

    // Filament action labels (resource-specific)
    'actions' => [
        'review' => 'مراجعة',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'suspend' => 'إيقاف',
        'approve_for_type' => 'الموافقة على نوع',
        'revoke_type' => 'سحب الموافقة على نوع',
        'download' => 'عرض',
    ],

    // Form field labels (Filament forms in modals)
    'forms' => [
        'rejection_reason_en' => 'سبب الرفض (إنجليزي)',
        'rejection_reason_ar' => 'سبب الرفض (عربي)',
        'revoke_reason_en' => 'سبب السحب (إنجليزي)',
        'revoke_reason_ar' => 'سبب السحب (عربي)',
        'business_name_en' => 'اسم النشاط (إنجليزي)',
        'business_name_ar' => 'اسم النشاط (عربي)',
        'bio_en' => 'النبذة (إنجليزي)',
        'bio_ar' => 'النبذة (عربي)',
    ],

    // Notification titles (in-app toasts)
    'notifications' => [
        'vendor_approved' => 'تمت الموافقة على الملف التجاري',
        'vendor_rejected' => 'تم رفض الملف التجاري',
        'vendor_suspended' => 'تم إيقاف المورد',
        'type_approved' => 'تمت الموافقة على المورد لـ :type',
        'type_revoked' => 'تم سحب الموافقة على النوع',
        'signed_url_generated' => 'تم إنشاء رابط موقّع',
    ],

    // Placeholder strings shown when no data
    'placeholders' => [
        'not_verified' => '— غير مُتحقّق منه —',
        'none' => '— لا يوجد —',
        'dash' => '—',
        'no_documents' => 'لم يتم رفع أي مستندات',
        's3_not_configured' => '— لم يتم إعداد S3 —',
        'open_document' => 'فتح المستند',
    ],

    // Field labels
    'fields' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'password' => 'كلمة المرور',
        'business_name' => 'اسم النشاط التجاري',
        'business_type' => 'نوع النشاط',
        'bio' => 'نبذة تعريفية',
        'slug' => 'المعرف النصي',
        'doc_type' => 'نوع المستند',
        'file' => 'الملف',
        'city_id' => 'المدينة',
        'governorate_id' => 'المحافظة',
        'delivery_fee' => 'رسوم التوصيل',
        'min_order' => 'الحد الأدنى للطلب',
        'product_type' => 'نوع المنتج',
        'bank_iban' => 'رقم IBAN',
        'bank_name' => 'اسم البنك',
        'bank_account_holder' => 'صاحب الحساب',
        'bank_swift_bic' => 'رمز SWIFT / BIC',
        'bank_branch' => 'الفرع',
        'opens_at' => 'وقت الفتح',
        'closes_at' => 'وقت الإغلاق',
        'day_of_week' => 'يوم الأسبوع',
        'label' => 'التسمية',
        'address_line' => 'العنوان',
        'recipient_name' => 'اسم المستلم',
        'recipient_phone' => 'هاتف المستلم',
        'is_default' => 'تعيين كافتراضي',
    ],

    // Approval status labels
    'status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'موافق عليه',
        'rejected' => 'مرفوض',
        'suspended' => 'موقوف',
    ],

    // Document status labels
    'document_status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'موافق عليه',
        'rejected' => 'مرفوض',
    ],

    // Document type labels
    'document_type' => [
        'cr' => 'السجل التجاري',
        'tax_card' => 'البطاقة الضريبية',
        'national_id' => 'بطاقة الهوية الوطنية',
        'iban_proof' => 'إثبات IBAN',
        'other' => 'أخرى',
    ],

    // Business type labels
    'business_type' => [
        'individual' => 'فرد',
        'company' => 'شركة',
        'establishment' => 'مؤسسة',
    ],

    // Product type labels
    'product_type' => [
        'rental' => 'إيجار',
        'sale' => 'بيع',
        'digital' => 'رقمي',
    ],

    // Day of week labels
    'day_of_week' => [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ],

    // Validation messages
    'validation' => [
        'phone_e164' => 'يجب أن يكون رقم الهاتف بصيغة E.164 (مثال: +201001234567).',
        'otp_invalid' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
        'otp_throttled' => 'محاولات كثيرة جداً. يرجى المحاولة لاحقاً.',
        'duplicate_phone' => 'رقم الهاتف هذا مسجل مسبقاً.',
        'duplicate_email' => 'البريد الإلكتروني هذا مسجل مسبقاً.',
        'business_name_en_required' => 'اسم النشاط التجاري باللغة الإنجليزية مطلوب.',
        'business_name_ar_required' => 'اسم النشاط التجاري باللغة العربية مطلوب.',
        'city_not_found' => 'المدينة المحددة غير موجودة.',
        'doc_type_invalid' => 'نوع المستند غير صالح.',
        'file_too_large' => 'يجب ألا يتجاوز حجم الملف 10 ميغابايت.',
        'file_mime_invalid' => 'يُقبل فقط ملفات PDF وJPEG وPNG.',
        'vendor_not_approved' => 'يجب الموافقة على الملف التجاري أولاً قبل منح صلاحيات نوع المنتج.',
        'type_approval_not_found' => 'الموافقة على النوع غير موجودة أو تم سحبها مسبقاً.',
    ],

    // Success messages
    'messages' => [
        'registered' => 'تم التسجيل بنجاح. يرجى التحقق من رقم هاتفك.',
        'phone_verified' => 'تم التحقق من رقم الهاتف بنجاح.',
        'logged_in' => 'تم تسجيل الدخول بنجاح.',
        'logged_out' => 'تم تسجيل الخروج بنجاح.',
        'profile_updated' => 'تم تحديث الملف الشخصي بنجاح.',
        'document_uploaded' => 'تم رفع المستند بنجاح.',
        'coverage_area_added' => 'تم إضافة منطقة التغطية بنجاح.',
        'business_hours_updated' => 'تم تحديث ساعات العمل بنجاح.',
        'address_added' => 'تم إضافة العنوان بنجاح.',
        'address_deleted' => 'تم حذف العنوان.',
        'vendor_approved' => 'تمت الموافقة على الملف التجاري.',
        'vendor_rejected' => 'تم رفض الملف التجاري.',
        'vendor_suspended' => 'تم إيقاف المورد.',
        'type_approved' => 'تمت الموافقة على المورد لـ :type.',
        'type_revoked' => 'تم سحب الموافقة على النوع.',
    ],
];
