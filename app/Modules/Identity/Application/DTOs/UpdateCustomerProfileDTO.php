<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

class UpdateCustomerProfileDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $preferredLocale = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $gender = null,
        public readonly ?bool $acceptsMarketing = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            preferredLocale: $data['preferred_locale'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            gender: $data['gender'] ?? null,
            acceptsMarketing: array_key_exists('accepts_marketing', $data) ? (bool) $data['accepts_marketing'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function userAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'preferred_locale' => $this->preferredLocale,
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    public function profileAttributes(): array
    {
        $out = [];
        if ($this->dateOfBirth !== null) {
            $out['date_of_birth'] = $this->dateOfBirth;
        }
        if ($this->gender !== null) {
            $out['gender'] = $this->gender;
        }
        if ($this->acceptsMarketing !== null) {
            $out['accepts_marketing'] = $this->acceptsMarketing;
        }

        return $out;
    }
}
