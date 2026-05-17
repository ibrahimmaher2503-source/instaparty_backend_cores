<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Domain\Models\FinancialSnapshot;
use Illuminate\Support\Carbon;

class EloquentSnapshotRepository
{
    public function latestFor(int $walletId): ?FinancialSnapshot
    {
        return FinancialSnapshot::where('wallet_id', $walletId)
            ->orderByDesc('snapshot_at')
            ->first();
    }

    public function historicalAsOf(int $walletId, Carbon $at): ?FinancialSnapshot
    {
        return FinancialSnapshot::where('wallet_id', $walletId)
            ->where('snapshot_at', '<=', $at)
            ->orderByDesc('snapshot_at')
            ->first();
    }
}
