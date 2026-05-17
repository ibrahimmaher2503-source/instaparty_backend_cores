<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Events\WithdrawalApproved;
use App\Modules\Settlement\Domain\Exceptions\InvalidWithdrawalTransitionException;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\ApprovedState;
use App\Modules\Settlement\Domain\States\WithdrawalStatus\PendingState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApproveWithdrawalAction
{
    public function execute(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        // Idempotency: if this withdrawal was already approved within the last 24 h, return early.
        // Checked first so replaying the same request short-circuits before state validation.
        $idempotencyKey = "wd_approve:{$withdrawal->id}";
        $alreadyDone = DB::table('idempotency_keys')
            ->where('key', $idempotencyKey)
            ->where('scope', 'internal')
            ->where('expires_at', '>', now())
            ->exists();

        if ($alreadyDone) {
            return $withdrawal->fresh();
        }

        if (! ($withdrawal->status instanceof PendingState)) {
            throw new InvalidWithdrawalTransitionException(
                (string) class_basename($withdrawal->status::class),
                'Approved'
            );
        }

        return DB::transaction(function () use ($withdrawal, $admin, $idempotencyKey): Withdrawal {
            $withdrawal->update([
                'status'               => ApprovedState::class,
                'approved_at'          => now(),
                'approved_by_admin_id' => $admin->id,
                'pending_lock'         => null,
            ]);

            DB::table('audit_logs')->insert([
                'public_id'      => (string) Str::ulid(),
                'auditable_type' => Withdrawal::class,
                'auditable_id'   => $withdrawal->id,
                'user_id'        => $admin->id,
                'action'         => 'withdrawal_approved',
                'changes'        => json_encode([
                    'before' => ['status' => WithdrawalStatus::Pending->value],
                    'after'  => ['status' => WithdrawalStatus::Approved->value],
                ]),
                'created_at' => now(),
            ]);

            DB::table('idempotency_keys')->insertOrIgnore([[
                'key'             => $idempotencyKey,
                'user_id'         => $admin->id,
                'route'           => "internal_action:{$idempotencyKey}",
                'request_hash'    => hash('sha256', (string) $withdrawal->id),
                'response_status' => 200,
                'response_body'   => null,
                'expires_at'      => now()->addHours(24),
                'created_at'      => now(),
                'scope'           => 'internal',
                'ttl_seconds'     => 86400,
                'payload_hash'    => hash('sha256', (string) $withdrawal->id),
            ]]);

            DB::afterCommit(fn () => event(new WithdrawalApproved(
                withdrawalId: $withdrawal->id,
                withdrawalPublicId: $withdrawal->public_id,
                vendorProfileId: $withdrawal->vendor_profile_id,
                approvedByAdminId: $admin->id,
            )));

            $withdrawal->refresh();

            return $withdrawal;
        });
    }
}
