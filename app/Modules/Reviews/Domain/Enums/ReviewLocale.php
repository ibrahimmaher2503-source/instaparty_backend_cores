<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Enums;

enum ReviewLocale: string
{
    case Ar = 'ar';
    case En = 'en';
    case Mixed = 'mixed';
}
