<?php

declare(strict_types=1);

return [
    'wishlist' => [
        'created' => 'تم إنشاء قائمة المفضلة بنجاح.',
        'updated' => 'تم تحديث قائمة المفضلة بنجاح.',
        'deleted' => 'تم حذف قائمة المفضلة بنجاح.',
        'item_added' => 'تمت إضافة الخدمة إلى قائمة المفضلة.',
        'item_removed' => 'تمت إزالة الخدمة من قائمة المفضلة.',
        'already_exists' => 'هذه الخدمة موجودة بالفعل في قائمة المفضلة.',
    ],

    'search' => [
        'no_results' => 'لم يتم العثور على خدمات تطابق بحثك.',
    ],

    'saved_search' => [
        'saved' => 'تم حفظ البحث بنجاح.',
        'deleted' => 'تم حذف البحث المحفوظ.',
    ],

    'reindex_services' => 'إعادة فهرسة الخدمات',
    'reindex_success' => 'تمت جدولة إعادة فهرسة الخدمات بنجاح.',
    'reindex_confirm' => 'سيتم جدولة إعادة فهرسة كاملة لجميع الخدمات المنشورة. هل تريد المتابعة؟',
    'wishlist_added' => 'تمت إضافة الخدمة إلى قائمة المفضلة.',
    'wishlist_removed' => 'تمت إزالة الخدمة من قائمة المفضلة.',
    'search_placeholder' => 'ابحث عن الخدمات...',

    'nav' => [
        'saved_searches' => 'عمليات البحث المحفوظة',
        'search_logs' => 'سجلات البحث',
        'wishlists' => 'قوائم المفضلة',
    ],

    'models' => [
        'saved_search' => [
            'singular' => 'بحث محفوظ',
            'plural' => 'عمليات البحث المحفوظة',
        ],
        'search_log' => [
            'singular' => 'سجل بحث',
            'plural' => 'سجلات البحث',
        ],
        'wishlist' => [
            'singular' => 'قائمة مفضلة',
            'plural' => 'قوائم المفضلة',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'user_id' => 'المستخدم',
        'label' => 'التسمية',
        'filters' => 'عوامل التصفية',
        'query' => 'عبارة البحث',
        'locale' => 'اللغة',
        'results_count' => 'عدد النتائج',
        'clicked_service_id' => 'الخدمة التي تم النقر عليها',
        'name' => 'الاسم',
        'items_count' => 'عدد العناصر',
    ],
];
