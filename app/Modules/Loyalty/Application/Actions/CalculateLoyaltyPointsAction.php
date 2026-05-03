<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Events\LoyaltyPointsEarned;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Illuminate\Support\Facades\DB;

class CalculateLoyaltyPointsAction
{
    public function __construct(
        private readonly BookingItemNetAmountReader $amountReader,
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyRuleRepository $rules,
        private readonly LoyaltyLedgerRepository $ledger,
    ) {}

    public function execute(int $customerId, int $bookingItemId, int $bookingId): ?LoyaltyLedgerEntry
    {
        $vendorProfileId = $this->amountReader->vendorProfileIdFor($bookingItemId);
        $program = $this->programs->findByVendor($vendorProfileId);

        if ($program === null || !$program->status->allowsEarning()) {
            return null;
        }

        $rule = $this->rules->activeRuleFor($program->id);
        if ($rule === null) {
            return null;
        }

        $netMinor = $this->amountReader->netPaidMinorFor($bookingItemId);
        $points = $rule->computeEarnPoints($netMinor);

        if ($points <= 0) {
            return null;
        }

        $productType = $this->amountReader->productTypeFor($bookingItemId);

        return DB::transaction(function () use ($customerId, $vendorProfileId, $program, $rule, $points, $bookingId, $bookingItemId, $productType) {
            $entry = $this->ledger->append(
                type: LedgerEntryType::Earn,
                customerId: $customerId,
                vendorProfileId: $vendorProfileId,
                programId: $program->id,
                points: $points,
                reason: ['en' => __('loyalty::loyalty.reason.earn', [], 'en'), 'ar' => __('loyalty::loyalty.reason.earn', [], 'ar')],
                bookingId: $bookingId,
                bookingItemId: $bookingItemId,
                productType: $productType,
            );

            DB::afterCommit(fn () => event(new LoyaltyPointsEarned($entry)));

            return $entry;
        });
    }
}
