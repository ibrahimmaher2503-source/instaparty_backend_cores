<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

class RegisterVendorDTO
{
    /**
     * @param  array{en: string, ar: string}  $businessName
     */
    public function __construct(
        public readonly string $name,
        public readonly string $phoneE164,
        public readonly ?string $email,
        public readonly string $password,
        public readonly array $businessName,
        public readonly string $businessType,
        public readonly int $primaryGovernorateId,
        public readonly int $primaryCityId,
        public readonly string $preferredLocale,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            phoneE164: $data['phone_e164'],
            email: $data['email'] ?? null,
            password: $data['password'],
            businessName: $data['business_name'],
            businessType: $data['business_type'],
            primaryGovernorateId: (int) $data['primary_governorate_id'],
            primaryCityId: (int) $data['primary_city_id'],
            preferredLocale: $data['preferred_locale'] ?? 'ar',
        );
    }
}
