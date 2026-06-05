<?php

declare(strict_types=1);

return [
    'availability' => [
        'window_required' => 'A rental availability check requires starts_at and ends_at.',
    ],

    // Navigation groups
    'nav_group_services' => 'Services',
    'nav_group_catalog' => 'Catalog',

    'nav' => [
        'categories' => 'Categories',
        'occasions' => 'Occasions',
        'rental_services' => 'Rental Services',
        'sale_services' => 'Sale Services',
        'digital_services' => 'Digital Services',
        'service_themes' => 'Service Themes',
        'pending_service_edits' => 'Pending Service Edits',
        'category_field_schemas' => 'Category Field Schemas',
        'excel_imports' => 'Excel Imports',
        'inventory_reservations' => 'Inventory Reservations',
        'import_rental_services' => 'Import Rental Services',
        'import_sale_services' => 'Import Sale Services',
        'import_digital_services' => 'Import Digital Services',
    ],

    'models' => [
        'category' => [
            'singular' => 'Category',
            'plural' => 'Categories',
        ],
        'occasion' => [
            'singular' => 'Occasion',
            'plural' => 'Occasions',
        ],
        'rental_service' => [
            'singular' => 'Rental Service',
            'plural' => 'Rental Services',
        ],
        'sale_service' => [
            'singular' => 'Sale Service',
            'plural' => 'Sale Services',
        ],
        'digital_service' => [
            'singular' => 'Digital Service',
            'plural' => 'Digital Services',
        ],
        'service_theme' => [
            'singular' => 'Service Theme',
            'plural' => 'Service Themes',
        ],
        'category_field_schema' => [
            'singular' => 'Category Field Schema',
            'plural' => 'Category Field Schemas',
        ],
        'excel_import' => [
            'singular' => 'Excel Import',
            'plural' => 'Excel Imports',
        ],
        'inventory_reservation' => [
            'singular' => 'Inventory Reservation',
            'plural' => 'Inventory Reservations',
        ],
    ],

    // Service statuses
    'status_draft' => 'Draft',
    'status_pending_review' => 'Pending Review',
    'status_changes_requested' => 'Changes Requested',
    'status_published' => 'Published',
    'status_rejected' => 'Rejected',
    'status_archived' => 'Archived',

    'status' => [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'changes_requested' => 'Changes Requested',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
    ],

    // Import statuses
    'import_status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    // Product types (legacy flat keys kept for backward compat)
    'product_type_rental' => 'Rental',
    'product_type_sale' => 'Sale',
    'product_type_digital' => 'Digital',

    // Product type labels (used by ProductType::label())
    'product_types' => [
        'rental' => 'Rental',
        'sale' => 'Sale',
        'digital' => 'Digital',
    ],

    // Field types
    'field_types' => [
        'text' => 'Text',
        'number' => 'Number',
        'boolean' => 'Boolean',
        'select' => 'Single Select',
        'multiselect' => 'Multi Select',
        'date' => 'Date',
    ],

    // Hold types
    'hold_types' => [
        'cart' => 'Cart',
        'payment' => 'Payment',
    ],

    // Reservation statuses
    'reservation_status_held' => 'Held',
    'reservation_status_confirmed' => 'Confirmed',
    'reservation_status_expired' => 'Expired',
    'reservation_status_released' => 'Released',

    // Table / Form labels
    'code' => 'Code',
    'name' => 'Name',
    'description' => 'Description',
    'short_description' => 'Short Description',
    'long_description' => 'Long Description',
    'name_en' => 'Name in English',
    'name_ar' => 'Name in Arabic',
    'sort_order' => 'Display Order',
    'is_active' => 'Active',
    'parent_category' => 'Parent Category',
    'no_parent' => 'No Parent Category',
    'allowed_product_types' => 'Allowed Product Types',
    'vendor_categories' => [
        'intro_heading' => 'Categories you can operate in',
        'intro_body' => 'This is a read-only reference of the platform categories available to you, based on the product types your account is approved for. To change which product types you are approved for, visit Approval Status.',
        'manage_link' => 'Go to Approval Status',
        'empty_heading' => 'No categories available yet',
        'empty_description' => 'Once your account is approved for one or more product types, the matching categories will appear here.',
    ],
    'product_type' => 'Product Type',
    'category' => 'Category',
    'base_price' => 'Base Price',
    'status_label' => 'Status',
    'is_featured' => 'Featured',
    'vendor' => 'Vendor',
    'media' => 'Media / Gallery',
    'cover_image' => 'Cover Image',
    'service' => 'Service',
    'user_id' => 'User',
    'quantity' => 'Quantity',
    'public_id' => 'Public ID',
    'original_filename' => 'Original Filename',
    'total_rows' => 'Total Rows',
    'imported_rows_count' => 'Imported Rows',
    'error_rows' => 'Error Rows',
    'hold_type' => 'Hold Type',
    'reserved_starts_at' => 'Reservation Starts At',
    'reserved_ends_at' => 'Reservation Ends At',
    'expires_at' => 'Expires At',

    // Category field schema
    'category_field_schema_details' => 'Category Field Schema Details',
    'options' => 'Options',
    'validation_rules' => 'Validation Rules',
    'field_key' => 'Field Key',
    'field_type' => 'Field Type',
    'field_label' => 'Field Label',
    'field_label_en' => 'Field Label in English',
    'field_label_ar' => 'Field Label in Arabic',
    'advanced_schema' => 'Advanced Schema',
    'is_required' => 'Required',
    'is_filterable' => 'Filterable',
    'icon_path' => 'Icon Path',

    // Section headings
    'occasion_details' => 'Occasion Details',
    'category_details' => 'Category Details',
    'service_theme_details' => 'Service Theme Details',
    'shared' => 'General Information',

    // Rental detail form
    'rental_details' => 'Rental Details',
    'requires_electricity' => 'Requires Electricity',
    'requires_outdoor_space' => 'Requires Outdoor Space',
    'default_rental_duration_hours' => 'Default Rental Duration in Hours',
    'setup_time_minutes' => 'Setup Time in Minutes',
    'teardown_time_minutes' => 'Teardown Time in Minutes',
    'security_deposit' => 'Security Deposit in Piastres',
    'minimum_space_sqm' => 'Minimum Required Space in Square Meters',

    // Sale detail form
    'sale_details' => 'Sale Details',
    'is_perishable' => 'Perishable',
    'is_made_to_order' => 'Made to Order',
    'lead_time_hours' => 'Lead Time in Hours',
    'stock_quantity' => 'Available Stock',
    'stock_quantity_hint' => 'Leave empty for unlimited stock.',
    'customization_fields' => 'Customization Fields',

    // Digital detail form
    'digital_details' => 'Digital Service Details',
    'delivery_method' => 'Delivery Method',
    'has_expiry' => 'Has Expiry Period',
    'expiry_days_after_purchase' => 'Expiry Days After Purchase',
    'is_refundable_after_delivery' => 'Refundable After Delivery',
    'redemption_url_template' => 'Redemption URL Template',

    // Translatable content section
    'translatable_fields' => 'Translatable Content',

    // Excel import
    'download_template' => 'Download Template',
    'import_file_label' => 'Excel or CSV File',
    'import_button' => 'Import',
    'import_no_vendor' => 'No vendor profile is associated with your account.',
    'import_success' => ':count service(s) imported successfully.',
    'import_failed' => 'Import failed. Please review the errors below.',
    'imported_rows' => ':count row(s) imported successfully.',
    'import_failed_rows' => 'Import failed with :count error(s).',
    'row' => 'Row',
    'field' => 'Field',
    'error' => 'Error',

    // Import history & error review (feature 039)
    'import_details' => 'Import Details',
    'import_history' => 'Import History',
    'view_errors' => 'View Errors',
    'import_errors_for' => 'Import Errors: :filename',
    'row_number' => 'Row #',
    'field_name' => 'Field',
    'entered_value' => 'Entered Value',
    'validation_message' => 'Validation Message',
    'required_fix' => 'Required Fix',
    'no_failed_rows' => 'No failed rows to export',
    'download_failed_rows' => 'Download Failed Rows',
    'retry_import' => 'Retry Import',
    'retry_import_queued' => 'New import started. Check Import History for progress.',

    // Fix hints
    'fix_hint_required' => 'This field is required',
    'fix_hint_integer' => 'Must be a whole number',
    'fix_hint_min_0' => 'Must be 0 or greater',
    'fix_hint_min_1' => 'Must be 1 or greater',
    'fix_hint_max_255' => 'Maximum 255 characters',
    'fix_hint_boolean' => 'Must be true (1) or false (0)',
    'fix_hint_string' => 'Must be text',
    'fix_hint_default' => 'Check the value and try again',

    // Moderation actions
    'approve_publish' => 'Approve & Publish',
    'approve_publish_heading' => 'Approve and publish service',
    'approve_publish_description' => 'This publishes the service and removes it from pending review.',
    'approve_publish_success' => 'Service published successfully.',
    'approve_publish_failed' => 'Cannot publish service',
    'approve_selected' => 'Approve selected',
    'approve_selected_heading' => 'Approve selected services',
    'approve_selected_description' => 'All selected pending services will be published.',
    'approve_selected_success' => ':count service(s) published successfully.',
    'reject_service' => 'Reject',
    'reject_service_heading' => 'Reject service',
    'reject_service_description' => 'Enter the rejection reason in both English and Arabic.',
    'reject_reason_en' => 'Rejection reason in English',
    'reject_reason_ar' => 'Rejection reason in Arabic',
    'reject_success' => 'Service rejected successfully.',
    'reject_selected' => 'Reject selected',
    'reject_selected_heading' => 'Reject selected services',
    'reject_selected_description' => 'The shared bilingual reason will be saved on every selected service.',
    'reject_selected_success' => ':count service(s) rejected successfully.',
    'archive_selected' => 'Archive selected',
    'archive_selected_heading' => 'Archive selected services',
    'archive_selected_description' => 'Selected eligible services will be archived.',
    'archive_selected_success' => ':count service(s) archived successfully.',
    'request_changes' => 'Request Edits',
    'change_items' => 'Requested edits',
    'field_path' => 'Field',
    'requested_change_en' => 'Requested change in English',
    'requested_change_ar' => 'Requested change in Arabic',
    'changes_requested' => 'Edits requested',
    'changes_requested_message' => 'The service was moved to changes requested.',
    'pending_rental_services' => 'Pending Rental Services',
    'pending_sale_services' => 'Pending Sale Services',
    'pending_digital_services' => 'Pending Digital Services',
    'pending_queue_empty_rental' => 'No rental services are pending review.',
    'pending_queue_empty_sale' => 'No sale services are pending review.',
    'pending_queue_empty_digital' => 'No digital services are pending review.',
    'moderation_not_allowed' => 'You do not have permission to moderate this service.',
    'moderation_invalid_transition' => 'This service can no longer use that moderation action.',
    'moderation_conflict' => ':count selected service(s) could not be processed because their status changed.',
    'moderation_notes' => 'Moderation Notes',

    'errors' => [
        'not_owned' => 'This service does not belong to your profile.',
        'cannot_submit' => 'This service cannot be submitted for review in its current status.',
        'not_approved_for_type' => 'You are not approved to operate this product type.',
    ],

    // Vendor portal labels
    'vendor_portal' => [
        'submit_for_review' => 'Submit for Review',
        'clone' => 'Clone',
        'cloned' => 'Service cloned. You are now editing the copy.',
        'block_dates' => 'Block Dates',
        'unblock' => 'Remove Block',
        'blocked' => 'Dates blocked.',
        'unblocked' => 'Block removed.',
        'availability_title' => 'Availability Blocks',
        'cannot_edit_published' => 'Material edits will send this service back to moderation.',
    ],
    'category_has_children' => 'Cannot delete a category that has subcategories. Move or delete the children first.',
    'reorder_unknown_categories' => 'One or more categories in the supplied order list do not exist.',
    'reorder_parent_mismatch' => 'All categories in a single reorder operation must share the same parent.',

    // Delivery method options
    'delivery_methods' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'link' => 'Link',
        'in_app' => 'In-App',
    ],

    // Translatable field labels (for manual JSON-column forms)
    'fields' => [
        'name_en' => 'Name (English)',
        'description_en' => 'Description (English)',
        'name_ar' => 'الاسم (عربي)',
        'description_ar' => 'الوصف (عربي)',
    ],

    // Money helper / suffix
    'piastres_suffix' => 'Piastres',
    'price_helper' => 'In piastres — 10000 = 100.00 EGP',

    // Admin inline-import action (table header action)
    'import' => [
        'rental_label' => 'Import Rental Services',
        'sale_label' => 'Import Sale Services',
        'digital_label' => 'Import Digital Services',
        'vendor' => 'Vendor',
        'file' => 'Excel File (.xlsx / .xls)',
        'modal_heading_rental' => 'Import Rental Services',
        'modal_heading_sale' => 'Import Sale Services',
        'modal_heading_digital' => 'Import Digital Services',
        'modal_submit' => 'Import',
        'completed' => 'Import completed: :count service(s) created.',
        'failed_row_errors' => 'Import failed — check per-row errors on the import record.',
    ],
];
