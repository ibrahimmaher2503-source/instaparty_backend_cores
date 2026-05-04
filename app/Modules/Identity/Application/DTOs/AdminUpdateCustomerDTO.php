<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

final class AdminUpdateCustomerDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phoneE164 = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = ['name' => $this->name];

        if ($this->phoneE164 !== null) {
            $data['phone_e164'] = $this->phoneE164;
        }

        return $data;
    }
}
