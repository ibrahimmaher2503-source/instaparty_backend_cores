<?php

declare(strict_types=1);

return [
    // Status labels
    'status_pending'                       => 'قيد المراجعة',
    'status_awaiting_clarification'        => 'في انتظار التوضيح',
    'status_approved'                      => 'تمت الموافقة',
    'status_rejected'                      => 'مرفوض',
    'status_cancelled_vendor_suspended'    => 'ملغي (المورد معلق)',
    'status_cancelled_service_unavailable' => 'ملغي (الخدمة غير متاحة)',

    // Validation messages
    'admin_note_en_required'               => 'ملاحظة الإدارة باللغة الإنجليزية مطلوبة.',
    'admin_note_ar_required'               => 'ملاحظة الإدارة باللغة العربية مطلوبة.',
    'body_en_required'                     => 'نص الرسالة باللغة الإنجليزية مطلوب.',
    'body_ar_required'                     => 'نص الرسالة باللغة العربية مطلوب.',
    'clarification_cap_reached'            => 'تم الوصول إلى الحد الأقصى من جولات التوضيح (:max).',
    'pending_request_exists'               => 'لديك تعديل قيد المراجعة من قبل الإدارة.',
    'version_mismatch'                     => 'تم البت في طلب التغيير هذا بالفعل من قبل مسؤول آخر. يرجى إعادة التحميل.',
    'not_found'                            => 'طلب التغيير غير موجود.',

    // Notification subjects
    'notification_approved_subject'        => 'تمت الموافقة على تعديل خدمتك',
    'notification_rejected_subject'        => 'لم تتم الموافقة على تعديل خدمتك',
    'notification_clarification_subject'   => 'لدى الإدارة سؤال حول تعديل خدمتك',

    // Notification bodies
    'notification_approved_body'           => 'تمت الموافقة على التعديلات المقترحة على ":service" وأصبحت سارية الآن.',
    'notification_rejected_body'           => 'لم تتم الموافقة على التعديلات المقترحة على ":service". السبب: :reason',
    'notification_clarification_body'      => 'قدّمت الإدارة سؤالاً حول تعديلك المعلّق للخدمة ":service". يرجى الرد للمتابعة.',
];
