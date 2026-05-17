<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum TriggerKind: string
{
    case System        = 'system';
    case Customer      = 'customer';
    case Vendor        = 'vendor';
    case Admin         = 'admin';
    case AdminOverride = 'admin_override';

    public function requiresReason(): bool
    {
        return $this === self::AdminOverride;
    }
}
