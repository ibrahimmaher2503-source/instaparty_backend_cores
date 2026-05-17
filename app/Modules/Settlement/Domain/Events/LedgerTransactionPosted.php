<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use App\Modules\Settlement\Domain\Enums\TransactionKind;

final readonly class LedgerTransactionPosted
{
    /**
     * @param  int[]  $affectedWalletIds
     */
    public function __construct(
        public int $groupId,
        public string $groupPublicId,
        public TransactionKind $kind,
        public string $correlationId,
        public array $affectedWalletIds,
    ) {}
}
