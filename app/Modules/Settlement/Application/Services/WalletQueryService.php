<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Services;

use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class WalletQueryService
{
    public function __construct(
        private EloquentWalletRepository $walletRepo,
    ) {}

    /**
     * @return array{
     *     public_id: string|null,
     *     currency: string,
     *     balance_minor: int,
     *     pending_withdrawal_minor: int,
     *     available_minor: int,
     *     is_negative: bool,
     *     totals: array{credits_minor: int, debits_minor: int}
     * }
     */
    public function balance(int $vendorProfileId, string $currency = 'EGP'): array
    {
        $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
        $wallet = $this->walletRepo->findByOwner($ownerType, $vendorProfileId, $currency);

        [$balanceMinor, $pendingMinor, $walletPublicId] = $wallet instanceof Wallet
            ? [$wallet->balance_minor, $wallet->pending_withdrawal_minor, $wallet->public_id]
            : [0, 0, null];

        $availableMinor = (int) max(0, $balanceMinor - $pendingMinor);

        $credits = 0;
        $debits = 0;

        if ($wallet !== null) {
            $credits = (int) DB::table('wallet_ledger')
                ->where('wallet_id', $wallet->id)
                ->where('amount_minor', '>', 0)
                ->sum('amount_minor');

            $rawDebits = (int) DB::table('wallet_ledger')
                ->where('wallet_id', $wallet->id)
                ->where('amount_minor', '<', 0)
                ->sum('amount_minor');
            $debits = abs($rawDebits);
        }

        return [
            'public_id' => $walletPublicId,
            'currency' => $currency,
            'balance_minor' => $balanceMinor,
            'pending_withdrawal_minor' => $pendingMinor,
            'available_minor' => $availableMinor,
            'is_negative' => $balanceMinor < 0,
            'totals' => [
                'credits_minor' => $credits,
                'debits_minor' => $debits,
            ],
        ];
    }

    /**
     * @param  array{per_page?: int, entry_type?: string, from?: string, to?: string}  $filters
     * @return CursorPaginator<int, WalletLedgerEntry>
     */
    public function ledger(int $vendorProfileId, string $currency = 'EGP', array $filters = []): CursorPaginator
    {
        $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
        $wallet = $this->walletRepo->findByOwner($ownerType, $vendorProfileId, $currency);

        if ($wallet === null) {
            /** @var CursorPaginator<int, WalletLedgerEntry> */
            return WalletLedgerEntry::whereNull('id')->cursorPaginate($filters['per_page'] ?? 25);
        }

        $query = WalletLedgerEntry::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['entry_type'])) {
            $query->where('entry_type', $filters['entry_type']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->cursorPaginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
