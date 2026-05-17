<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Detectors;

use App\Modules\Settlement\Application\DTOs\DetectedFinding;
use App\Modules\Settlement\Domain\Contracts\ReconciliationDetector;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use Illuminate\Support\Facades\DB;

class EloquentReconciliationDetector implements ReconciliationDetector
{
    public function __construct(
        private readonly EloquentLedgerRepository $ledgerRepo,
    ) {}

    /** @return list<DetectedFinding> */
    public function detectCacheDrift(int $walletId): array
    {
        $wallet = Wallet::find($walletId);
        if ($wallet === null) {
            return [];
        }

        $projected = $this->ledgerRepo->projectionFromLedger($walletId);
        $findings  = [];

        if ($projected->balanceMinor !== (int) $wallet->balance_minor) {
            $findings[] = new DetectedFinding(
                findingType: ReconciliationFindingType::WalletCacheDrift,
                severity: ReconciliationFindingSeverity::Warning,
                resourceType: 'wallet',
                resourceId: $walletId,
                expected: ['balance_minor' => $projected->balanceMinor],
                actual: ['balance_minor' => (int) $wallet->balance_minor],
                delta: ['balance_minor' => $projected->balanceMinor - (int) $wallet->balance_minor],
                descriptionKey: 'settlement.findings.wallet_cache_drift',
            );
        }

        if ($projected->pendingWithdrawalMinor !== (int) $wallet->pending_withdrawal_minor) {
            $findings[] = new DetectedFinding(
                findingType: ReconciliationFindingType::WalletCacheDrift,
                severity: ReconciliationFindingSeverity::Warning,
                resourceType: 'wallet',
                resourceId: $walletId,
                expected: ['pending_withdrawal_minor' => $projected->pendingWithdrawalMinor],
                actual: ['pending_withdrawal_minor' => (int) $wallet->pending_withdrawal_minor],
                delta: ['pending_withdrawal_minor' => $projected->pendingWithdrawalMinor - (int) $wallet->pending_withdrawal_minor],
                descriptionKey: 'settlement.findings.wallet_pending_withdrawal_drift',
            );
        }

        return $findings;
    }

