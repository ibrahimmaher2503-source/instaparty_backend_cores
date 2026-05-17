<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Application\DTOs\WalletProjectionResult;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentLedgerRepository
{
    public function findGroupByIdempotencyKey(string $key): ?LedgerTransactionGroup
    {
        return LedgerTransactionGroup::where('idempotency_key', $key)->first();
    }

    /** @return Collection<int, WalletLedgerEntry> */
    public function entriesForWallet(int $walletId): Collection
    {
        return WalletLedgerEntry::where('wallet_id', $walletId)
            ->orderBy('id')
            ->get();
    }

    public function projectionFromLedger(int $walletId): WalletProjectionResult
    {
        $row = DB::table('wallet_ledger')
            ->where('wallet_id', $walletId)
            ->selectRaw('
                SUM(CASE WHEN direction = \'credit\' THEN amount_minor ELSE 0 END) -
                SUM(CASE WHEN direction = \'debit\'  THEN amount_minor ELSE 0 END) AS balance_minor,
                SUM(CASE WHEN entry_type = \'withdrawal_reserve\' THEN amount_minor ELSE 0 END) -
                SUM(CASE WHEN entry_type IN (\'withdrawal_settle\', \'withdrawal_reject_release\') THEN amount_minor ELSE 0 END) AS pending_withdrawal_minor,
                MAX(id) AS last_ledger_entry_id
            ')
            ->first();

        return new WalletProjectionResult(
            walletId: $walletId,
            balanceMinor: (int) ($row->balance_minor ?? 0),
            pendingWithdrawalMinor: max(0, (int) ($row->pending_withdrawal_minor ?? 0)),
            lastLedgerEntryId: $row->last_ledger_entry_id ? (int) $row->last_ledger_entry_id : null,
        );
    }

    /**
     * Return all ledger entries that share the same correlation_id as the given entry.
     * This forms the complete causal chain for any financial event.
     *
     * @return Collection<int, WalletLedgerEntry>
     */
    public function causalChainFor(int $entryId): Collection
    {
        $entry = WalletLedgerEntry::find($entryId);

        if ($entry === null || $entry->correlation_id === null) {
            return collect();
        }

        return WalletLedgerEntry::where('correlation_id', $entry->correlation_id)
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, WalletLedgerEntry> */
    public function causalChainByCorrelationId(string $correlationId): Collection
    {
        return WalletLedgerEntry::where('correlation_id', $correlationId)
            ->orderBy('id')
            ->get();
    }
}
