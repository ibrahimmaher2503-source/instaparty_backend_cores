<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum ChangeRequestItemStatus: string
{
    case Pending = 'pending';
    case Addressed = 'addressed';
    case Waived = 'waived';
}
