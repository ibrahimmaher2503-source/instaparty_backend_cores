<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Enums;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function isTerminal(): bool
    {
        return $this === self::Rejected;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending  => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => in_array($target, [self::Hidden, self::Rejected], true),
            self::Hidden   => $target === self::Approved,
            self::Rejected => false,
        };
    }
}
