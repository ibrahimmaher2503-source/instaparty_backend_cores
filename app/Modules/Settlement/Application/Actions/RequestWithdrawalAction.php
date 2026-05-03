<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalRequested;
use App\Modules\Settlement\Domain\Exceptions\ExistingPendingWithdrawalException;
use App\Modules\Settlement\Domain\Exceptions\InsufficientWalletBalanceException;
use App\Modules\Settlement\Domain\Exceptions\WithdrawalBelowMinimumException;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RequestWithdrawalAction
{
    private const MINIMUM_MINOR = 10000; // 100 EGP in piastres

    public function __construct(
        private EloquentWalletRepository $walletRepo,
    ) {}

    public function execute(RequestWithdrawalDto $dto): Withdrawal
    {
        return DB::transaction(function () use ($dto) {
            $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
            $wallet = $this->walletRepo->findByOwner($ownerType, $dto->vendorProfileId, $dto->currency);

            if ($dto->amountMinor < self::MINIMUM_MINOR) {
                throw WithdrawalBelowMinimumException::make($dto->amountMinor, self::MINIMUM_MINOR, $dto->currency);
            }

            [$balanceMinor, $pendingMinor] = $wallet instanceof Wallet
                ? [$wallet->balance_minor, $wallet->pending_withdrawal_minor]
                : [0, 0];
            $availableMinor = (int) max(0, $balanceMinor - $pendingMinor);

            if ($balanceMinor < 0) {
                throw new \RuntimeException(__('settlement::settlement.errors.negative_balance_blocked'));
            }

            if ($dto->amountMinor > $availableMinor) {
                throw InsufficientWalletBalanceException::make($availableMinor, $dto->amountMinor, $dto->currency);
            }

            try {
                /** @var Withdrawal $withdrawal */
                $withdrawal = Withdrawal::create([
                    'vendor_profile_id' => $dto->vendorProfileId,
                    'requested_amount_minor' => $dto->amountMinor,
                    'requested_amount_currency' => $dto->currency,
                    'bank_account_snapshot' => $dto->bankAccount,
                    'status' => WithdrawalStatus::Pending,
                    'requested_by_user_id' => $dto->requestedByUserId,
                    'requested_at' => now(),
                    'pending_lock' => $dto->vendorProfileId,
                ]);
            } catch (QueryException $e) {
                // UNIQUE violation on pending_lock — vendor already has a pending withdrawal
                if ($e->getCode() === '23000') {
                    $existing = Withdrawal::where('vendor_profile_id', $dto->vendorProfileId)
                        ->where('status', WithdrawalStatus::Pending)
                        ->first();

                    throw ExistingPendingWithdrawalException::make($existing !== null ? $existing->public_id : 'unknown');
                }

                throw $e;
            }

            if ($wallet !== null) {
                $this->walletRepo->incrementPendingWithdrawal($wallet->id, $dto->amountMinor);
            }

            DB::afterCommit(fn () => event(new WithdrawalRequested(
                withdrawalId: $withdrawal->id,
                withdrawalPublicId: $withdrawal->public_id,
                vendorProfileId: $dto->vendorProfileId,
                amountMinor: $dto->amountMinor,
                currency: $dto->currency,
            )));

            return $withdrawal;
        });
    }
}
