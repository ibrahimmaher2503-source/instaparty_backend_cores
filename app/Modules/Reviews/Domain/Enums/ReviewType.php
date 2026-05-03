<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Enums;

enum ReviewType: string
{
    case Service = 'service';
    case Vendor = 'vendor';
}
