<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum ChatFreezeCategory: string
{
    case OffPlatformContact = 'off_platform_contact';
    case PolicyViolation = 'policy_violation';
    case Harassment = 'harassment';
    case Other = 'other';
}
