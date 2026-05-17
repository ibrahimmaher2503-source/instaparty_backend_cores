<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum InterventionType: string
{
    case ForceCancel = 'force_cancel';
    case VendorTimeout = 'vendor_timeout';
    case VendorProposal = 'vendor_proposal';
    case AdminNote = 'admin_note';
    case VendorReminder = 'vendor_reminder';
    case ChatFrozen = 'chat_frozen';
    case ChatResumed = 'chat_resumed';
    case CustomerReviewReminder = 'customer_review_reminder';
}
