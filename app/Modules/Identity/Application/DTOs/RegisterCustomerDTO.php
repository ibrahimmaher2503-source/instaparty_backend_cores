<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

class RegisterCustomerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $phoneE164,
        public readonly ?string $email,
        public readonly string $password,
        public readonly string $preferredLocale,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            phoneE164: $data['phone_e164'],
            email: $data['email'] ?? null,
            password: $data['password'],
            preferredLocale: $data['preferred_locale'] ?? 'ar',
        );
    }
}
