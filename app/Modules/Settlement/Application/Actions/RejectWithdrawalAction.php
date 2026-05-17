<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalRejected;
use App\Modules\Settlement\Domain\Exceptions\LockAcquisitionTimeoutException;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RejectWithdrawalAction
{
    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
        private readonly LedgerWriter $ledgerWriter,
        private readonly WalletLocker $walletLocker,
    ) {}

    /**
     * @param  array{en: string, ar: string}  $rejectedReason
     */
    public function execute(Withdrawal $withdrawal, array $rejectedReason, User $admin): Withdrawal
    {
        $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
        $wallet    = $this->walletRepo->findByOwner($ownerType, $withdrawal->vendor_profile_id, $withdrawal->requested_amount_currency);

        if ($wallet !== null) {
            $lock = $this->walletLocker->tryAcquire($wallet->id, ttlSeconds: 30, waitSeconds: 5);
            if ($lock === null) {
                throw new LockAcquisitionTimeoutException("Could not acquire lock on wallet {$wallet->id}.");
            }
        } else {
            $lock = null;
        }

        try {
            return DB::transaction(function () use ($withdrawal, $rejectedReason, $admin, $wallet, $ownerType): Withdrawal {
                $withdrawal->update([
                    'status'               => WithdrawalStatus::Rejected,
                    'rejected_reason'      => $rejectedReason,
                    'processed_by_user_id' => $admin->id,
                    'processed_at'         => now(),
                    'pending_lock'         => null,
                ]);

                if ($wallet !== null) {
                    $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

                    // Post a withdrawal_reject_release group:
                    // debit platform_withdrawal_payable → credit vendor wallet
                    $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                        kind: TransactionKind::WithdrawalRejectRelease,
                        currency: $withdrawal->requested_amount_currency,
                        idempotencyKey: "wd_reject:{$withdrawal->id}",
                        correlationId: $correlationId,
                        causationId: null,
                        initiatorType: 'admin',
                        initiatedByUserId: $admin->id,
                        entries: [
                            new LedgerEntryInput(
                                walletOwnerType: 'platform_account',
                                walletOwnerId: SuspenseAccount::PlatformWithdrawalPayable->value,
                                direction: LedgerDirection::Debit,
                                amountMinor: (int) $withdrawal->requested_amount_minor,
                                entryType: LedgerEntryType::WithdrawalRejectRelease,
                                counterAccountType: $ownerType,
                                counterAccountId: $withdrawal->vendor_profile_id,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                            new LedgerEntryInput(
                                walletOwnerType: $ownerType,
                                walletOwnerId: $withdrawal->vendor_profile_id,
                                direction: LedgerDirection::Credit,
                                amountMinor: (int) $withdrawal->requested_amount_minor,
                                entryType: LedgerEntryType::WithdrawalRejectRelease,
                                counterAccountType: 'platform_account',
                                counterAccountId: SuspenseAccount::PlatformWithdrawalPayable->value,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                        ],
                        descriptionKey: 'settlement.ledger.withdrawal_reject_release',
                        descriptionParams: ['withdrawal_id' => $withdrawal->public_id],
                    ));

                    $releaseEntryId = DB::table('wallet_ledger')
                        ->where('transaction_group_id', $result->groupId)
                        ->where('wallet_id', $wallet->id)
                        ->value('id');

                    $withdrawal->update(['rejected_ledger_entry_id' => $releaseEntryId]);
                }

                DB::table('audit_logs')->insert([
                    'public_id'      => (string) Str::ulid(),
                    'auditable_type' => Withdrawal::class,
                    'auditable_id'   => $withdrawal->id,
                    'user_id'        => $admin->id,
                    'action'         => 'withdrawal_rejected',
                    'changes'        => json_encode([
                        'before' => ['status' => WithdrawalStatus::Pending->value],
                        'after'  => ['status' => WithdrawalStatus::Rejected->value],
                    ]),
                    'created_at'     => now(),
                ]);

                DB::afterCommit(fn () => event(new WithdrawalRejected(
                    withdrawalId: $withdrawal->id,
                    withdrawalPublicId: $withdrawal->public_id,
                    vendorProfileId: $withdrawal->vendor_profile_id,
                )));

                $withdrawal->refresh();

                return $withdrawal;
            });
        } finally {
            $lock?->release();
        }
    }
}
