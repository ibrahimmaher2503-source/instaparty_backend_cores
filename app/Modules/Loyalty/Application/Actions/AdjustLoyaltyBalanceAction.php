<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

class AdjustLoyaltyBalanceAction
{
    public function __construct(
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    /**
     * Admin force-adjust of a user×vendor balance. $pointsDelta may be negative.
     */
    public function execute(
        int $userId,
        int $vendorProfileId,
        int $pointsDelta,
        string $reasonEn,
        string $reasonAr,
        int $adminUserId,
    ): LoyaltyLedgerEntry {
        if ($pointsDelta === 0) {
            throw new DomainException('loyalty.adjust_delta_zero');
        }

        $program = $this->programs->findByVendor($vendorProfileId);
        if ($program === null) {
            throw new DomainException('loyalty.program_not_found');
        }

        $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);
        $newBalance = $prevBalance + $pointsDelta;
        if ($newBalance < 0) {
            throw new DomainException('loyalty.balance_underflow');
        }

        return DB::transaction(function () use (
            $userId, $vendorProfileId, $program, $pointsDelta, $newBalance, $adminUserId, $reasonEn, $reasonAr
        ) {
            $entry = $this->ledger->append(
                userId: $userId,
                vendorProfileId: $vendorProfileId,
                programId: (int) $program->id,
                direction: LedgerDirection::Adjust,
                points: abs($pointsDelta),
                balanceAfter: $newBalance,
                referenceType: 'admin_adjustment',
                referenceId: $adminUserId,
                reason: [
                    'en' => $reasonEn,
                    'ar' => $reasonAr,
                ],
                expiresAt: null,
            );

            // TODO: write to audit_logs once the Shared audit helper is available.
            // The ledger repo fires LoyaltyLedgerEntryAppended after commit already;
            // no extra event needed here to avoid double-fire.

            return $entry;
        });
    }
}
