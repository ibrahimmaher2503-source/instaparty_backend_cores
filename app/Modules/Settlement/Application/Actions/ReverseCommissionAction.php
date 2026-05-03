<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Events\CommissionReversed;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReverseCommissionAction
{
    public function __construct(
        private EloquentWalletRepository $walletRepo,
        private DebitWalletAction $debitWallet,
    ) {}

    public function execute(Commission $commission, RefundSnapshotDto $refund): Commission
    {
        return DB::transaction(function () use ($commission, $refund) {
            // Calculate proportional reversal using Brick\Math with HALF_EVEN
            $proportionDecimal = BigDecimal::of($refund->amountMinor)
                ->dividedBy($commission->gross_amount_minor, 10, RoundingMode::HALF_EVEN);

            $reversalMinor = (int) BigDecimal::of($commission->vendor_share_minor)
                ->multipliedBy($proportionDecimal)
                ->toScale(0, RoundingMode::HALF_EVEN)
                ->toInt();

            $newReversed = $commission->reversed_amount_minor + $reversalMinor;
            $isFullReversal = $newReversed >= $commission->vendor_share_minor;

            $commission->update([
                'reversed_amount_minor' => $newReversed,
                'status' => $isFullReversal
                    ? CommissionStatus::Reversed
                    : CommissionStatus::PartiallyReversed,
            ]);

            // Debit vendor wallet for the reversal amount
            $ownerType = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';
            $this->debitWallet->execute(
                ownerType: $ownerType,
                ownerId: $commission->vendor_profile_id,
                amountMinor: $reversalMinor,
                currency: $commission->vendor_share_currency,
                entryType: LedgerEntryType::RefundDebit,
                relatedEntityType: 'refund',
                relatedEntityId: $refund->id,
                descriptionKey: 'settlement::settlement.ledger.refund_debit',
                descriptionParams: ['booking_item_id' => $commission->booking_item_id],
            );

            // Warn on negative balance
            $wallet = $this->walletRepo->findByOwner($ownerType, $commission->vendor_profile_id, $commission->vendor_share_currency);
            if ($wallet !== null) {
                $wallet->refresh();
                if ($wallet->balance_minor < 0) {
                    Log::warning('Settlement: negative balance after refund reversal', [
                        'vendor_profile_id' => $commission->vendor_profile_id,
                        'wallet_id' => $wallet->id,
                    ]);

                    DB::table('audit_logs')->insert([
                        'public_id' => (string) Str::ulid(),
                        'auditable_type' => Commission::class,
                        'auditable_id' => $commission->id,
                        'user_id' => null,
                        'action' => 'negative_balance_warning',
                        'changes' => json_encode([
                            'before' => [],
                            'after' => ['balance_minor' => $wallet->balance_minor],
                        ]),
                        'created_at' => now(),
                    ]);
                }
            }

            DB::afterCommit(fn () => event(new CommissionReversed(
                commissionId: $commission->id,
                reversedAmountMinor: $reversalMinor,
                currency: $commission->vendor_share_currency,
            )));

            $commission->refresh();

            return $commission;
        });
    }
}
