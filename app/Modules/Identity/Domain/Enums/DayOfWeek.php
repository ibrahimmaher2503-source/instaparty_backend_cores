<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum DayOfWeek: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sunday => __('identity.days.sunday'),
            self::Monday => __('identity.days.monday'),
            self::Tuesday => __('identity.days.tuesday'),
            self::Wednesday => __('identity.days.wednesday'),
            self::Thursday => __('identity.days.thursday'),
            self::Friday => __('identity.days.friday'),
            self::Saturday => __('identity.days.saturday'),
        };
    }
}
