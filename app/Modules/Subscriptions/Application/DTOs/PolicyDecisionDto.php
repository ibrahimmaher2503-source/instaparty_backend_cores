<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\DTOs;

final class PolicyDecisionDto
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $featureKey = null,
        public readonly ?int $currentCount = null,
        public readonly ?int $limit = null,
        public readonly ?string $unblockingPlanCode = null,
        public readonly ?string $currentPlanCode = null,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function deny(
        string $featureKey,
        int $currentCount,
        int $limit,
        string $unblockingPlanCode,
        string $currentPlanCode,
    ): self {
        return new self(
            allowed: false,
            featureKey: $featureKey,
            currentCount: $currentCount,
            limit: $limit,
            unblockingPlanCode: $unblockingPlanCode,
            currentPlanCode: $currentPlanCode,
        );
    }
}
