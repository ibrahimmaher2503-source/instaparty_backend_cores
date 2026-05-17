<?php

declare(strict_types=1);

return [
    // Index columns
    'columns' => [
        'booking_ref' => 'الحجز',
        'customer' => 'العميل',
        'vendor' => 'المورّد',
        'status' => 'الحالة',
        'flag_count' => 'بلاغات مفتوحة',
        'last_message_at' => 'آخر رسالة',
        'frozen_by' => 'جُمِّد بواسطة',
        'frozen_at' => 'وقت التجميد',
        'product_type' => 'نوع المنتج',
    ],

    // Status pills
    'status' => [
        'open' => 'مفتوح',
        'locked' => 'مغلق',
        'closed' => 'منتهٍ',
        'frozen' => 'مجمَّد بواسطة المشرف',
    ],

    // Action labels
    'actions' => [
        'freeze' => 'تجميد المحادثة',
        'unfreeze' => 'إلغاء تجميد المحادثة',
        'resolve_flag' => 'حسم البلاغ',
        'mark_off_platform' => 'تسجيل كمحاولة تواصل خارج المنصة',
        'escalate' => 'تصعيد إلى صندوق الإدارة',
    ],

    // Modal field labels
    'fields' => [
        'reason_en' => 'السبب (بالإنجليزية)',
        'reason_ar' => 'السبب (بالعربية)',
        'category' => 'التصنيف',
        'decision' => 'القرار',
        'note_en' => 'ملاحظة (بالإنجليزية)',
        'note_ar' => 'ملاحظة (بالعربية)',
        'note' => 'ملاحظة',
        'severity' => 'مستوى الخطورة',
        'summary_en' => 'الملخص (بالإنجليزية)',
        'summary_ar' => 'الملخص (بالعربية)',
        'summary' => 'الملخص',
    ],

    // Category options
    'categories' => [
        'off_platform_contact' => 'محاولة تواصل خارج المنصة',
        'policy_violation' => 'مخالفة السياسات',
        'harassment' => 'تحرش أو إساءة',
        'other' => 'أخرى',
    ],

    // Resolution decisions
    'decisions' => [
        'upheld_redact' => 'مؤيَّد — إخفاء الرسالة',
        'upheld_warn' => 'مؤيَّد — تحذير المستخدم',
        'upheld_block' => 'مؤيَّد — حظر المستخدم',
        'dismissed_false_positive' => 'مرفوض — بلاغ خاطئ',
    ],

    // Flag types
    'flag_types' => [
        'phone' => 'رقم هاتف',
        'email' => 'بريد إلكتروني',
        'profanity' => 'ألفاظ نابية',
        'external_link' => 'رابط خارجي',
        'other' => 'أخرى',
    ],

    // Severity options
    'severities' => [
        'info' => 'معلوماتي',
        'warning' => 'تحذير',
        'critical' => 'حرج',
    ],

    // 409 / business-rule errors
    'errors' => [
        'unfreeze_forbidden_outside_window' => 'لا يمكن إلغاء تجميد هذه المحادثة لأن الحجز خرج من نافذة المراجعة.',
        'flag_already_resolved' => 'تم حسم هذا البلاغ مسبقاً ولا يمكن حسمه مرة أخرى.',
    ],

    // Audit timeline labels
    'audit' => [
        'event_actor' => 'الفاعل',
        'event_reason' => 'السبب',
        'event_at' => 'الوقت',
        'event_action' => 'الإجراء',
    ],

    // Escalation
    'escalation_title' => 'تم تصعيد بلاغ مراقبة المحادثة إلى صندوق الإدارة',

    // Redacted placeholder
    'redacted_placeholder' => '<تم حجب الرسالة بواسطة الإشراف>',

    // Navigation
    'nav' => [
        'restricted_chat' => 'المحادثة المقيَّدة',
    ],

    // Sections in detail view
    'sections' => [
        'booking_context' => 'بيانات الحجز',
        'messages' => 'الرسائل (للقراءة فقط)',
        'message_body' => 'نص الرسالة',
        'audit_timeline' => 'سجل الإجراءات',
    ],

    'firestore_placeholder' => 'المحتوى محفوظ في Firestore',

    'filters' => [
        'has_open_flags' => 'به بلاغات مفتوحة',
        'product_type' => 'نوع المنتج',
        'last_message_from' => 'آخر رسالة من',
        'last_message_to' => 'آخر رسالة إلى',
    ],
];
