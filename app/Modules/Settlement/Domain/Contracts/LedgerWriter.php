<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;

interface LedgerWriter
{
    /**
     * Post a balanced double-entry transaction group to the ledger.
     *
     * Invariants enforced by the implementation:
     * - All entries must share the same currency.
     * - Sum of debit amounts must equal sum of credit amounts.
     * - The idempotency key (if supplied) must not match a prior group with a different payload.
     * - Wallet locks are acquired in ascending wallet_id order before any write.
     * - The wallet projection cache is updated atomically in the same DB transaction.
     * - Domain events fire AFTER commit via DB::afterCommit().
     */
    public function post(PostLedgerTransactionInput $input): LedgerTransactionResult;
}
