<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentWalletRepository
{
    public function firstOrCreate(string $ownerType, int $ownerId, string $currency = 'EGP'): Wallet
    {
        /** @var Wallet $wallet */
        $wallet = Wallet::firstOrCreate(
            [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
                'currency'   => $currency,
            ],
            [
                'public_id'                => (string) Str::ulid(),
                'balance_minor'            => 0,
                'pending_withdrawal_minor' => 0,
            ]
        );

        return $wallet;
    }

    public function findByOwner(string $ownerType, int $ownerId, string $currency = 'EGP'): ?Wallet
    {
        return Wallet::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->first();
    }

    /** Find any wallet for this owner, ignoring currency — used by the ledger resolver so assertCurrencyMatch can catch mismatches. */
    public function findByOwnerAny(string $ownerType, int|string $ownerId): ?Wallet
    {
        return Wallet::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->first();
    }

    /**
     * @ledger-projection-write — the ONLY permitted path that writes balance columns.
     * Called exclusively by ProjectWalletBalanceAction after a successful ledger post.
     */
    public function applyProjection(
        int $walletId,
        int $newBalanceMinor,
        int $newPendingWithdrawalMinor,
        int $lastLedgerEntryId,
    ): void {
        Wallet::where('id', $walletId)->update([
            'balance_minor'            => $newBalanceMinor,
            'pending_withdrawal_minor' => $newPendingWithdrawalMinor,
            'last_ledger_entry_id'     => $lastLedgerEntryId,
            'last_projected_at'        => now(),
        ]);
    }

    /**
     * @deprecated since Phase 4.9 — balances are derived from wallet_ledger.
     * Will be removed in the cleanup stage (Stage 3 rollout).
     * Use PostLedgerTransactionAction + ProjectWalletBalanceAction instead.
     *
     * Stage 2 (FEATURE_LEDGER_HARDENING_V2=true): throws BadMethodCallException.
     * Stage 1 (false):                             falls back to direct DB write for backward compat.
     */
    public function incrementBalance(int $walletId, int $amountMinor): void
    {
        if (config('feature_flags.financial_ledger_hardening_v2')) {
            throw new \BadMethodCallException(
                'Direct balance mutation is forbidden. Post a ledger entry via PostLedgerTransactionAction.'
            );
        }

        DB::table('wallets')->where('id', $walletId)->increment('balance_minor', $amountMinor);
    }

    /**
     * @deprecated since Phase 4.9 — balances are derived from wallet_ledger.
     * Will be removed in the cleanup stage (Stage 3 rollout).
     * Use PostLedgerTransactionAction + ProjectWalletBalanceAction instead.
     */
    public function decrementBalance(int $walletId, int $amountMinor): void
    {
        if (config('feature_flags.financial_ledger_hardening_v2')) {
            throw new \BadMethodCallException(
                'Direct balance mutation is forbidden. Post a ledger entry via PostLedgerTransactionAction.'
            );
        }

        DB::table('wallets')->where('id', $walletId)->decrement('balance_minor', $amountMinor);
    }

    /**
     * @deprecated since Phase 4.9 — pending withdrawal is derived from wallet_ledger.
     * Will be removed in the cleanup stage (Stage 3 rollout).
     * Use PostLedgerTransactionAction + ProjectWalletBalanceAction instead.
     */
    public function incrementPendingWithdrawal(int $walletId, int $amountMinor): void
    {
        if (config('feature_flags.financial_ledger_hardening_v2')) {
            throw new \BadMethodCallException(
                'Direct balance mutation is forbidden. Post a ledger entry via PostLedgerTransactionAction.'
            );
        }

        DB::table('wallets')->where('id', $walletId)->increment('pending_withdrawal_minor', $amountMinor);
    }

    /**
     * @deprecated since Phase 4.9 — pending withdrawal is derived from wallet_ledger.
     * Will be removed in the cleanup stage (Stage 3 rollout).
     * Use PostLedgerTransactionAction + ProjectWalletBalanceAction instead.
     */
    public function decrementPendingWithdrawal(int $walletId, int $amountMinor): void
    {
        if (config('feature_flags.financial_ledger_hardening_v2')) {
            throw new \BadMethodCallException(
                'Direct balance mutation is forbidden. Post a ledger entry via PostLedgerTransactionAction.'
            );
        }

        DB::table('wallets')->where('id', $walletId)->decrement('pending_withdrawal_minor', $amountMinor);
    }
}
