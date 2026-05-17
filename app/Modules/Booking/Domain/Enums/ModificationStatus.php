<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum ModificationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case CustomerAccepted = 'customer_accepted';
    case CustomerRejected = 'customer_rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
