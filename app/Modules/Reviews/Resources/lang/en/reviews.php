<?php

declare(strict_types=1);

return [
    'verified_customer' => 'Verified Customer',
    'verified_customer_ar' => 'Verified Customer',

    'nav' => [
        'service_reviews' => 'Service Reviews',
        'vendor_reviews' => 'Vendor Reviews',
        'moderation_logs' => 'Moderation Logs',
        'review_moderation' => 'Review Moderation',
    ],

    'models' => [
        'service_review' => [
            'singular' => 'Service Review',
            'plural' => 'Service Reviews',
        ],
        'vendor_review' => [
            'singular' => 'Vendor Review',
            'plural' => 'Vendor Reviews',
        ],
        'moderation_log' => [
            'singular' => 'Moderation Log',
            'plural' => 'Moderation Logs',
        ],
        'review_response' => [
            'singular' => 'Review Response',
            'plural' => 'Review Responses',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'service' => 'Service',
        'vendor' => 'Vendor',
        'booking_item' => 'Booking Item',
        'booking_vendor' => 'Booking Vendor',
        'review_type' => 'Review Type',
        'rating' => 'Rating',
        'locale' => 'Language',
        'moderation_status' => 'Moderation Status',
        'reviewer' => 'Reviewer',
        'review_id' => 'Review ID',
        'from_status' => 'From Status',
        'to_status' => 'To Status',
        'moderator' => 'Moderator',
    ],

    'moderation_status' => [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'hidden' => 'Hidden',
    ],

    'review_type' => [
        'service' => 'Service Review',
        'vendor' => 'Vendor Review',
    ],

    'errors' => [
        'booking_item_not_completed' => 'The booking item must be completed before submitting a review.',
        'booking_vendor_items_not_all_completed' => 'All items for this vendor must be completed before submitting a review.',
        'review_already_exists' => 'A review already exists for this booking.',
        'forbidden_transition' => 'This moderation status transition is not allowed.',
        'not_found' => 'Review not found.',
        'locked_after_moderation' => 'This review can no longer be edited after moderation.',
        'forbidden' => 'You do not have permission to perform this action.',
    ],

    'validation' => [
        'rating_required' => 'Rating is required.',
        'rating_out_of_range' => 'Rating must be between 1 and 5.',
        'body_too_long' => 'Review body must not exceed 2000 characters.',
        'reason_required' => 'A reason is required when rejecting a review.',
    ],

    'actions' => [
        'approve' => 'Approve',
        'reject' => 'Reject',
        'hide' => 'Hide',
        'restore' => 'Restore',
    ],

    'labels' => [
        'rating' => 'Rating',
        'body' => 'Review Body',
        'locale' => 'Language',
        'moderation_status' => 'Moderation Status',
        'reviewer' => 'Reviewer',
        'moderated_by' => 'Moderated By',
        'moderated_at' => 'Moderated At',
        'rejection_reason' => 'Rejection Reason',
        'review_type' => 'Review Type',
        'id' => 'ID',
        'submitted_at' => 'Submitted At',
        'reason_en' => 'Reason (EN)',
        'reason_ar' => 'Reason (AR)',
    ],

    'locale_options' => [
        'en' => 'English',
        'ar' => 'Arabic',
    ],

    'bulk_actions' => [
        'approve_selected' => 'Approve Selected',
    ],

    'notifications' => [
        'approved_title' => 'Review Approved',
        'rejected_title' => 'Review Rejected',
        'hidden_title' => 'Review Hidden',
        'bulk_approved_title' => 'Selected reviews have been approved.',
    ],
];
