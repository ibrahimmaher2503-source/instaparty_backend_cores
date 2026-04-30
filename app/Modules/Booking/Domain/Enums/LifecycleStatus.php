<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum LifecycleStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case VendorReview = 'vendor_review';
    case CustomerReview = 'customer_review';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
