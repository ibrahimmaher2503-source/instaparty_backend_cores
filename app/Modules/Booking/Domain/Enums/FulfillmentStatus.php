<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum FulfillmentStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Failed = 'failed';
}
