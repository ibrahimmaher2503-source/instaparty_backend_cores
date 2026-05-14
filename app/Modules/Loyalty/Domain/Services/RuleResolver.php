<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Services;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\RuleKind;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;

class RuleResolver
{
    public function __construct(
        private readonly LoyaltyLedgerRepository $ledger,
    ) {}

    /**
     * Pick the first applicable, currently-valid, active rule for this booking item.
     * Returns null if no rule applies (the program's base earn rate is then used).
     */
    public function resolveForBookingItem(LoyaltyProgram $program, int $bookingItemId, int $userId): ?LoyaltyRule
    {
        // Use eager-loaded activeRules when available; otherwise hit the DB.
        $rules = $program->relationLoaded('activeRules')
            ? $program->getRelation('activeRules')
            : $program->activeRules()->get();

        foreach ($rules as $rule) {
            if (! $this->isWithinWindow($rule)) {
                continue;
            }

            if ($this->matches($rule, $program, $bookingItemId, $userId)) {
                return $rule;
            }
        }

        return null;
    }

    private function isWithinWindow(LoyaltyRule $rule): bool
    {
        $now = now();

        if ($rule->starts_at !== null && $rule->starts_at->greaterThan($now)) {
            return false;
        }

        if ($rule->ends_at !== null && $rule->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }

    private function matches(LoyaltyRule $rule, LoyaltyProgram $program, int $bookingItemId, int $userId): bool
    {
        return match ($rule->rule_kind) {
            RuleKind::FirstBooking => $this->isFirstBooking($userId, (int) $program->vendor_profile_id),
            // TODO Phase 2: CategoryBonus requires cross-module reader (booking_items -> services.category_id).
            // TODO Phase 2: ThresholdBonus + Referral kinds.
            RuleKind::CategoryBonus, RuleKind::ThresholdBonus, RuleKind::Referral => false,
        };
    }

    private function isFirstBooking(int $userId, int $vendorProfileId): bool
    {
        return ! $this->ledger->hasPriorEarnFor($userId, $vendorProfileId);
    }

}
