<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum ChangeRequestSubjectType: string
{
    case VendorProfile = 'vendor_profile';
    case Service = 'service';
}
