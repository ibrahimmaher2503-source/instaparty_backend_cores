<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum NotificationAudience: string
{
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Vendor => 'Vendor',
            self::Admin => 'Admin',
        };
    }
}
