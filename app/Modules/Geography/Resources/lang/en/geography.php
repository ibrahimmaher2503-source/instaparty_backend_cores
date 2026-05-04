<?php

declare(strict_types=1);

return [
    'country' => 'Country',
    'governorate' => 'Governorate',
    'region' => 'Region',
    'city' => 'City',

    'countries' => 'Countries',
    'governorates' => 'Governorates',
    'regions' => 'Regions',
    'cities' => 'Cities',

    'columns' => [
        'name' => 'Name',
        'name_en' => 'Name (EN)',
        'name_ar' => 'Name (AR)',
        'code' => 'Code',
        'iso2' => 'ISO2',
        'iso3' => 'ISO3',
        'default_currency' => 'Default Currency',
        'default_locale' => 'Default Locale',
        'default_timezone' => 'Default Timezone',
        'phone_code' => 'Phone Code',
        'country' => 'Country',
        'governorate' => 'Governorate',
        'region' => 'Region',
        'sort_order' => 'Sort Order',
        'is_active' => 'Active',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'created_at' => 'Created At',
    ],

    'errors' => [
        'delete_region_has_cities' => 'Cannot delete this region because it has cities assigned to it.',
        'delete_governorate_has_regions' => 'Cannot delete this governorate because it has regions assigned to it.',
    ],

    'filters' => [
        'is_active' => 'Active',
        'country' => 'Country',
        'governorate' => 'Governorate',
        'region' => 'Region',
    ],
];
