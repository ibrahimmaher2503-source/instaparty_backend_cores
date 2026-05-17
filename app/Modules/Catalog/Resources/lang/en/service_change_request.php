<?php

declare(strict_types=1);

return [
    // Status labels
    'status_pending'                       => 'Pending Review',
    'status_awaiting_clarification'        => 'Awaiting Clarification',
    'status_approved'                      => 'Approved',
    'status_rejected'                      => 'Rejected',
    'status_cancelled_vendor_suspended'    => 'Cancelled (Vendor Suspended)',
    'status_cancelled_service_unavailable' => 'Cancelled (Service Unavailable)',

    // Validation messages
    'admin_note_en_required'               => 'An English admin note is required.',
    'admin_note_ar_required'               => 'An Arabic admin note is required.',
    'body_en_required'                     => 'An English message body is required.',
    'body_ar_required'                     => 'An Arabic message body is required.',
    'clarification_cap_reached'            => 'The maximum number of clarification rounds (:max) has been reached.',
    'pending_request_exists'               => 'You already have a pending edit awaiting admin review.',
    'version_mismatch'                     => 'This change request was already decided by another admin. Please reload.',
    'not_found'                            => 'Change request not found.',

    // Notification subjects
    'notification_approved_subject'        => 'Your service edit has been approved',
    'notification_rejected_subject'        => 'Your service edit was not approved',
    'notification_clarification_subject'   => 'Admin has a question about your service edit',

    // Notification bodies
    'notification_approved_body'           => 'Your proposed changes to ":service" have been approved and are now live.',
    'notification_rejected_body'           => 'Your proposed changes to ":service" were not approved. Reason: :reason',
    'notification_clarification_body'      => 'Admin has asked a question about your pending edit for ":service". Please reply to continue.',
];
