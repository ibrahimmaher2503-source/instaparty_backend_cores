<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class WalletProjectionResult
{
    public function __construct(
        public int $walletId,
        public int $balanceMinor,
        public int $pendingWithdrawalMinor,
        public ?int $lastLedgerEntryId,
    ) {}
}
