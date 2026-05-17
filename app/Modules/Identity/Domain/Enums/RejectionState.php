<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum RejectionState: string
{
    case None             = 'none';
    case ChangesRequested = 'changes_requested';
    case Rejected         = 'rejected';
}
