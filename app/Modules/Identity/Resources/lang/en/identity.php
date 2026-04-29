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
        'queue' => 'Approval Queue',
        'all_vendors' => 'All Vendors',
        'documents' => 'Documents',
    ],

    // Infolist / form section titles
    'sections' => [
        'identity' => 'Identity',
        'business_profile' => 'Business Profile',
        'banking' => 'Banking',
        'uploaded_documents' => 'Uploaded Documents',
        'approved_product_types' => 'Approved Product Types',
    ],

    // Table column labels
    'columns' => [
        'id' => 'ID',
        'business_name' => 'Business Name',
        'business_type' => 'Business Type',
        'email' => 'Email',
        'phone' => 'Phone',
        'phone_verified' => 'Phone Verified',
        'docs' => 'Docs',
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
        'bank_name' => 'Bank Name',
        'bank_account_holder' => 'Account Holder',
        'bank_iban' => 'IBAN',
        'bank_swift_bic' => 'SWIFT / BIC',
        'active_type_approvals' => 'Active type approvals',
    ],

    // Filament action labels (resource-specific)
    'actions' => [
        'review' => 'Review',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'suspend' => 'Suspend',
        'approve_for_type' => 'Approve for Type',
        'revoke_type' => 'Revoke Type',
        'download' => 'View',
    ],

    // Form field labels (Filament forms in modals)
    'forms' => [
        'rejection_reason_en' => 'Rejection Reason (EN)',
        'rejection_reason_ar' => 'Rejection Reason (AR)',
        'revoke_reason_en' => 'Revoke Reason (EN)',
        'revoke_reason_ar' => 'Revoke Reason (AR)',
        'business_name_en' => 'Business Name (EN)',
        'business_name_ar' => 'Business Name (AR)',
        'bio_en' => 'Bio (EN)',
        'bio_ar' => 'Bio (AR)',
    ],

    // Notification titles (in-app toasts)
    'notifications' => [
        'vendor_approved' => 'Vendor profile approved',
        'vendor_rejected' => 'Vendor profile rejected',
        'vendor_suspended' => 'Vendor suspended',
        'type_approved' => 'Vendor approved for :type',
        'type_revoked' => 'Type approval revoked',
        'signed_url_generated' => 'Signed URL generated',
    ],

    // Placeholder strings shown when no data
    'placeholders' => [
        'not_verified' => '— not verified —',
        'none' => '— none —',
        'dash' => '—',
        'no_documents' => 'No documents uploaded',
        's3_not_configured' => '— S3 not configured —',
        'open_document' => 'Open document',
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
        'bank_branch' => 'Branch',
        'opens_at' => 'Opens At',
        'closes_at' => 'Closes At',
        'day_of_week' => 'Day of Week',
        'label' => 'Label',
        'address_line' => 'Address',
        'recipient_name' => 'Recipient Name',
        'recipient_phone' => 'Recipient Phone',
        'is_default' => 'Set as Default',
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
        'pending' => 'Pending',
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

    // Validation messages
    'validation' => [
        'phone_e164' => 'Phone number must be in E.164 format (e.g. +201001234567).',
        'otp_invalid' => 'The OTP code is incorrect or expired.',
        'otp_throttled' => 'Too many OTP attempts. Please try again later.',
        'duplicate_phone' => 'This phone number is already registered.',
        'duplicate_email' => 'This email address is already registered.',
        'business_name_en_required' => 'English business name is required.',
        'business_name_ar_required' => 'Arabic business name is required.',
        'city_not_found' => 'The selected city does not exist.',
        'doc_type_invalid' => 'Invalid document type.',
        'file_too_large' => 'File size must not exceed 10 MB.',
        'file_mime_invalid' => 'Only PDF, JPEG, and PNG files are accepted.',
        'vendor_not_approved' => 'Vendor profile must be approved before granting product-type permissions.',
        'type_approval_not_found' => 'Type approval not found or already revoked.',
    ],

    // Success messages
    'messages' => [
        'registered' => 'Registration successful. Please verify your phone.',
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
        'type_approved' => 'Vendor approved for :type.',
        'type_revoked' => 'Type approval revoked.',
    ],
];
