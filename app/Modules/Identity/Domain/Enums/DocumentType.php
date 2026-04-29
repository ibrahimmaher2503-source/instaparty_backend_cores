<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

enum DocumentType: string
{
    case Cr = 'cr';
    case TaxCard = 'tax_card';
    case NationalId = 'national_id';
    case IbanProof = 'iban_proof';
    case Other = 'other';
}
