<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum LedgerDirection: string
{
    case Debit  = 'debit';
    case Credit = 'credit';
}
