<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class ProgramDraft
{
    public function __construct(
        public array $name,
        public ?array $terms,
        public string $currency,
        public ?int $expirationDays,
        public string $status,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            terms: $data['terms'] ?? null,
            currency: $data['currency'] ?? 'EGP',
            expirationDays: $data['expiration_days'] ?? null,
            status: $data['status'] ?? 'active',
        );
    }
}
