<?php

declare(strict_types=1);

return [
    // Navigation
    'nav_group_services' => 'Services',
    'nav_group_catalog' => 'Catalog',

    // Service statuses
    'status_draft' => 'Draft',
    'status_pending_review' => 'Pending Review',
    'status_published' => 'Published',
    'status_rejected' => 'Rejected',
    'status_archived' => 'Archived',

    // Product types
    'product_type_rental' => 'Rental',
    'product_type_sale' => 'Sale',
    'product_type_digital' => 'Digital',

    // Table / Form labels
    'code' => 'Code',
    'name' => 'Name',
    'description' => 'Description',
    'sort_order' => 'Sort Order',
    'is_active' => 'Active',
    'parent_category' => 'Parent Category',
    'no_parent' => 'No parent (top-level)',
    'allowed_product_types' => 'Allowed Product Types',
    'product_type' => 'Product Type',

    // Section headings
    'occasion_details' => 'Occasion Details',
    'category_details' => 'Category Details',

    // Shared service form
    'shared' => 'General Information',
    'category' => 'Category',
    'base_price' => 'Base Price (piastres)',
    'status' => 'Status',
    'is_featured' => 'Featured',
    'vendor' => 'Vendor',
    'media' => 'Media / Gallery',

    // Rental detail form
    'rental_details' => 'Rental Details',
    'requires_electricity' => 'Requires Electricity',
    'requires_outdoor_space' => 'Requires Outdoor Space',
    'default_rental_duration_hours' => 'Default Rental Duration (hours)',
    'setup_time_minutes' => 'Setup Time (minutes)',
    'teardown_time_minutes' => 'Teardown Time (minutes)',
    'security_deposit' => 'Security Deposit (piastres)',
    'minimum_space_sqm' => 'Minimum Space (sqm)',

    // Sale detail form
    'sale_details' => 'Sale Details',
    'is_perishable' => 'Perishable',
    'is_made_to_order' => 'Made to Order',
    'lead_time_hours' => 'Lead Time (hours)',
    'stock_quantity' => 'Stock Quantity',
    'stock_quantity_hint' => 'Leave empty for unlimited stock',
    'customization_fields' => 'Customization Fields',

    // Digital detail form
    'digital_details' => 'Digital Details',
    'delivery_method' => 'Delivery Method',
    'has_expiry' => 'Has Expiry',
    'expiry_days_after_purchase' => 'Expiry Days After Purchase',
    'is_refundable_after_delivery' => 'Refundable After Delivery',
    'redemption_url_template' => 'Redemption URL Template',

    // Translatable content section
    'translatable_fields' => 'Content (Translatable)',

    // Status nested array (for use in Filament filter options and display)
    'status' => [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],

    // Excel import
    'import_file_label' => 'Excel File (.xlsx, .xls, .csv)',
    'import_button' => 'Import',
    'import_no_vendor' => 'No vendor profile associated with your account.',
    'import_success' => ':count service(s) imported successfully.',
    'import_failed' => 'Import failed. Please review the errors below.',
    'imported_rows' => ':count row(s) imported successfully.',
    'import_failed_rows' => 'Import failed with :count error(s).',
    'row' => 'Row',
    'field' => 'Field',
    'error' => 'Error',
];
