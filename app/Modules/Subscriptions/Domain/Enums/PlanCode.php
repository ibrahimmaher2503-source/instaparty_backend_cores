<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

enum PlanCode: string
{
    case Free = 'free';
    case Silver = 'silver';
    case Gold = 'gold';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Premium => 'Premium',
        };
    }

    public function isFreeTier(): bool
    {
        return $this === self::Free;
    }

    /** Returns tiers ranked lower than this one (useful for determining "unblocking" tier). */
    public function upgradeOptions(): array
    {
        return match ($this) {
            self::Free => [self::Silver, self::Gold, self::Premium],
            self::Silver => [self::Gold, self::Premium],
            self::Gold => [self::Premium],
            self::Premium => [],
        };
    }

    public function firstUpgrade(): ?self
    {
        return $this->upgradeOptions()[0] ?? null;
    }
}
