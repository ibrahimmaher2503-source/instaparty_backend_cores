<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum ChangeRequestStatus: string
{
    case Open = 'open';
    case Resubmitted = 'resubmitted';
    case Resolved = 'resolved';
    case EscalatedToRejection = 'escalated_to_rejection';
}
