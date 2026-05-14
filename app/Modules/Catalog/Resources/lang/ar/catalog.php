<?php

declare(strict_types=1);

return [
    // Navigation groups
    'nav_group_services' => 'الخدمات',
    'nav_group_catalog' => 'الكتالوج',

    'nav' => [
        'categories' => 'التصنيفات',
        'occasions' => 'المناسبات',
        'rental_services' => 'خدمات التأجير',
        'sale_services' => 'خدمات البيع',
        'digital_services' => 'الخدمات الرقمية',
        'service_themes' => 'ثيمات الخدمات',
        'category_field_schemas' => 'مخططات حقول التصنيفات',
        'excel_imports' => 'عمليات استيراد إكسل',
        'inventory_reservations' => 'حجوزات المخزون',
        'import_rental_services' => 'استيراد خدمات التأجير',
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
            'singular' => 'خدمة تأجير',
            'plural' => 'خدمات التأجير',
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
            'singular' => 'ثيم خدمة',
            'plural' => 'ثيمات الخدمات',
        ],
        'category_field_schema' => [
            'singular' => 'مخطط حقول التصنيف',
            'plural' => 'مخططات حقول التصنيفات',
        ],
        'excel_import' => [
            'singular' => 'عملية استيراد إكسل',
            'plural' => 'عمليات استيراد إكسل',
        ],
        'inventory_reservation' => [
            'singular' => 'حجز مخزون',
            'plural' => 'حجوزات المخزون',
        ],
    ],

    // Service statuses
    'status_changes_requested' => 'مطلوب تعديلات',
    'status_draft' => 'مسودة',
    'status_pending_review' => 'قيد المراجعة',
    'status_published' => 'منشور',
    'status_rejected' => 'مرفوض',
    'status_archived' => 'مؤرشف',

    'status' => [
        'changes_requested' => 'مطلوب تعديلات',
        'draft' => 'مسودة',
        'pending_review' => 'قيد المراجعة',
        'published' => 'منشور',
        'rejected' => 'مرفوض',
        'archived' => 'مؤرشف',
    ],

    // Import statuses
    'import_status' => [
        'pending' => 'قيد الانتظار',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],

    // Product types
    'product_type_rental' => 'تأجير',
    'product_type_sale' => 'بيع',
    'product_type_digital' => 'رقمي',

    // Field types
    'field_types' => [
        'text' => 'نص',
        'number' => 'رقم',
        'boolean' => 'نعم / لا',
        'select' => 'اختيار واحد',
        'multiselect' => 'اختيارات متعددة',
        'date' => 'تاريخ',
    ],

    // Hold types
    'hold_types' => [
        'cart' => 'سلة الشراء',
        'payment' => 'الدفع',
    ],

    // Reservation statuses
    'reservation_status_held' => 'محجوز مؤقتاً',
    'reservation_status_confirmed' => 'مؤكد',
    'reservation_status_expired' => 'منتهي الصلاحية',
    'reservation_status_released' => 'تم تحريره',

    // Table / Form labels
    'code' => 'الكود',
    'name' => 'الاسم',
    'description' => 'الوصف',
    'short_description' => 'وصف مختصر',
    'long_description' => 'وصف تفصيلي',
    'name_en' => 'الاسم بالإنجليزية',
    'name_ar' => 'الاسم بالعربية',
    'sort_order' => 'ترتيب العرض',
    'is_active' => 'نشط',
    'parent_category' => 'التصنيف الرئيسي',
    'no_parent' => 'بدون تصنيف رئيسي',
    'allowed_product_types' => 'أنواع المنتجات المسموح بها',
    'product_type' => 'نوع المنتج',
    'category' => 'التصنيف',
    'base_price' => 'السعر الأساسي بالقرش',
    'status_label' => 'الحالة',
    'is_featured' => 'مميز',
    'vendor' => 'المورّد',
    'media' => 'الوسائط / المعرض',
    'service' => 'الخدمة',
    'user_id' => 'المستخدم',
    'quantity' => 'الكمية',
    'public_id' => 'المعرّف العام',
    'original_filename' => 'اسم الملف الأصلي',
    'total_rows' => 'إجمالي الصفوف',
    'imported_rows_count' => 'الصفوف المستوردة',
    'error_rows' => 'صفوف الأخطاء',
    'hold_type' => 'نوع الحجز المؤقت',
    'reserved_starts_at' => 'بداية الحجز',
    'reserved_ends_at' => 'نهاية الحجز',
    'expires_at' => 'ينتهي في',

    // Category field schema
    'field_key' => 'مفتاح الحقل',
    'field_type' => 'نوع الحقل',
    'field_label' => 'عنوان الحقل',
    'field_label_en' => 'عنوان الحقل بالإنجليزية',
    'field_label_ar' => 'عنوان الحقل بالعربية',
    'advanced_schema' => 'المخطط المتقدم',
    'is_required' => 'مطلوب',
    'is_filterable' => 'قابل للتصفية',
    'icon_path' => 'مسار الأيقونة',

    // Section headings
    'occasion_details' => 'تفاصيل المناسبة',
    'category_details' => 'تفاصيل التصنيف',
    'service_theme_details' => 'تفاصيل ثيم الخدمة',
    'shared' => 'المعلومات العامة',

    // Rental detail form
    'rental_details' => 'تفاصيل التأجير',
    'requires_electricity' => 'يتطلب كهرباء',
    'requires_outdoor_space' => 'يتطلب مساحة خارجية',
    'default_rental_duration_hours' => 'مدة التأجير الافتراضية بالساعات',
    'setup_time_minutes' => 'وقت التركيب بالدقائق',
    'teardown_time_minutes' => 'وقت الفك بالدقائق',
    'security_deposit' => 'مبلغ التأمين بالقرش',
    'minimum_space_sqm' => 'أقل مساحة مطلوبة بالمتر المربع',

    // Sale detail form
    'sale_details' => 'تفاصيل البيع',
    'is_perishable' => 'قابل للتلف',
    'is_made_to_order' => 'يُصنع حسب الطلب',
    'lead_time_hours' => 'وقت التجهيز بالساعات',
    'stock_quantity' => 'الكمية المتاحة',
    'stock_quantity_hint' => 'اتركه فارغاً إذا كانت الكمية غير محدودة',
    'customization_fields' => 'حقول التخصيص',

    // Digital detail form
    'digital_details' => 'تفاصيل الخدمة الرقمية',
    'delivery_method' => 'طريقة التسليم',
    'has_expiry' => 'له مدة صلاحية',
    'expiry_days_after_purchase' => 'عدد أيام الصلاحية بعد الشراء',
    'is_refundable_after_delivery' => 'قابل لرد المبلغ بعد التسليم',
    'redemption_url_template' => 'قالب رابط الاستخدام',

    // Translatable content section
    'translatable_fields' => 'المحتوى القابل للترجمة',

    // Excel import
    'import_file_label' => 'ملف إكسل أو CSV',
    'import_button' => 'استيراد',
    'import_no_vendor' => 'لا يوجد ملف مورّد مرتبط بحسابك.',
    'import_success' => 'تم استيراد :count خدمة بنجاح.',
    'import_failed' => 'فشلت عملية الاستيراد. يرجى مراجعة الأخطاء أدناه.',
    'imported_rows' => 'تم استيراد :count صف بنجاح.',
    'import_failed_rows' => 'فشلت عملية الاستيراد بسبب :count خطأ.',
    'row' => 'الصف',
    'field' => 'الحقل',
    'error' => 'الخطأ',
    // Moderation actions
    'approve_publish' => 'اعتماد ونشر',
    'approve_publish_heading' => 'اعتماد الخدمة ونشرها',
    'approve_publish_description' => 'سيتم نشر الخدمة وإزالتها من قائمة المراجعة.',
    'approve_publish_success' => 'تم نشر الخدمة بنجاح.',
    'approve_selected' => 'اعتماد المحدد',
    'approve_selected_heading' => 'اعتماد الخدمات المحددة',
    'approve_selected_description' => 'سيتم نشر كل الخدمات المحددة قيد المراجعة.',
    'approve_selected_success' => 'تم نشر :count خدمة بنجاح.',
    'reject_service' => 'رفض',
    'reject_service_heading' => 'رفض الخدمة',
    'reject_service_description' => 'اكتب سبب الرفض بالإنجليزية والعربية.',
    'reject_reason_en' => 'سبب الرفض بالإنجليزية',
    'reject_reason_ar' => 'سبب الرفض بالعربية',
    'reject_success' => 'تم رفض الخدمة بنجاح.',
    'reject_selected' => 'رفض المحدد',
    'reject_selected_heading' => 'رفض الخدمات المحددة',
    'reject_selected_description' => 'سيتم حفظ سبب الرفض الثنائي على كل خدمة محددة.',
    'reject_selected_success' => 'تم رفض :count خدمة بنجاح.',
    'archive_selected' => 'أرشفة المحدد',
    'archive_selected_heading' => 'أرشفة الخدمات المحددة',
    'archive_selected_description' => 'سيتم أرشفة الخدمات المحددة المؤهلة.',
    'archive_selected_success' => 'تمت أرشفة :count خدمة بنجاح.',
    'request_changes' => 'طلب تعديلات',
    'change_items' => 'التعديلات المطلوبة',
    'field_path' => 'الحقل',
    'requested_change_en' => 'التعديل المطلوب بالإنجليزية',
    'requested_change_ar' => 'التعديل المطلوب بالعربية',
    'changes_requested' => 'تم طلب التعديلات',
    'changes_requested_message' => 'تم نقل الخدمة إلى مسار التعديلات المطلوبة.',
    'pending_rental_services' => 'خدمات التأجير قيد المراجعة',
    'pending_sale_services' => 'خدمات البيع قيد المراجعة',
    'pending_digital_services' => 'الخدمات الرقمية قيد المراجعة',
    'pending_queue_empty_rental' => 'لا توجد خدمات تأجير قيد المراجعة.',
    'pending_queue_empty_sale' => 'لا توجد خدمات بيع قيد المراجعة.',
    'pending_queue_empty_digital' => 'لا توجد خدمات رقمية قيد المراجعة.',
    'moderation_not_allowed' => 'ليست لديك صلاحية مراجعة هذه الخدمة.',
    'moderation_invalid_transition' => 'لم تعد هذه الخدمة مؤهلة لهذا إجراء المراجعة.',
    'moderation_conflict' => 'تعذر تنفيذ الإجراء على :count خدمة لأن حالتها تغيرت.',
    'moderation_notes' => 'ملاحظات المراجعة',
];
