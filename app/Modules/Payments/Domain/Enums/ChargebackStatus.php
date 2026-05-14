<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Enums;

enum ChargebackStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under Review',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::UnderReview => 'info',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function isResolved(): bool
    {
        return match ($this) {
            self::Won, self::Lost => true,
            default => false,
        };
    }
}
