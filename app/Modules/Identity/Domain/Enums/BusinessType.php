<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum BusinessType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Establishment = 'establishment';
}
