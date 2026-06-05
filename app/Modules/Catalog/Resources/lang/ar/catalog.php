<?php

declare(strict_types=1);

return [
    'availability' => [
        'window_required' => 'يتطلب فحص توفر الإيجار تحديد starts_at و ends_at.',
    ],

    // Navigation groups
    'nav_group_services' => 'الخدمات',
    'nav_group_catalog' => 'الكتالوج',

    'nav' => [
        'categories' => 'التصنيفات',
        'occasions' => 'المناسبات',
        'rental_services' => 'خدمات التأجير',
        'sale_services' => 'خدمات البيع',
        'digital_services' => 'الخدمات الرقمية',
        'pending_service_edits' => 'تعديلات الخدمات المعلقة',
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

    // Product types (legacy flat keys kept for backward compat)
    'product_type_rental' => 'تأجير',
    'product_type_sale' => 'بيع',
    'product_type_digital' => 'رقمي',

    // Product type labels (used by ProductType::label())
    'product_types' => [
        'rental' => 'تأجير',
        'sale' => 'بيع',
        'digital' => 'رقمي',
    ],

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
    'vendor_categories' => [
        'intro_heading' => 'التصنيفات التي يمكنك العمل بها',
        'intro_body' => 'هذه قائمة مرجعية للاطلاع فقط بالتصنيفات المتاحة لك على المنصة، بناءً على أنواع المنتجات المعتمَدة لحسابك. لتغيير أنواع المنتجات المعتمَدة لك، انتقل إلى حالة الاعتماد.',
        'manage_link' => 'الانتقال إلى حالة الاعتماد',
        'empty_heading' => 'لا توجد تصنيفات متاحة بعد',
        'empty_description' => 'بمجرد اعتماد حسابك لنوع منتج واحد أو أكثر، ستظهر التصنيفات المطابقة هنا.',
    ],
    'product_type' => 'نوع المنتج',
    'category' => 'التصنيف',
    'base_price' => 'السعر الأساسي',
    'status_label' => 'الحالة',
    'is_featured' => 'مميز',
    'vendor' => 'المورّد',
    'media' => 'الوسائط / المعرض',
    'cover_image' => 'صورة الغلاف',
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
    'download_template' => 'تحميل النموذج',
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

    // Import history & error review (feature 039)
    'import_details' => 'تفاصيل الاستيراد',
    'import_history' => 'سجل الاستيراد',
    'view_errors' => 'عرض الأخطاء',
    'import_errors_for' => 'أخطاء الاستيراد: :filename',
    'row_number' => 'رقم الصف',
    'field_name' => 'الحقل',
    'entered_value' => 'القيمة المُدخلة',
    'validation_message' => 'رسالة التحقق',
    'required_fix' => 'الإصلاح المطلوب',
    'no_failed_rows' => 'لا توجد صفوف فاشلة للتصدير',
    'download_failed_rows' => 'تحميل الصفوف الفاشلة',
    'retry_import' => 'إعادة محاولة الاستيراد',
    'retry_import_queued' => 'بدأ استيراد جديد. تحقق من سجل الاستيراد للمتابعة.',

    // Fix hints
    'fix_hint_required' => 'هذا الحقل مطلوب',
    'fix_hint_integer' => 'يجب أن يكون رقمًا صحيحًا',
    'fix_hint_min_0' => 'يجب أن يكون 0 أو أكثر',
    'fix_hint_min_1' => 'يجب أن يكون 1 أو أكثر',
    'fix_hint_max_255' => 'الحد الأقصى 255 حرفًا',
    'fix_hint_boolean' => 'يجب أن يكون صحيحًا (1) أو خاطئًا (0)',
    'fix_hint_string' => 'يجب أن يكون نصًا',
    'fix_hint_default' => 'تحقق من القيمة وحاول مجددًا',
    // Moderation actions
    'approve_publish' => 'اعتماد ونشر',
    'approve_publish_heading' => 'اعتماد الخدمة ونشرها',
    'approve_publish_description' => 'سيتم نشر الخدمة وإزالتها من قائمة المراجعة.',
    'approve_publish_success' => 'تم نشر الخدمة بنجاح.',
    'approve_publish_failed' => 'تعذر نشر الخدمة',
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
    'category_has_children' => 'لا يمكن حذف فئة تحتوي على فئات فرعية. انقل أو احذف الفئات الفرعية أولاً.',
    'reorder_unknown_categories' => 'إحدى الفئات في قائمة الترتيب غير موجودة.',
    'reorder_parent_mismatch' => 'جميع الفئات في عملية إعادة ترتيب واحدة يجب أن يكون لها نفس الأب.',

    // طرق التسليم
    'delivery_methods' => [
        'email' => 'بريد إلكتروني',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'link' => 'رابط',
        'in_app' => 'داخل التطبيق',
    ],

    // حقول الترجمة (للنماذج ذات الأعمدة JSON اليدوية)
    'fields' => [
        'name_en' => 'الاسم (إنجليزي)',
        'description_en' => 'الوصف (إنجليزي)',
        'name_ar' => 'الاسم (عربي)',
        'description_ar' => 'الوصف (عربي)',
    ],

    // تسميات بوابة المورد
    'vendor_portal' => [
        'submit_for_review' => 'إرسال للمراجعة',
        'clone' => 'نسخ',
        'cloned' => 'تم نسخ الخدمة. أنت الآن تعدّل النسخة.',
        'block_dates' => 'حظر تواريخ',
        'unblock' => 'إزالة الحظر',
        'blocked' => 'تم حظر التواريخ.',
        'unblocked' => 'تمت إزالة الحظر.',
        'availability_title' => 'كتل التوافر',
        'cannot_edit_published' => 'التعديلات الجوهرية ستعيد هذه الخدمة إلى المراجعة.',
    ],

    // لاحقة المبلغ / تلميح السعر
    'piastres_suffix' => 'قرش',
    'price_helper' => 'بالقرش — 10000 = 100.00 جنيه مصري',

    // إجراء الاستيراد المدمج في جدول المسؤول
    'import' => [
        'rental_label' => 'استيراد خدمات التأجير',
        'sale_label' => 'استيراد خدمات البيع',
        'digital_label' => 'استيراد الخدمات الرقمية',
        'vendor' => 'المورّد',
        'file' => 'ملف إكسل (.xlsx / .xls)',
        'modal_heading_rental' => 'استيراد خدمات التأجير',
        'modal_heading_sale' => 'استيراد خدمات البيع',
        'modal_heading_digital' => 'استيراد الخدمات الرقمية',
        'modal_submit' => 'استيراد',
        'completed' => 'اكتمل الاستيراد: تم إنشاء :count خدمة.',
        'failed_row_errors' => 'فشل الاستيراد — راجع أخطاء كل صف في سجل الاستيراد.',
    ],
];
