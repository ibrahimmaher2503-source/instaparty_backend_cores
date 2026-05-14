<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class RuleDraft
{
    public function __construct(
        public array $label,
        public string $ruleKind,
        public float $multiplier,
        public ?array $conditions,
        public bool $isActive,
        public ?string $startsAt,
        public ?string $endsAt,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            label: $data['label'],
            ruleKind: $data['rule_kind'],
            multiplier: (float) ($data['multiplier'] ?? 1.0),
            conditions: $data['conditions'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            startsAt: $data['starts_at'] ?? null,
            endsAt: $data['ends_at'] ?? null,
        );
    }
}