    /** @return list<DetectedFinding> */
    public function detectOrphanedRefunds(int $walletId): array
    {
        // Refunds with status='completed' that have no ledger_group_id.
        // This is a global check — orphaned refunds represent missing ledger coverage anywhere in the system.
        $orphans = DB::table('refunds')
            ->where('status', 'completed')
            ->whereNull('ledger_group_id')
            ->select('id')
            ->get();

        return $orphans->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::OrphanedRefundRow,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'refund',
            resourceId: $row->id,
            expected: ['ledger_group_id' => 'not null'],
            actual: ['ledger_group_id' => null],
            delta: null,
            descriptionKey: 'settlement.findings.orphaned_refund',
        ))->values()->all();
    }

    /** @return list<DetectedFinding> */
    public function detectOrphanedLedgerEntries(int $walletId): array
    {
        $orphans = DB::table('wallet_ledger')
            ->where('wallet_id', $walletId)
            ->whereNull('transaction_group_id')
            ->select('id')
            ->get();

        return $orphans->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::OrphanedLedgerEntry,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'wallet_ledger',
            resourceId: $row->id,
            expected: ['transaction_group_id' => 'not null'],
            actual: ['transaction_group_id' => null],
            delta: null,
            descriptionKey: 'settlement.findings.orphaned_ledger_entry',
        ))->values()->all();
    }

    /** @return list<DetectedFinding> */
    public function detectUnbalancedGroups(int $walletId): array
    {
        // Find groups that touch this wallet, then check their GLOBAL balance (all entries across all wallets).
        // In double-entry accounting each wallet sees only one side; the imbalance check must be group-wide.
        // legacy_backfill groups are intentionally single-sided — skip them.
        $unbalanced = DB::table('ledger_transaction_groups as g')
            ->whereExists(function ($q) use ($walletId): void {
                $q->from('wallet_ledger')
                    ->whereColumn('wallet_ledger.transaction_group_id', 'g.id')
                    ->where('wallet_ledger.wallet_id', $walletId);
            })
            ->where('g.kind', '!=', 'legacy_backfill')
            ->whereRaw('(
                SELECT COALESCE(SUM(CASE WHEN wl2.direction = \'debit\' THEN wl2.amount_minor ELSE 0 END), 0)
                FROM wallet_ledger wl2 WHERE wl2.transaction_group_id = g.id
            ) != (
                SELECT COALESCE(SUM(CASE WHEN wl2.direction = \'credit\' THEN wl2.amount_minor ELSE 0 END), 0)
                FROM wallet_ledger wl2 WHERE wl2.transaction_group_id = g.id
            )')
            ->select('g.id', 'g.public_id')
            ->get();

        return $unbalanced->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::UnbalancedTransactionGroup,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'ledger_transaction_groups',
            resourceId: $row->id,
            expected: ['debits_equal_credits' => true],
            actual: ['debits_equal_credits' => false],
            delta: null,
            descriptionKey: 'settlement.findings.unbalanced_group',
            descriptionParams: ['group_public_id' => $row->public_id],
        ))->values()->all();
    }

    /** @return list<DetectedFinding> */
    public function detectCommissionsWithoutSnapshot(int $walletId): array
    {
        // Commissions linked to payments where the related vendor wallet matches,
        // but no accrual_ledger_entry_id is set
        $missing = DB::table('commissions')
            ->join('wallets', function ($join) use ($walletId): void {
                $join->on('wallets.owner_id', '=', 'commissions.vendor_profile_id')
                    ->where('wallets.id', '=', $walletId);
            })
            ->where('commissions.status', '!=', 'reversed')
            ->whereNull('commissions.accrual_ledger_entry_id')
            ->select('commissions.id')
            ->get();

        return $missing->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::CommissionWithoutSnapshotRate,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'commission',
            resourceId: $row->id,
            expected: ['accrual_ledger_entry_id' => 'not null'],
            actual: ['accrual_ledger_entry_id' => null],
            delta: null,
            descriptionKey: 'settlement.findings.commission_without_snapshot',
        ))->values()->all();
    }

    /** @return list<DetectedFinding> */
    public function detectWithdrawalsWithoutReserve(int $walletId): array
    {
        $missing = DB::table('withdrawals')
            ->join('wallets', function ($join) use ($walletId): void {
                $join->on('wallets.owner_id', '=', 'withdrawals.vendor_profile_id')
                    ->where('wallets.id', '=', $walletId);
            })
            ->whereIn('withdrawals.status', ['approved', 'paid'])
            ->whereNull('withdrawals.reserved_ledger_entry_id')
            ->select('withdrawals.id')
            ->get();

        return $missing->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::WithdrawalWithoutReserveEntry,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'withdrawal',
            resourceId: $row->id,
            expected: ['reserved_ledger_entry_id' => 'not null'],
            actual: ['reserved_ledger_entry_id' => null],
            delta: null,
            descriptionKey: 'settlement.findings.withdrawal_without_reserve',
        ))->values()->all();
    }

    /** @return list<DetectedFinding> */
    public function detectNegativeVendorBalances(int $walletId): array
    {
        $wallet = Wallet::find($walletId);
        if ($wallet === null || (int) $wallet->balance_minor >= 0) {
            return [];
        }

        return [new DetectedFinding(
            findingType: ReconciliationFindingType::NegativeVendorBalance,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'wallet',
            resourceId: $walletId,
            expected: ['balance_minor' => '>= 0'],
            actual: ['balance_minor' => (int) $wallet->balance_minor],
            delta: ['balance_minor' => (int) $wallet->balance_minor],
            descriptionKey: 'settlement.findings.negative_vendor_balance',
        )];
    }

    /** @return list<DetectedFinding> */
    public function detectCurrencyMismatches(int $walletId): array
    {
        $wallet = Wallet::find($walletId);
        if ($wallet === null) {
            return [];
        }

        $mismatches = DB::table('wallet_ledger')
            ->where('wallet_id', $walletId)
            ->where('currency', '!=', $wallet->currency)
            ->select('id', 'currency')
            ->get();

        return $mismatches->map(fn ($row) => new DetectedFinding(
            findingType: ReconciliationFindingType::CurrencyMismatch,
            severity: ReconciliationFindingSeverity::High,
            resourceType: 'wallet_ledger',
            resourceId: $row->id,
            expected: ['currency' => $wallet->currency],
            actual: ['currency' => $row->currency],
            delta: null,
            descriptionKey: 'settlement.findings.currency_mismatch',
        ))->values()->all();
    }
}
