<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Application\DTOs\WalletProjectionResult;

interface WalletProjector
{
    /**
     * Recompute and persist the wallet's projection cache from its ledger entries.
     * Must be called inside an active DB transaction that holds a SELECT FOR UPDATE
     * on the wallet row.
     */
    public function project(int $walletId): void;

    /**
     * Compute the projected balance without persisting it (read-only).
     * Used by reconciliation to detect drift.
     */
    public function recompute(int $walletId): WalletProjectionResult;
}
