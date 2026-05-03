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
                'owner_id' => $ownerId,
                'currency' => $currency,
            ],
            [
                'public_id' => (string) Str::ulid(),
                'balance_minor' => 0,
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

    public function incrementBalance(int $walletId, int $amountMinor): void
    {
        DB::table('wallets')
            ->where('id', $walletId)
            ->increment('balance_minor', $amountMinor);
    }

    public function decrementBalance(int $walletId, int $amountMinor): void
    {
        DB::table('wallets')
            ->where('id', $walletId)
            ->decrement('balance_minor', $amountMinor);
    }

    public function incrementPendingWithdrawal(int $walletId, int $amountMinor): void
    {
        DB::table('wallets')
            ->where('id', $walletId)
            ->increment('pending_withdrawal_minor', $amountMinor);
    }

    public function decrementPendingWithdrawal(int $walletId, int $amountMinor): void
    {
        DB::table('wallets')
            ->where('id', $walletId)
            ->decrement('pending_withdrawal_minor', $amountMinor);
    }
}
