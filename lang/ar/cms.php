<?php

declare(strict_types=1);

return [
    'validation' => [
        'body_en_required' => 'محتوى النص الإنجليزي مطلوب قبل النشر.',
        'body_ar_required' => 'محتوى النص العربي مطلوب قبل النشر.',
    ],

    'actions' => [
        'publish'   => 'نشر',
        'unpublish' => 'إلغاء النشر',
    ],

    'messages' => [
        'published_successfully'   => 'تم نشر الصفحة بنجاح.',
        'unpublished_successfully' => 'تم إلغاء نشر الصفحة.',
    ],

    'fields' => [
        'slug'             => 'المسار',
        'title'            => 'العنوان',
        'body'             => 'المحتوى',
        'meta_description' => 'وصف الميتا',
        'is_published'     => 'منشور',
        'published_at'     => 'تاريخ النشر',
    ],
];
