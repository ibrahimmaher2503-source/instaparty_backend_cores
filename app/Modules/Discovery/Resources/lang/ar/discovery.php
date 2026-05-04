<?php

declare(strict_types=1);

return [
    'wishlist' => [
        'created' => 'تم إنشاء قائمة الأمنيات بنجاح.',
        'updated' => 'تم تحديث قائمة الأمنيات بنجاح.',
        'deleted' => 'تم حذف قائمة الأمنيات بنجاح.',
        'item_added' => 'تمت إضافة الخدمة إلى قائمة الأمنيات.',
        'item_removed' => 'تمت إزالة الخدمة من قائمة الأمنيات.',
        'already_exists' => 'هذه الخدمة موجودة بالفعل في قائمة الأمنيات.',
    ],
    'search' => [
        'no_results' => 'لم يتم العثور على خدمات تطابق بحثك.',
    ],
    'saved_search' => [
        'saved' => 'تم حفظ البحث بنجاح.',
        'deleted' => 'تم حذف البحث المحفوظ.',
    ],
    'reindex_services' => 'إعادة فهرسة الخدمات',
    'reindex_success' => 'تم إعادة فهرسة الخدمات بنجاح.',
    'reindex_confirm' => 'سيتم جدولة إعادة فهرسة كاملة لجميع الخدمات المنشورة. هل تريد المتابعة؟',
    'wishlist_added' => 'تمت إضافة الخدمة إلى قائمة الرغبات.',
    'wishlist_removed' => 'تمت إزالة الخدمة من قائمة الرغبات.',
    'search_placeholder' => 'ابحث عن الخدمات…',

    'nav' => [
        'saved_searches' => 'البحوث المحفوظة',
        'search_logs' => 'سجل البحث',
        'wishlists' => 'قوائم الأمنيات',
    ],

    'models' => [
        'saved_search' => [
            'singular' => 'بحث محفوظ',
            'plural' => 'البحوث المحفوظة',
        ],
        'search_log' => [
            'singular' => 'سجل بحث',
            'plural' => 'سجلات البحث',
        ],
        'wishlist' => [
            'singular' => 'قائمة أمنيات',
            'plural' => 'قوائم الأمنيات',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'user_id' => 'المستخدم',
        'label' => 'التسمية',
        'filters' => 'عوامل التصفية',
        'query' => 'نص البحث',
        'locale' => 'اللغة',
        'results_count' => 'عدد النتائج',
        'clicked_service_id' => 'الخدمة المنقور عليها',
        'name' => 'الاسم',
        'items_count' => 'عدد العناصر',
    ],
];
