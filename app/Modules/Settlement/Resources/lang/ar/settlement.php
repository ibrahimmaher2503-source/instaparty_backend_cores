<?php

declare(strict_types=1);

return [
    'nav' => [
        'commissions' => 'العمولات',
        'commission_rules' => 'قواعد العمولات',
        'wallet_ledger' => 'سجل المحفظة',
        'withdrawals_queue' => 'طلبات السحب',
        'settlement_runs' => 'دورات التسوية',
        'ledger_groups' => 'مجموعات دفتر الأستاذ',
        'reconciliation_runs' => 'دورات المطابقة',
        'reconciliation_findings' => 'نتائج المطابقة',
    ],

    'models' => [
        'commission' => [
            'singular' => 'عمولة',
            'plural' => 'العمولات',
        ],
        'commission_rule' => [
            'singular' => 'قاعدة عمولة',
            'plural' => 'قواعد العمولات',
        ],
        'wallet' => [
            'singular' => 'محفظة',
            'plural' => 'المحافظ',
        ],
        'withdrawal' => [
            'singular' => 'طلب سحب',
            'plural' => 'طلبات السحب',
        ],
        'settlement_run' => [
            'singular' => 'دورة تسوية',
            'plural' => 'دورات التسوية',
        ],
        'ledger_group' => [
            'singular' => 'مجموعة دفتر الأستاذ',
            'plural' => 'مجموعات دفتر الأستاذ',
        ],
        'reconciliation_run' => [
            'singular' => 'دورة مطابقة',
            'plural' => 'دورات المطابقة',
        ],
        'reconciliation_finding' => [
            'singular' => 'نتيجة مطابقة',
            'plural' => 'نتائج المطابقة',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking_item' => 'عنصر الحجز',
        'payment' => 'عملية الدفع',
        'vendor' => 'المورّد',
        'product_type' => 'نوع المنتج',
        'gross_amount' => 'إجمالي المبلغ',
        'commission_amount' => 'قيمة العمولة',
        'vendor_share' => 'مستحقات المورد',
        'status' => 'الحالة',
        'period_start' => 'بداية الفترة',
        'period_end' => 'نهاية الفترة',
        'total_gross' => 'إجمالي المبالغ',
        'total_commission' => 'إجمالي العمولات',
        'total_vendor_share' => 'إجمالي مستحقات الموردين',
        'kind' => 'النوع',
        'currency' => 'العملة',
        'direction' => 'الاتجاه',
        'entry_type' => 'نوع القيد',
        'amount' => 'المبلغ',
        'running_balance' => 'الرصيد الجاري',
        'group_id' => 'المجموعة',
        'correlation_id' => 'معرّف الترابط',
        'causation_id' => 'معرّف السببية',
        'initiator_type' => 'نوع المبادر',
        'related_entity' => 'الكيان المرتبط',
        'related_id' => 'المعرّف المرتبط',
        'scope_type' => 'النطاق',
        'wallets_scanned' => 'المحافظ المفحوصة',
        'findings_count' => 'النتائج',
        'auto_repaired_count' => 'الإصلاح التلقائي',
        'manual_review_count' => 'مراجعة يدوية',
        'started_at' => 'بدأت في',
        'completed_at' => 'اكتملت في',
        'finding_type' => 'نوع النتيجة',
        'severity' => 'الخطورة',
        'resource_type' => 'نوع المورد',
        'resource_id' => 'معرّف المورد',
        'resolution' => 'الحل',
        'detected_at' => 'اكتُشفت في',
        'run_id' => 'دورة المطابقة',
    ],

    'status' => [
        'calculated' => 'محسوبة',
        'partially_reversed' => 'معكوسة جزئياً',
        'reversed' => 'معكوسة بالكامل',
        'pending' => 'قيد الانتظار',
        'reconciled' => 'تمت المطابقة',
        'disputed' => 'محل نزاع',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
    ],

    'errors' => [
        'insufficient_balance' => 'رصيد المحفظة غير كافٍ. المتاح: :available :currency، والمطلوب: :requested :currency.',
        'existing_pending_withdrawal' => 'لديك طلب سحب قيد الانتظار بالفعل (:public_id). يرجى انتظار معالجته قبل تقديم طلب جديد.',
        'below_minimum_amount' => 'مبلغ السحب أقل من الحد الأدنى وهو :minimum :currency.',
        'negative_balance_blocked' => 'رصيد محفظتك سالب. تم إيقاف طلبات السحب حتى تتم تسوية الرصيد.',
        'withdrawal_not_found' => 'طلب السحب غير موجود.',
        'no_wallet_access' => 'ليس لديك صلاحية الوصول إلى هذه المحفظة.',
        'per_page_too_large' => 'يجب ألا تزيد قيمة per_page عن 100.',
        'invalid_iban' => 'رقم IBAN المُدخل غير صالح.',
        // Phase 4.11 — Finance Audit
        'withdrawal_state'    => 'لا يمكن تنفيذ هذا الإجراء في الحالة الحالية.',
        'mark_paid_failed'    => 'تعذر تسجيل العملية كمدفوعة.',
        'wallet_locked_retry' => 'المحفظة محجوزة حاليًا — برجاء المحاولة بعد ثوانٍ.',
    ],

    'ledger' => [
        'commission_credit' => 'إضافة عمولة لعنصر الحجز رقم :booking_item_id',
        'refund_debit' => 'خصم استرداد لعنصر الحجز رقم :booking_item_id',
        'withdrawal_debit' => 'سحب مدفوع إلى الحساب البنكي المنتهي بـ ...:iban_last3',
        'manual_adjustment' => 'تعديل يدوي: :note',
    ],

    'withdrawal_status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
    ],

    // Phase 4.11 — Withdrawal Proof & Finance Audit
    'notifications' => [
        'withdrawal_approved' => 'تم اعتماد طلب السحب.',
        'withdrawal_paid'     => 'تم تسجيل صرف طلب السحب.',
    ],

    'fields' => [
        'bank_transfer_reference' => 'رقم إشعار التحويل البنكي',
        'transfer_proof'          => 'إثبات التحويل',
        'payment_note_en'         => 'ملاحظة الدفع (إنجليزي)',
        'payment_note_ar'         => 'ملاحظة الدفع (عربي)',
        'approved_at'             => 'تاريخ الاعتماد',
        'approved_by'             => 'اعتمد بواسطة',
        'paid_by'                 => 'صرف بواسطة',
    ],

    'validation' => [
        'bank_transfer_reference_required' => 'رقم إشعار التحويل البنكي مطلوب.',
        'bank_transfer_reference_duplicate' => 'هذا الرقم مستخدم من قبل لهذا المورد.',
        'proof_required'                    => 'ملف إثبات التحويل مطلوب.',
        'proof_format'                      => 'يجب أن يكون الإثبات بصيغة PDF أو JPEG أو PNG وبحجم أقصى 10 ميجا.',
    ],

    'commission_status' => [
        'calculated' => 'محسوبة',
        'partially_reversed' => 'معكوسة جزئياً',
        'reversed' => 'معكوسة بالكامل',
    ],

    'settlement_run_status' => [
        'pending' => 'قيد الانتظار',
        'reconciled' => 'تمت المطابقة',
        'disputed' => 'محل نزاع',
    ],
    'dispute_oversight' => 'مراقبة النزاعات',
    'pending_refunds' => 'المستردات المعلقة',
    'pending_withdrawals' => 'السحوبات المعلقة',
    'total_disputed_amount' => 'إجمالي المبالغ المتنازع عليها',
    'pending_refunds_section' => 'المستردات المعلقة',
    'pending_withdrawals_section' => 'السحوبات المعلقة',
    'ref' => 'مرجع الدفع',
    'status' => 'الحالة',
    'amount' => 'المبلغ',
    'reason' => 'السبب',
    'created_at' => 'تاريخ الإنشاء',
    'withdrawal_id' => 'رقم السحب',
    'owner' => 'المالك',

    'ledger_group' => [
        'detail' => 'تفاصيل المجموعة',
        'entries' => 'قيود دفتر الأستاذ',
    ],

    'actions' => [
        // Phase 4.11 — Finance Audit
        'approve'              => 'اعتماد',
        'approve_description'  => 'اعتماد طلب السحب هذا. سيتم إبلاغ المورد.',
        'mark_paid'            => 'تسجيل كمدفوع',
        'show_causal_chain' => 'عرض السلسلة السببية',
        'causal_chain_title' => 'السلسلة السببية الكاملة',
        'causal_chain_entries' => ':count قيود في هذه السلسلة',
        'close' => 'إغلاق',
        'mark_ignored' => 'وضع علامة "متجاهل"',
        'mark_ignored_heading' => 'تجاهل النتيجة (مشكلة معروفة)',
        'mark_ignored_description' => 'ستُعلَّم هذه النتيجة على أنها مشكلة معروفة وتُستبعد من قائمة المراجعة اليدوية.',
        'ignore_reason' => 'السبب',
        'marked_ignored_success' => 'تم وضع علامة "متجاهل" على النتيجة.',
    ],

    'findings' => [
        'unresolved' => 'غير محلولة',
        'auto_repaired' => 'إصلاح تلقائي',
        'ignored' => 'متجاهلة (مشكلة معروفة)',
    ],

    'reconciliation_run' => [
        'summary' => 'ملخص الدورة',
    ],

    'reconciliation_dashboard' => [
        'nav_label' => 'لوحة المطابقة',
        'title' => 'لوحة المطابقة',
        'open_high_findings' => 'النتائج عالية الخطورة المفتوحة',
        'auto_repaired_today' => 'الإصلاح التلقائي اليوم',
        'runs_last_7_days' => 'الدورات (آخر 7 أيام)',
        'run_trend_heading' => 'اتجاه الدورات خلال 7 أيام',
        'date' => 'التاريخ',
        'runs' => 'الدورات',
    ],
];
