<?php

declare(strict_types=1);

return [
    'wishlist' => [
        'created' => 'Wishlist created successfully.',
        'updated' => 'Wishlist updated successfully.',
        'deleted' => 'Wishlist deleted successfully.',
        'item_added' => 'Service added to wishlist.',
        'item_removed' => 'Service removed from wishlist.',
        'already_exists' => 'This service is already in your wishlist.',
    ],

    'search' => [
        'no_results' => 'No services found matching your search.',
    ],

    'saved_search' => [
        'saved' => 'Search saved successfully.',
        'deleted' => 'Saved search deleted successfully.',
    ],

    'reindex_services' => 'Reindex Services',
    'reindex_success' => 'Service reindexing has been queued successfully.',
    'reindex_confirm' => 'This will queue a full reindex of all published services. Do you want to continue?',
    'wishlist_added' => 'Service added to wishlist.',
    'wishlist_removed' => 'Service removed from wishlist.',
    'search_placeholder' => 'Search services...',

    'nav' => [
        'saved_searches' => 'Saved Searches',
        'search_logs' => 'Search Logs',
        'wishlists' => 'Wishlists',
    ],

    'models' => [
        'saved_search' => [
            'singular' => 'Saved Search',
            'plural' => 'Saved Searches',
        ],
        'search_log' => [
            'singular' => 'Search Log',
            'plural' => 'Search Logs',
        ],
        'wishlist' => [
            'singular' => 'Wishlist',
            'plural' => 'Wishlists',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'user_id' => 'User',
        'label' => 'Label',
        'filters' => 'Filters',
        'query' => 'Search Query',
        'locale' => 'Language',
        'results_count' => 'Results Count',
        'clicked_service_id' => 'Clicked Service',
        'name' => 'Name',
        'items_count' => 'Items Count',
    ],
];
