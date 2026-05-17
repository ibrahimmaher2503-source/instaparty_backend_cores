<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class LedgerTransactionResult
{
    /**
     * @param  array<int, int>  $newBalances  [walletId => newBalanceMinor]
     */
    public function __construct(
        public int $groupId,
        public string $groupPublicId,
        public bool $wasIdempotentReplay,
        public array $newBalances,
    ) {}
}
