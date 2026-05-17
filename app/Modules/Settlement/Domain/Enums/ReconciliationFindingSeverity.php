<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum ReconciliationFindingSeverity: string
{
    case Info    = 'info';
    case Warning = 'warning';
    case High    = 'high';
}
