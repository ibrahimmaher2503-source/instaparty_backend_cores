<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalPaid;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApproveAndMarkWithdrawalPaidAction
{
    public function __construct(
        private EloquentWalletRepository $walletRepo,
        private DebitWalletAction $debitWallet,
    ) {}

    public function execute(Withdrawal $withdrawal, UploadedFile $proof, User $admin): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $proof, $admin) {
            // Upload bank proof via Spatie Media Library
            $withdrawal->addMedia($proof)
                ->toMediaCollection('bank_proof');

            $media = $withdrawal->getFirstMedia('bank_proof');

            $withdrawal->update([
                'status' => WithdrawalStatus::Paid,
                'paid_amount_minor' => $withdrawal->requested_amount_minor,
                'paid_amount_currency' => $withdrawal->requested_amount_currency,
                'bank_proof_media_id' => $media?->id,
                'processed_by_user_id' => $admin->id,
                'processed_at' => now(),
                'paid_at' => now(),
                'pending_lock' => null,
            ]);

            // Debit vendor wallet
            $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
            $this->debitWallet->execute(
                ownerType: $ownerType,
                ownerId: $withdrawal->vendor_profile_id,
                amountMinor: $withdrawal->requested_amount_minor,
                currency: $withdrawal->requested_amount_currency,
                entryType: LedgerEntryType::WithdrawalDebit,
                relatedEntityType: 'withdrawal',
                relatedEntityId: $withdrawal->id,
                descriptionKey: 'settlement::settlement.ledger.withdrawal_debit',
                descriptionParams: ['iban_last3' => substr((string) $withdrawal->bank_account_snapshot?->iban, -3)],
            );

            // Decrement pending_withdrawal_minor on wallet
            $wallet = $this->walletRepo->findByOwner($ownerType, $withdrawal->vendor_profile_id, $withdrawal->requested_amount_currency);
            if ($wallet !== null) {
                $this->walletRepo->decrementPendingWithdrawal($wallet->id, $withdrawal->requested_amount_minor);
            }

            // Append audit log (schema: auditable_type, auditable_id, user_id, action, changes, created_at)
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => Withdrawal::class,
                'auditable_id' => $withdrawal->id,
                'user_id' => $admin->id,
                'action' => 'withdrawal_paid',
                'changes' => json_encode([
                    'before' => ['status' => WithdrawalStatus::Pending->value],
                    'after' => ['status' => WithdrawalStatus::Paid->value],
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
    }
}
