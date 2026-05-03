<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Enums;

enum CommissionStatus: string
{
    case Calculated = 'calculated';
    case PartiallyReversed = 'partially_reversed';
    case Reversed = 'reversed';
}
