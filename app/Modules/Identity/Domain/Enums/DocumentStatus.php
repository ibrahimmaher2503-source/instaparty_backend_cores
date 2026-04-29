<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
