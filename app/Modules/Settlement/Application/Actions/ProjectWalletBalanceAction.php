<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\WalletProjectionResult;
use App\Modules\Settlement\Domain\Contracts\WalletProjector;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;

class ProjectWalletBalanceAction implements WalletProjector
{
    public function __construct(
        private readonly EloquentLedgerRepository $ledgerRepo,
        private readonly EloquentWalletRepository $walletRepo,
    ) {}

    public function project(int $walletId): void
    {
        $result = $this->recompute($walletId);

        $this->walletRepo->applyProjection(
            $walletId,
            $result->balanceMinor,
            $result->pendingWithdrawalMinor,
            $result->lastLedgerEntryId ?? 0,
        );
    }

    public function recompute(int $walletId): WalletProjectionResult
    {
        return $this->ledgerRepo->projectionFromLedger($walletId);
    }
}
