<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\WalletLocker;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalPaid;
use App\Modules\Settlement\Domain\Exceptions\DuplicateBankTransferReferenceException;
use App\Modules\Settlement\Domain\Exceptions\InvalidWithdrawalTransitionException;
use App\Modules\Settlement\Domain\Exceptions\LockAcquisitionTimeoutException;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PaidState;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarkWithdrawalPaidAction
{
    public function __construct(
        private readonly EloquentWalletRepository $walletRepo,
        private readonly LedgerWriter $ledgerWriter,
        private readonly WalletLocker $walletLocker,
    ) {}

    public function execute(Withdrawal $withdrawal, MarkWithdrawalPaidInput $input, User $admin): Withdrawal
    {
        $input->assertValid();

        if (! ($withdrawal->status instanceof ApprovedState)) {
            throw new InvalidWithdrawalTransitionException(
                (string) class_basename($withdrawal->status::class),
                'Paid'
            );
        }

        $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
        $wallet    = $this->walletRepo->findByOwner($ownerType, $withdrawal->vendor_profile_id, $withdrawal->requested_amount_currency);

        if ($wallet !== null) {
            $lock = $this->walletLocker->tryAcquire($wallet->id, ttlSeconds: 60, waitSeconds: 10);
            if ($lock === null) {
                throw new LockAcquisitionTimeoutException("Could not acquire lock on wallet {$wallet->id}.");
            }
        } else {
            $lock = null;
        }

        try {
            return DB::transaction(function () use ($withdrawal, $input, $admin, $wallet, $ownerType): Withdrawal {
                // Preemptively enforce uniqueness (catches before DB UNIQUE fires with a friendlier message)
                $referenceExists = DB::table('withdrawals')
                    ->where('vendor_profile_id', $withdrawal->vendor_profile_id)
                    ->where('bank_transfer_reference', $input->bankTransferReference)
                    ->where('id', '!=', $withdrawal->id)
                    ->exists();

                if ($referenceExists) {
                    throw new DuplicateBankTransferReferenceException(
                        $withdrawal->vendor_profile_id,
                        $input->bankTransferReference
                    );
                }

                $withdrawal->addMedia($input->proofFile)->toMediaCollection('bank_proof');
                $media = $withdrawal->getFirstMedia('bank_proof');

                $withdrawal->update([
                    'status'                 => PaidState::class,
                    'paid_amount_minor'      => $withdrawal->requested_amount_minor,
                    'paid_amount_currency'   => $withdrawal->requested_amount_currency,
                    'bank_proof_media_id'    => $media?->id,
                    'paid_by_admin_id'       => $admin->id,
                    'bank_transfer_reference' => $input->bankTransferReference,
                    'admin_payment_note'     => ! empty($input->paymentNote) ? $input->paymentNote : null,
                    // Legacy columns (maintained for backward compat until Phase 8)
                    'processed_by_user_id'  => $admin->id,
                    'processed_at'          => now(),
                    'paid_at'               => now(),
                ]);

                if ($wallet !== null) {
                    $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

                    $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                        kind: TransactionKind::WithdrawalSettle,
                        currency: $withdrawal->requested_amount_currency,
                        idempotencyKey: "wd_settle:{$withdrawal->id}",
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
                                entryType: LedgerEntryType::WithdrawalSettle,
                                counterAccountType: 'platform_account',
                                counterAccountId: SuspenseAccount::GatewayInTransit->value,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                            new LedgerEntryInput(
                                walletOwnerType: 'platform_account',
                                walletOwnerId: SuspenseAccount::GatewayInTransit->value,
                                direction: LedgerDirection::Credit,
                                amountMinor: (int) $withdrawal->requested_amount_minor,
                                entryType: LedgerEntryType::WithdrawalSettle,
                                counterAccountType: 'platform_account',
                                counterAccountId: SuspenseAccount::PlatformWithdrawalPayable->value,
                                relatedEntityType: 'withdrawal',
                                relatedEntityId: $withdrawal->id,
                            ),
                        ],
                        descriptionKey: 'settlement.ledger.withdrawal_settle',
                        descriptionParams: ['withdrawal_id' => $withdrawal->public_id],
                    ));

                    $settleEntryId = DB::table('wallet_ledger')
                        ->where('transaction_group_id', $result->groupId)
                        ->where('wallet_id', function ($q) use ($ownerType, $withdrawal): void {
                            $q->select('id')->from('wallets')
                                ->where('owner_type', $ownerType)
                                ->where('owner_id', $withdrawal->vendor_profile_id)
                                ->limit(1);
                        })
                        ->value('id');

                    $settleEntryId ??= DB::table('wallet_ledger')
                        ->where('transaction_group_id', $result->groupId)
                        ->value('id');

                    $withdrawal->update(['settled_ledger_entry_id' => $settleEntryId]);
                }

                DB::table('audit_logs')->insert([
                    'public_id'      => (string) Str::ulid(),
                    'auditable_type' => Withdrawal::class,
                    'auditable_id'   => $withdrawal->id,
                    'user_id'        => $admin->id,
                    'action'         => 'withdrawal_paid',
                    'changes'        => json_encode([
                        'before' => ['status' => WithdrawalStatus::Approved->value],
                        'after'  => ['status' => WithdrawalStatus::Paid->value],
                    ]),
                    'created_at' => now(),
                ]);

                DB::afterCommit(fn () => event(new WithdrawalPaid(
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
