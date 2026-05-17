<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum ChecklistItemStatus: string
{
    case Complete = 'complete';
    case Pending  = 'pending';
    case Warning  = 'warning';
    case Danger   = 'danger';
    case Info     = 'info';
}
