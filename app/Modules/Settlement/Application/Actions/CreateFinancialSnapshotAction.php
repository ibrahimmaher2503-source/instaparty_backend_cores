<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Domain\Models\FinancialSnapshot;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateFinancialSnapshotAction
{
    public function __construct(
        private readonly EloquentLedgerRepository $ledgerRepo,
    ) {}

    /**
     * Create a financial snapshot for the given wallet anchored to the current
     * ledger high-water mark. Idempotent per (wallet_id, calendar day).
     *
     * Returns null when the wallet has no ledger entries (nothing to snapshot).
     */
    public function execute(int $walletId, ?Carbon $snapshotAt = null): ?FinancialSnapshot
    {
        $snapshotAt ??= now();
        $snapshotDay = $snapshotAt->toDateString();

        return DB::transaction(function () use ($walletId, $snapshotAt, $snapshotDay): ?FinancialSnapshot {
            $existing = FinancialSnapshot::where('wallet_id', $walletId)
                ->whereDate('snapshot_at', $snapshotDay)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $projection = $this->ledgerRepo->projectionFromLedger($walletId);

            if ($projection->lastLedgerEntryId === null) {
                return null;
            }

            $wallet = Wallet::findOrFail($walletId);
            $checksum = $this->computeChecksum($walletId, $projection->lastLedgerEntryId);

            return FinancialSnapshot::create([
                'wallet_id' => $walletId,
                'snapshot_at' => $snapshotAt,
                'as_of_ledger_entry_id' => $projection->lastLedgerEntryId,
                'available_minor' => $projection->balanceMinor,
                'pending_minor' => $projection->pendingWithdrawalMinor,
                'currency' => $wallet->currency,
                'checksum' => $checksum,
            ]);
        });
    }

    /**
     * SHA-256 of the ordered sequence of all ledger entries for this wallet up
     * to (and including) the high-water mark. Format:
     *   "id:direction:amount_minor|id:direction:amount_minor|..."
     */
    private function computeChecksum(int $walletId, int $asOfLedgerEntryId): string
    {
        $entries = DB::table('wallet_ledger')
            ->where('wallet_id', $walletId)
            ->where('id', '<=', $asOfLedgerEntryId)
            ->orderBy('id')
            ->select(['id', 'direction', 'amount_minor'])
            ->get();

        $sequence = $entries->map(
            fn (object $e): string => "{$e->id}:{$e->direction}:{$e->amount_minor}"
        )->implode('|');

        return hash('sha256', $sequence);
    }
}
