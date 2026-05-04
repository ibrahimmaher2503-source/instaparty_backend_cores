<?php

declare(strict_types=1);

return [
    // General
    'vendor_profile' => 'Vendor Profile',
    'vendor_profiles' => 'Vendor Profiles',
    'vendor_document' => 'Vendor Document',
    'vendor_documents' => 'Vendor Documents',
    'customer' => 'Customer',
    'vendor' => 'Vendor',
    'admin' => 'Admin',

    // Navigation labels
    'nav' => [
        'users' => 'Users',
        'customer_profiles' => 'Customer Profiles',
        'customer_addresses' => 'Customer Addresses',
        'devices' => 'User Devices',
        'coverage_areas' => 'Coverage Areas',
        'business_hours' => 'Business Hours',
        'approved_product_types' => 'Approved Product Types',
        'queue' => 'Approval Queue',
        'all_vendors' => 'All Vendors',
        'vendor_management' => 'Vendor Management',
        'documents' => 'Documents',
        'customers' => 'Customers',
    ],

    'models' => [
        'user' => [
            'singular' => 'User',
            'plural' => 'Users',
        ],
        'customer_profile' => [
            'singular' => 'Customer Profile',
            'plural' => 'Customer Profiles',
        ],
        'customer_address' => [
            'singular' => 'Customer Address',
            'plural' => 'Customer Addresses',
        ],
        'user_device' => [
            'singular' => 'User Device',
            'plural' => 'User Devices',
        ],
        'vendor_profile' => [
            'singular' => 'Vendor Profile',
            'plural' => 'Vendor Profiles',
        ],
        'vendor_document' => [
            'singular' => 'Vendor Document',
            'plural' => 'Vendor Documents',
        ],
        'coverage_area' => [
            'singular' => 'Coverage Area',
            'plural' => 'Coverage Areas',
        ],
        'business_hour' => [
            'singular' => 'Business Hour',
            'plural' => 'Business Hours',
        ],
        'approved_product_type' => [
            'singular' => 'Approved Product Type',
            'plural' => 'Approved Product Types',
        ],
    ],

    // Infolist / form section titles
    'sections' => [
        'identity' => 'Identity',
        'business_profile' => 'Business Profile',
        'banking' => 'Banking Details',
        'uploaded_documents' => 'Uploaded Documents',
        'approved_product_types' => 'Approved Product Types',
        'business_hours' => 'Business Hours',
        'coverage_areas' => 'Coverage Areas',
        'services' => 'Services',
        'bookings' => 'Bookings',
        'wallet' => 'Wallet',
        'withdrawals' => 'Withdrawals',
        'reviews' => 'Reviews',
        'activity' => 'Activity Log',
        'documents' => 'Documents',
    ],

    // Table column labels
    'columns' => [
        'id' => 'ID',
        'name' => 'Name',
        'user_id' => 'User',
        'business_name' => 'Business Name',
        'business_type' => 'Business Type',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'phone_verified' => 'Phone Verified',
        'docs' => 'Documents',
        'vendor' => 'Vendor',
        'doc_type' => 'Document Type',
        'file_name' => 'File Name',
        'status' => 'Status',
        'approval_status' => 'Approval Status',
        'created_at' => 'Created At',
        'download' => 'Download',
        'slug' => 'Slug',
        'governorate' => 'Governorate',
        'city' => 'City',
        'city_id' => 'City',
        'label' => 'Label',
        'recipient_name' => 'Recipient Name',
        'recipient_phone' => 'Recipient Phone',
        'is_default' => 'Default Address',
        'bank_name' => 'Bank Name',
        'bank_account_holder' => 'Account Holder',
        'bank_iban' => 'IBAN',
        'bank_swift_bic' => 'SWIFT / BIC',
        'active_type_approvals' => 'Active Product Type Approvals',
        'date_of_birth' => 'Date of Birth',
        'gender' => 'Gender',
        'accepts_marketing' => 'Accepts Marketing',
        'platform' => 'Platform',
        'device_id' => 'Device ID',
        'last_seen_at' => 'Last Seen At',
        'delivery_fee' => 'Delivery Fee',
        'min_order' => 'Minimum Order',
        'day_of_week' => 'Day of Week',
        'opens_at' => 'Opens At',
        'closes_at' => 'Closes At',
        'product_type' => 'Product Type',
        'approved_at' => 'Approved At',
        'revoked_at' => 'Revoked At',
        'role' => 'Role',
    ],

    // Filament action labels
    'actions' => [
        'review' => 'Review',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'suspend' => 'Suspend',
        'unsuspend' => 'Unsuspend',
        'approve_for_type' => 'Approve Product Type',
        'revoke_type' => 'Revoke Product Type Approval',
        'download' => 'View',
        'suspend_customer' => 'Suspend Customer',
        'unsuspend_customer' => 'Unsuspend Customer',
        'force_logout' => 'Force Logout',
        'edit_profile' => 'Edit Profile',
        'impersonate' => 'Impersonate Vendor',
        'replace_coverage' => 'Replace Coverage Areas',
        're_upload_document' => 'Re-upload Document',
    ],

    // Form field labels
    'forms' => [
        'rejection_reason_en' => 'Rejection Reason in English',
        'rejection_reason_ar' => 'Rejection Reason in Arabic',
        'revoke_reason_en' => 'Revocation Reason in English',
        'revoke_reason_ar' => 'Revocation Reason in Arabic',
        'business_name_en' => 'Business Name in English',
        'business_name_ar' => 'Business Name in Arabic',
        'bio_en' => 'Bio in English',
        'bio_ar' => 'Bio in Arabic',
    ],

    // Notification titles
    'notifications' => [
        'customer_suspended' => 'Customer suspended',
        'customer_unsuspended' => 'Customer unsuspended',
        'customer_logged_out' => 'Customer logged out',
        'profile_updated_by_admin' => 'Customer profile updated',
        'vendor_approved' => 'Vendor profile approved',
        'vendor_rejected' => 'Vendor profile rejected',
        'vendor_suspended' => 'Vendor suspended',
        'type_approved' => 'Vendor approved for product type: :type',
        'type_revoked' => 'Product type approval revoked',
        'signed_url_generated' => 'Signed URL generated',
        'impersonation_token' => 'Impersonation Token (expires in 30 min)',
        'coverage_updated' => 'Coverage areas updated successfully',
        'document_uploaded' => 'Document replaced successfully',
        'vendor_profile_updated' => 'Vendor profile updated',
    ],

    // Confirmation dialog content
    'confirmations' => [
        'impersonate_warning' => 'You are about to impersonate this vendor. This action will be logged in the audit trail. The token expires in 30 minutes.',
    ],

    // Placeholder strings
    'placeholders' => [
        'not_verified' => '— Not verified —',
        'none' => '— None —',
        'dash' => '—',
        'no_documents' => 'No documents uploaded',
        's3_not_configured' => '— S3 is not configured —',
        'open_document' => 'Open Document',
    ],

    // Field labels
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'password' => 'Password',
        'business_name' => 'Business Name',
        'business_type' => 'Business Type',
        'bio' => 'Bio',
        'slug' => 'Slug',
        'doc_type' => 'Document Type',
        'file' => 'File',
        'city_id' => 'City',
        'governorate_id' => 'Governorate',
        'delivery_fee' => 'Delivery Fee',
        'min_order' => 'Minimum Order',
        'product_type' => 'Product Type',
        'bank_iban' => 'IBAN',
        'bank_name' => 'Bank Name',
        'bank_account_holder' => 'Account Holder',
        'bank_swift_bic' => 'SWIFT / BIC',
        'bank_branch' => 'Bank Branch',
        'opens_at' => 'Opens At',
        'closes_at' => 'Closes At',
        'day_of_week' => 'Day of Week',
        'label' => 'Label',
        'address_line' => 'Address',
        'recipient_name' => 'Recipient Name',
        'recipient_phone' => 'Recipient Phone',
        'is_default' => 'Set as Default',
    ],

    // Role labels (Spatie roles)
    'roles' => [
        'customer' => 'Customer',
        'vendor' => 'Vendor',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
        'booking_manager' => 'Booking Manager',
    ],

    // Approval status labels
    'status' => [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ],

    // Document status labels
    'document_status' => [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    // Document type labels
    'document_type' => [
        'cr' => 'Commercial Register',
        'tax_card' => 'Tax Card',
        'national_id' => 'National ID',
        'iban_proof' => 'IBAN Proof',
        'other' => 'Other',
    ],

    // Business type labels
    'business_type' => [
        'individual' => 'Individual',
        'company' => 'Company',
        'establishment' => 'Establishment',
    ],

    // Product type labels
    'product_type' => [
        'rental' => 'Rental',
        'sale' => 'Sale',
        'digital' => 'Digital',
    ],

    // Day of week labels
    'day_of_week' => [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ],

    // Customer management keys
    'account_suspended' => 'Your account has been suspended. Contact support for assistance.',
    'customer_already_suspended' => 'This account is already suspended.',
    'customer_not_suspended' => 'This account is not currently suspended.',
    'admin_cannot_self_suspend' => 'You cannot suspend your own account.',

    // Customer tabs
    'tabs' => [
        'overview' => 'Overview',
        'bookings' => 'Bookings',
        'reviews' => 'Reviews',
        'wallet' => 'Wallet',
        'addresses' => 'Addresses',
        'activity' => 'Activity',
    ],

    // Generic error keys (used in Actions and API responses)
    'invalid_credentials' => 'The provided credentials are incorrect.',
    'user_not_found' => 'No account was found with the given phone number.',
    'invalid_otp' => 'The verification code is incorrect or has expired.',

    // Validation messages
    'validation' => [
        'phone_e164' => 'Phone number must be in E.164 format, for example: +201001234567.',
        'otp_invalid' => 'The verification code is incorrect or has expired.',
        'otp_throttled' => 'Too many verification attempts. Please try again later.',
        'duplicate_phone' => 'This phone number is already registered.',
        'duplicate_email' => 'This email address is already registered.',
        'business_name_en_required' => 'The business name in English is required.',
        'business_name_ar_required' => 'The business name in Arabic is required.',
        'city_not_found' => 'The selected city does not exist.',
        'doc_type_invalid' => 'Invalid document type.',
        'file_too_large' => 'File size must not exceed 10 MB.',
        'file_mime_invalid' => 'Only PDF, JPEG, and PNG files are accepted.',
        'vendor_not_approved' => 'The vendor profile must be approved before granting product-type permissions.',
        'type_approval_not_found' => 'Product type approval was not found or has already been revoked.',
    ],

    // Success messages
    'messages' => [
        'registered' => 'Registration successful. Please verify your phone number.',
        'phone_verified' => 'Phone number verified successfully.',
        'logged_in' => 'Logged in successfully.',
        'logged_out' => 'Logged out successfully.',
        'profile_updated' => 'Profile updated successfully.',
        'document_uploaded' => 'Document uploaded successfully.',
        'coverage_area_added' => 'Coverage area added successfully.',
        'business_hours_updated' => 'Business hours updated successfully.',
        'address_added' => 'Address added successfully.',
        'address_deleted' => 'Address removed.',
        'vendor_approved' => 'Vendor profile approved.',
        'vendor_rejected' => 'Vendor profile rejected.',
        'vendor_suspended' => 'Vendor suspended.',
        'type_approved' => 'Vendor approved for product type: :type.',
        'type_revoked' => 'Product type approval revoked.',
    ],
    'days' => [
        'sunday'    => 'Sunday',
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
    ],
];
