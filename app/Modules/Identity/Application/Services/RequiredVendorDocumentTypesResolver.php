<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\BusinessType;

class RequiredVendorDocumentTypesResolver
{
    /** @return array<int, string> */
    public function forBusinessType(string $businessType): array
    {
        return match (BusinessType::tryFrom($businessType) ?? BusinessType::Individual) {
            BusinessType::Individual    => ['national_id', 'iban_proof'],
            BusinessType::Company       => ['cr', 'tax_card', 'iban_proof'],
            BusinessType::Establishment => ['cr', 'tax_card', 'national_id', 'iban_proof'],
        };
    }
}
