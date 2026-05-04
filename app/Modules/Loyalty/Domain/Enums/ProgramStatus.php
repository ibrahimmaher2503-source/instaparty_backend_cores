<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Enums;

enum ProgramStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function allowsEarning(): bool
    {
        return $this === self::Active;
    }

    public function allowsRedemption(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        };
    }
}
