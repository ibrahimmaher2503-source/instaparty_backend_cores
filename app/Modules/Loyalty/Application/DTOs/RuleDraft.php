<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class RuleDraft
{
    public function __construct(
        public array $label,
        public int $earnPointsPerMinor,
        public int $earnMinorPerUnit,
        public int $redemptionRatioPoints,
        public int $redemptionRatioMinor,
        public int $minPointsToRedeem,
        public int $maxRedeemPctBps,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            label: $data['label'],
            earnPointsPerMinor: $data['earn_points_per_minor'],
            earnMinorPerUnit: $data['earn_minor_per_unit'],
            redemptionRatioPoints: $data['redemption_ratio_points'],
            redemptionRatioMinor: $data['redemption_ratio_minor'],
            minPointsToRedeem: $data['min_points_to_redeem'] ?? 0,
            maxRedeemPctBps: $data['max_redeem_pct_bps'] ?? 5000,
        );
    }
}
