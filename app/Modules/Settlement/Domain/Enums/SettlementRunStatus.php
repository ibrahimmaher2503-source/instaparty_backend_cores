<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum SettlementRunStatus: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Disputed = 'disputed';
}
