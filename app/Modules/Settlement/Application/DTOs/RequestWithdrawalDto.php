<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;

final readonly class RequestWithdrawalDto
{
    public function __construct(
        public int $vendorProfileId,
        public int $requestedByUserId,
        public int $amountMinor,
        public string $currency,
        public BankAccountSnapshot $bankAccount,
    ) {}
}
