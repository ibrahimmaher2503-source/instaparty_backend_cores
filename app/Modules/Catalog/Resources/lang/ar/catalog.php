<?php

declare(strict_types=1);

return [
    // Navigation
    'nav_group_services' => 'الخدمات',
    'nav_group_catalog'  => 'الكتالوج',

    // Service statuses
    'status_draft'          => 'مسودة',
    'status_pending_review' => 'قيد المراجعة',
    'status_published'      => 'منشور',
    'status_rejected'       => 'مرفوض',
    'status_archived'       => 'مؤرشف',

    // Product types
    'product_type_rental'  => 'إيجار',
    'product_type_sale'    => 'بيع',
    'product_type_digital' => 'رقمي',

    // Table / Form labels
    'code'                   => 'الكود',
    'name'                   => 'الاسم',
    'description'            => 'الوصف',
    'sort_order'             => 'ترتيب العرض',
    'is_active'              => 'نشط',
    'parent_category'        => 'التصنيف الأب',
    'no_parent'              => 'بدون أب (مستوى أعلى)',
    'allowed_product_types'  => 'أنواع المنتجات المسموح بها',
    'product_type'           => 'نوع المنتج',

    // Section headings
    'occasion_details'   => 'تفاصيل المناسبة',
    'category_details'   => 'تفاصيل التصنيف',

    // Shared service form
    'shared'             => 'المعلومات العامة',
    'category'           => 'التصنيف',
    'base_price'         => 'السعر الأساسي (قرش)',
    'status'             => 'الحالة',
    'is_featured'        => 'مميز',
    'vendor'             => 'البائع',
    'media'              => 'الوسائط / المعرض',

    // Rental detail form
    'rental_details'                  => 'تفاصيل الإيجار',
    'requires_electricity'            => 'يحتاج كهرباء',
    'requires_outdoor_space'          => 'يحتاج مساحة خارجية',
    'default_rental_duration_hours'   => 'مدة الإيجار الافتراضية (ساعات)',
    'setup_time_minutes'              => 'وقت التركيب (دقائق)',
    'teardown_time_minutes'           => 'وقت الفك (دقائق)',
    'security_deposit'                => 'تأمين (قرش)',
    'minimum_space_sqm'               => 'أدنى مساحة (متر مربع)',

    // Sale detail form
    'sale_details'            => 'تفاصيل البيع',
    'is_perishable'           => 'قابل للتلف',
    'is_made_to_order'        => 'يُصنع بالطلب',
    'lead_time_hours'         => 'وقت التجهيز (ساعات)',
    'stock_quantity'          => 'الكمية المتاحة',
    'stock_quantity_hint'     => 'اتركه فارغاً للكمية غير المحدودة',
    'customization_fields'    => 'حقول التخصيص',

    // Digital detail form
    'digital_details'                => 'التفاصيل الرقمية',
    'delivery_method'                => 'طريقة التسليم',
    'has_expiry'                     => 'له تاريخ انتهاء',
    'expiry_days_after_purchase'     => 'أيام الانتهاء بعد الشراء',
    'is_refundable_after_delivery'   => 'قابل للاسترداد بعد التسليم',
    'redemption_url_template'        => 'نموذج رابط الاستبدال',

    // Translatable content section
    'translatable_fields' => 'المحتوى (قابل للترجمة)',

    // Status nested array (for use in Filament filter options and display)
    'status' => [
        'draft'          => 'مسودة',
        'pending_review' => 'قيد المراجعة',
        'published'      => 'منشور',
        'archived'       => 'مؤرشف',
    ],

    // Excel import
    'import_file_label'       => 'ملف إكسل (.xlsx, .xls, .csv)',
    'import_button'           => 'استيراد',
    'import_no_vendor'        => 'لا يوجد ملف بائع مرتبط بحسابك.',
    'import_success'          => 'تم استيراد :count خدمة بنجاح.',
    'import_failed'           => 'فشل الاستيراد. يرجى مراجعة الأخطاء أدناه.',
    'imported_rows'           => 'تم استيراد :count صف بنجاح.',
    'import_failed_rows'      => 'فشل الاستيراد بـ :count خطأ.',
    'row'                     => 'الصف',
    'field'                   => 'الحقل',
    'error'                   => 'الخطأ',
];
