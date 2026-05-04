<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum CustomerConsentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ConsentExpired = 'consent_expired';
}
