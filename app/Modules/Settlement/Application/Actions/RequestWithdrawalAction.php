<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalRequested;
use App\Modules\Settlement\Domain\Exceptions\ExistingPendingWithdrawalException;
use App\Modules\Settlement\Domain\Exceptions\InsufficientAvailableBalanceException;
use App\Modules\Settlement\Domain\Exceptions\LockAcquisitionTimeoutException;
use App\Modules\Settlement\Domain\Exceptions\WithdrawalBelowMinimumException;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestWithdrawalAction
{
    private const MINIMUM_MINOR = 10000; // 100 EGP in piastres

    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
        private readonly LedgerWriter $ledgerWriter,
        private readonly WalletLocker $walletLocker,
    ) {}

    public function execute(RequestWithdrawalDto $dto): Withdrawal
    {
        $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
        $wallet    = $this->walletRepo->findByOwner($ownerType, $dto->vendorProfileId, $dto->currency);

        if ($dto->amountMinor < self::MINIMUM_MINOR) {
            throw WithdrawalBelowMinimumException::make($dto->amountMinor, self::MINIMUM_MINOR, $dto->currency);
        }

        // Acquire Redis lock before touching the DB row
        if ($wallet !== null) {
            $lock = $this->walletLocker->tryAcquire($wallet->id, ttlSeconds: 30, waitSeconds: 5);
            if ($lock === null) {
                throw new LockAcquisitionTimeoutException("Could not acquire lock on wallet {$wallet->id}.");
            }
        } else {
            $lock = null;
        }

        try {
            return DB::transaction(function () use ($dto, $wallet, $ownerType): Withdrawal {
                // Row lock to prevent concurrent over-draw
                if ($wallet !== null) {
                    $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
                }

                [$balanceMinor, $pendingMinor] = $wallet instanceof Wallet
                    ? [$wallet->balance_minor, $wallet->pending_withdrawal_minor]
                    : [0, 0];
                $availableMinor = (int) max(0, $balanceMinor - $pendingMinor);

                if ($balanceMinor < 0) {
                    throw new \RuntimeException(__('settlement::settlement.errors.negative_balance_blocked'));
                }

                if ($dto->amountMinor > $availableMinor) {
                    throw InsufficientAvailableBalanceException::make($availableMinor, $dto->amountMinor, $dto->currency);
                }

                try {
                    /** @var Withdrawal $withdrawal */
                    $withdrawal = Withdrawal::create([
                        'vendor_profile_id'           => $dto->vendorProfileId,
                        'requested_amount_minor'       => $dto->amountMinor,
                        'requested_amount_currency'    => $dto->currency,
                        'bank_account_snapshot'        => $dto->bankAccount,
                        'status'                       => WithdrawalStatus::Pending,
                        'requested_by_user_id'         => $dto->requestedByUserId,
                        'requested_at'                 => now(),
                        'pending_lock'                 => $dto->vendorProfileId,
                        'idempotency_key'              => $dto->idempotencyKey ?? "wd_req:{$dto->vendorProfileId}:{$dto->amountMinor}:" . Str::ulid(),
                    ]);
                } catch (QueryException $e) {
                    if ($e->getCode() === '23000') {
                        $existing = Withdrawal::where('vendor_profile_id', $dto->vendorProfileId)
                            ->where('status', WithdrawalStatus::Pending)
                            ->first();

                        throw ExistingPendingWithdrawalException::make($existing !== null ? $existing->public_id : 'unknown');
                    }

                    throw $e;
                }

                $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

                if ($wallet !== null) {
                    // Post a withdrawal_reserve group:
                    // debit vendor wallet → credit platform_withdrawal_payable
                    $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                        kind: TransactionKind::WithdrawalReserve,
                        currency: $dto->currency,
                        idempotencyKey: "wd_reserve:{$withdrawal->id}",
                        correlationId: $correlationId,
                        causationId: null,
                        initiatorType: 'vendor',
                        initiatedByUserId: $dto->requestedByUserId,
                        entries: [
                            new LedgerEntryInput(
                                walletOwnerType: $ownerType,
                                walletOwnerId: $dto->vendorProfileId,
                                direction: LedgerDirection::Debit,
                                amountMinor: $dto->amountMinor,
                                entryType: LedgerEntryType::WithdrawalReserve,
                                counterAccountType: 'platform_account',
                                counterAccountId: SuspenseAccount::PlatformWithdrawalPayable->value,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                            new LedgerEntryInput(
                                walletOwnerType: 'platform_account',
                                walletOwnerId: SuspenseAccount::PlatformWithdrawalPayable->value,
                                direction: LedgerDirection::Credit,
                                amountMinor: $dto->amountMinor,
                                entryType: LedgerEntryType::WithdrawalReserve,
                                counterAccountType: $ownerType,
                                counterAccountId: $dto->vendorProfileId,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                        ],
                        descriptionKey: 'settlement.ledger.withdrawal_reserve',
                        descriptionParams: ['withdrawal_id' => $withdrawal->public_id],
                    ));

                    // Link the reserve entry to the withdrawal row
                    // The debit entry on the vendor wallet is the reserve entry
                    $reserveEntryId = DB::table('wallet_ledger')
                        ->where('transaction_group_id', $result->groupId)
                        ->where('wallet_id', $wallet->id)
                        ->value('id');

                    $withdrawal->update(['reserved_ledger_entry_id' => $reserveEntryId]);
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
        } finally {
            $lock?->release();
        }
    }
}
