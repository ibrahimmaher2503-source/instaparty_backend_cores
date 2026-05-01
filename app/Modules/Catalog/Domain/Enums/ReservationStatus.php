<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

enum ReservationStatus: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Released = 'released';

    public function label(): string
    {
        return __('catalog.reservation_status_' . $this->value);
    }
}
