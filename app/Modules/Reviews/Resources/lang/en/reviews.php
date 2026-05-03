<?php

declare(strict_types=1);

return [
    'verified_customer'    => 'Verified Customer',
    'verified_customer_ar' => 'عميل موثق',

    'moderation_status' => [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'hidden'   => 'Hidden',
    ],

    'review_type' => [
        'service' => 'Service Review',
        'vendor'  => 'Vendor Review',
    ],

    'errors' => [
        'booking_item_not_completed'              => 'The booking item must be completed before submitting a review.',
        'booking_vendor_items_not_all_completed'  => 'All items for this vendor must be completed before submitting a review.',
        'review_already_exists'                   => 'A review already exists for this booking.',
        'forbidden_transition'                    => 'This moderation status transition is not allowed.',
        'not_found'                               => 'Review not found.',
        'forbidden'                               => 'You do not have permission to perform this action.',
    ],

    'validation' => [
        'rating_required'     => 'Rating is required.',
        'rating_out_of_range' => 'Rating must be between 1 and 5.',
        'body_too_long'       => 'Review body must not exceed 2000 characters.',
        'reason_required'     => 'A reason is required when rejecting a review.',
    ],

    'actions' => [
        'approve'  => 'Approve',
        'reject'   => 'Reject',
        'hide'     => 'Hide',
        'restore'  => 'Restore',
    ],

    'labels' => [
        'rating'             => 'Rating',
        'body'               => 'Review',
        'locale'             => 'Language',
        'moderation_status'  => 'Status',
        'reviewer'           => 'Reviewer',
        'moderated_by'       => 'Moderated By',
        'moderated_at'       => 'Moderated At',
        'rejection_reason'   => 'Rejection Reason',
        'review_type'        => 'Review Type',
    ],

    'notifications' => [
        'approved_title'  => 'Review Approved',
        'rejected_title'  => 'Review Rejected',
        'hidden_title'    => 'Review Hidden',
    ],
];
