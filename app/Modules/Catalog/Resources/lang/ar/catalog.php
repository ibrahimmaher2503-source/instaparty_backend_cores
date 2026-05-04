<?php

declare(strict_types=1);

return [
    // Navigation
    'nav_group_services' => 'الخدمات',
    'nav_group_catalog' => 'الكتالوج',

    'nav' => [
        'categories' => 'التصنيفات',
        'occasions' => 'المناسبات',
        'rental_services' => 'خدمات الإيجار',
        'sale_services' => 'خدمات البيع',
        'digital_services' => 'الخدمات الرقمية',
        'service_themes' => 'سمات الخدمات',
        'category_field_schemas' => 'مخططات حقول التصنيف',
        'excel_imports' => 'استيرادات Excel',
        'inventory_reservations' => 'حجوزات المخزون',
        'import_rental_services' => 'استيراد خدمات الإيجار',
        'import_sale_services' => 'استيراد خدمات البيع',
        'import_digital_services' => 'استيراد الخدمات الرقمية',
    ],

    'models' => [
        'category' => [
            'singular' => 'تصنيف',
            'plural' => 'التصنيفات',
        ],
        'occasion' => [
            'singular' => 'مناسبة',
            'plural' => 'المناسبات',
        ],
        'rental_service' => [
            'singular' => 'خدمة إيجار',
            'plural' => 'خدمات الإيجار',
        ],
        'sale_service' => [
            'singular' => 'خدمة بيع',
            'plural' => 'خدمات البيع',
        ],
        'digital_service' => [
            'singular' => 'خدمة رقمية',
            'plural' => 'الخدمات الرقمية',
        ],
        'service_theme' => [
            'singular' => 'سمة خدمة',
            'plural' => 'سمات الخدمات',
        ],
        'category_field_schema' => [
            'singular' => 'مخطط حقل التصنيف',
            'plural' => 'مخططات حقول التصنيف',
        ],
        'excel_import' => [
            'singular' => 'استيراد Excel',
            'plural' => 'استيرادات Excel',
        ],
        'inventory_reservation' => [
            'singular' => 'حجز مخزون',
            'plural' => 'حجوزات المخزون',
        ],
    ],

    // Import statuses
    'import_status' => [
        'pending' => 'قيد الانتظار',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],

    // Field types
    'field_types' => [
        'text' => 'نص',
        'number' => 'رقم',
        'boolean' => 'نعم/لا',
        'select' => 'اختيار مفرد',
        'multiselect' => 'اختيار متعدد',
        'date' => 'تاريخ',
    ],

    // Hold types
    'hold_types' => [
        'cart' => 'سلة التسوق',
        'payment' => 'الدفع',
    ],

    // Reservation statuses
    'reservation_status_held' => 'محجوز',
    'reservation_status_confirmed' => 'مؤكد',
    'reservation_status_expired' => 'منتهي',
    'reservation_status_released' => 'محرر',

    // Service statuses
    'status_draft' => 'مسودة',
    'status_pending_review' => 'قيد المراجعة',
    'status_published' => 'منشور',
    'status_rejected' => 'مرفوض',
    'status_archived' => 'مؤرشف',

    // Product types
    'product_type_rental' => 'إيجار',
    'product_type_sale' => 'بيع',
    'product_type_digital' => 'رقمي',

    // Table / Form labels
    'code' => 'الكود',
    'name' => 'الاسم',
    'description' => 'الوصف',
    'sort_order' => 'ترتيب العرض',
    'is_active' => 'نشط',
    'parent_category' => 'التصنيف الأب',
    'no_parent' => 'بدون أب (مستوى أعلى)',
    'allowed_product_types' => 'أنواع المنتجات المسموح بها',
    'product_type' => 'نوع المنتج',

    // Section headings
    'occasion_details' => 'تفاصيل المناسبة',
    'category_details' => 'تفاصيل التصنيف',
    'service_theme_details' => 'تفاصيل سمة الخدمة',
    'category_field_schema_details' => 'تفاصيل مخطط حقل التصنيف',

    // Category field schema
    'field_key' => 'مفتاح الحقل',
    'field_type' => 'نوع الحقل',
    'field_label' => 'تسمية الحقل',
    'field_label_en' => 'تسمية الحقل بالإنجليزية',
    'field_label_ar' => 'تسمية الحقل بالعربية',
    'advanced_schema' => 'مخطط متقدم',
    'is_required' => 'مطلوب',
    'is_filterable' => 'قابل للتصفية',
    'icon_path' => 'مسار الأيقونة',
    'options' => 'الخيارات',
    'validation_rules' => 'قواعد التحقق',

    // Shared service form
    'shared' => 'المعلومات العامة',
    'category' => 'التصنيف',
    'base_price' => 'السعر الأساسي (قرش)',
    'status_label' => 'الحالة',
    'is_featured' => 'مميز',
    'vendor' => 'مقدم الخدمة',
    'media' => 'الوسائط / المعرض',
    'service' => 'الخدمة',
    'short_description' => 'وصف مختصر',
    'long_description' => 'وصف مفصل',
    'name_en' => 'الاسم بالإنجليزية',
    'name_ar' => 'الاسم بالعربية',
    'user_id' => 'المستخدم',
    'quantity' => 'الكمية',
    'public_id' => 'المعرف العام',
    'original_filename' => 'اسم الملف الأصلي',
    'total_rows' => 'إجمالي الصفوف',
    'imported_rows_count' => 'الصفوف المستوردة',
    'error_rows' => 'صفوف الأخطاء',
    'hold_type' => 'نوع الحجز',
    'reserved_starts_at' => 'بداية الحجز',
    'reserved_ends_at' => 'نهاية الحجز',
    'expires_at' => 'تاريخ الانتهاء',

    // Rental detail form
    'rental_details' => 'تفاصيل الإيجار',
    'requires_electricity' => 'يحتاج كهرباء',
    'requires_outdoor_space' => 'يحتاج مساحة خارجية',
    'default_rental_duration_hours' => 'مدة الإيجار الافتراضية (ساعات)',
    'setup_time_minutes' => 'وقت التركيب (دقائق)',
    'teardown_time_minutes' => 'وقت الفك (دقائق)',
    'security_deposit' => 'تأمين (قرش)',
    'minimum_space_sqm' => 'أدنى مساحة (متر مربع)',

    // Sale detail form
    'sale_details' => 'تفاصيل البيع',
    'is_perishable' => 'قابل للتلف',
    'is_made_to_order' => 'يُصنع بالطلب',
    'lead_time_hours' => 'وقت التجهيز (ساعات)',
    'stock_quantity' => 'الكمية المتاحة',
    'stock_quantity_hint' => 'اتركه فارغاً للكمية غير المحدودة',
    'customization_fields' => 'حقول التخصيص',

    // Digital detail form
    'digital_details' => 'التفاصيل الرقمية',
    'delivery_method' => 'طريقة التسليم',
    'has_expiry' => 'له تاريخ انتهاء',
    'expiry_days_after_purchase' => 'أيام الانتهاء بعد الشراء',
    'is_refundable_after_delivery' => 'قابل للاسترداد بعد التسليم',
    'redemption_url_template' => 'نموذج رابط الاستبدال',

    // Translatable content section
    'translatable_fields' => 'المحتوى (قابل للترجمة)',

    // Status nested array (for use in Filament filter options and display)
    'status' => [
        'draft' => 'مسودة',
        'pending_review' => 'قيد المراجعة',
        'published' => 'منشور',
        'rejected' => 'مرفوض',
        'archived' => 'مؤرشف',
    ],

    // Excel import
    'import_file_label' => 'ملف إكسل (.xlsx, .xls, .csv)',
    'import_button' => 'استيراد',
    'import_no_vendor' => 'لا يوجد ملف بائع مرتبط بحسابك.',
    'import_success' => 'تم استيراد :count خدمة بنجاح.',
    'import_failed' => 'فشل الاستيراد. يرجى مراجعة الأخطاء أدناه.',
    'imported_rows' => 'تم استيراد :count صف بنجاح.',
    'import_failed_rows' => 'فشل الاستيراد بـ :count خطأ.',
    'row' => 'الصف',
    'field' => 'الحقل',
    'error' => 'الخطأ',
];
