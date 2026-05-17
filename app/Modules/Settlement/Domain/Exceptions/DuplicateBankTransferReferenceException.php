<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exceptions;

final class DuplicateBankTransferReferenceException extends \DomainException
{
    public function __construct(int $vendorProfileId, string $reference)
    {
        parent::__construct(
            "Bank transfer reference '{$reference}' already exists for vendor profile #{$vendorProfileId}."
        );
    }
}
