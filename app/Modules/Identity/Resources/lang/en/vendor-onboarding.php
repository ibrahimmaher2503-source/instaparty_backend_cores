<?php

declare(strict_types=1);

return [
    'rows' => [
        'profile' => [
            'label' => 'Business profile',
            'cta'   => 'Complete business profile',
        ],
        'banking' => [
            'label' => 'Banking details',
            'cta'   => 'Add banking details',
        ],
        'docs_uploaded' => [
            'label' => 'Documents uploaded',
            'cta'   => 'Upload required documents',
        ],
        'docs_approved' => [
            'label' => 'Documents approved',
            'cta'   => 'Upload new copy',
        ],
        'coverage' => [
            'label' => 'Coverage area',
            'cta'   => 'Add a coverage area',
        ],
        'hours' => [
            'label' => 'Business hours',
            'cta'   => 'Set business hours',
        ],
        'service_drafted' => [
            'label' => 'First service added',
            'cta'   => 'Add your first service',
        ],
        'service_submitted' => [
            'label' => 'Service submitted for review',
            'cta'   => 'Submit a service for review',
        ],
        'approval_status' => [
            'label' => 'Account approval',
            'cta'   => null,
        ],
        'approved_types' => [
            'label' => 'Approved product types',
            'cta'   => null,
        ],
    ],

    'status' => [
        'complete' => 'Complete',
        'pending'  => 'Pending',
        'warning'  => 'Attention needed',
        'danger'   => 'Action required',
        'info'     => 'In review',
    ],

    'progress' => ':done of :total complete',

    'cta' => [
        'next_action'      => 'Next recommended action',
        'onboarding_done'  => 'Onboarding complete',
        'resubmit_profile' => 'Edit profile and resubmit',
        'upload_new_copy'  => 'Upload new copy',
    ],

    'banner' => [
        'rejected_heading'          => 'Your account has been rejected',
        'changes_requested_heading' => 'Changes requested',
        'suspended_heading'         => 'Account suspended',
        'suspended_since'           => 'Suspended since :date',
    ],

    'approved_types' => [
        'sub_text' => 'Approved for: :types',
        'rental'   => 'Rental',
        'sale'     => 'Sale',
        'digital'  => 'Digital',
    ],

    'approval_status' => [
        'pending'           => 'Awaiting admin review',
        'approved'          => 'Account approved',
        'changes_requested' => 'Changes requested by admin',
        'rejected'          => 'Account rejected',
    ],
];
