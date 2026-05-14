<?php

declare(strict_types=1);

return [
    'validation' => [
        'body_en_required' => 'The English body content is required before publishing.',
        'body_ar_required' => 'The Arabic body content is required before publishing.',
    ],

    'actions' => [
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
    ],

    'messages' => [
        'published_successfully' => 'Page published successfully.',
        'unpublished_successfully' => 'Page unpublished.',
    ],

    'fields' => [
        'slug' => 'Slug',
        'title' => 'Title',
        'body' => 'Body',
        'meta_description' => 'Meta Description',
        'is_published' => 'Published',
        'published_at' => 'Published At',
    ],
];
