<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class ProgramDraft
{
    public function __construct(
        public array $name,
        public ?array $terms,
        public bool $isActive,
        public float $pointsPerCurrencyUnit,
        public int $pointsValueMinor,
        public string $pointsValueCurrency,
        public int $minPointsToRedeem,
        public int $maxRedeemPct,
        public ?int $pointsExpireAfterDays,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            terms: $data['terms'] ?? null,
            isActive: (bool) ($data['is_active'] ?? false),
            pointsPerCurrencyUnit: (float) ($data['points_per_currency_unit'] ?? 1.0),
            pointsValueMinor: (int) ($data['points_value_minor'] ?? 0),
            pointsValueCurrency: $data['points_value_currency'] ?? 'EGP',
            minPointsToRedeem: (int) ($data['min_points_to_redeem'] ?? 100),
            maxRedeemPct: (int) ($data['max_redeem_pct'] ?? 50),
            pointsExpireAfterDays: isset($data['points_expire_after_days'])
                ? (int) $data['points_expire_after_days']
                : null,
        );
    }
}
