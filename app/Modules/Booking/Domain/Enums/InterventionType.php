<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum InterventionType: string
{
    case ForceCancel = 'force_cancel';
    case VendorTimeout = 'vendor_timeout';
    case VendorProposal = 'vendor_proposal';
    case AdminNote = 'admin_note';
}
