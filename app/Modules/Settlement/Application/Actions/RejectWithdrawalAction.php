<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalRejected;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RejectWithdrawalAction
{
    public function __construct(
        private EloquentWalletRepository $walletRepo,
    ) {}

    /**
     * @param  array{en: string, ar: string}  $rejectedReason
     */
    public function execute(Withdrawal $withdrawal, array $rejectedReason, User $admin): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $rejectedReason, $admin) {
            $withdrawal->update([
                'status' => WithdrawalStatus::Rejected,
                'rejected_reason' => $rejectedReason,
                'processed_by_user_id' => $admin->id,
                'processed_at' => now(),
                'pending_lock' => null,
            ]);

            // Release pending_withdrawal_minor
            $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
            $wallet = $this->walletRepo->findByOwner($ownerType, $withdrawal->vendor_profile_id, $withdrawal->requested_amount_currency);
            if ($wallet !== null) {
                $this->walletRepo->decrementPendingWithdrawal($wallet->id, $withdrawal->requested_amount_minor);
            }

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => Withdrawal::class,
                'auditable_id' => $withdrawal->id,
                'user_id' => $admin->id,
                'action' => 'withdrawal_rejected',
                'changes' => json_encode([
                    'before' => ['status' => WithdrawalStatus::Pending->value],
                    'after' => ['status' => WithdrawalStatus::Rejected->value],
                ]),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new WithdrawalRejected(
                withdrawalId: $withdrawal->id,
                withdrawalPublicId: $withdrawal->public_id,
                vendorProfileId: $withdrawal->vendor_profile_id,
            )));

            $withdrawal->refresh();

            return $withdrawal;
        });
    }
}
