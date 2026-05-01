<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum VendorSubStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Modified = 'modified';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
