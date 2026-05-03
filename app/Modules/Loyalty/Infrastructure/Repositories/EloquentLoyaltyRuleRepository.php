<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Illuminate\Support\Facades\DB;

class EloquentLoyaltyRuleRepository implements LoyaltyRuleRepository
{
    public function activeRuleFor(int $programId): ?LoyaltyRule
    {
        return LoyaltyRule::where('loyalty_program_id', $programId)
            ->where('is_active', true)
            ->latest('effective_from')
            ->first();
    }

    public function replaceActive(int $programId, RuleDraft $draft): LoyaltyRule
    {
        return DB::transaction(function () use ($programId, $draft) {
            // Deactivate all current active rules for this program
            LoyaltyRule::where('loyalty_program_id', $programId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return LoyaltyRule::create([
                'loyalty_program_id'      => $programId,
                'label'                   => $draft->label,
                'earn_points_per_minor'   => $draft->earnPointsPerMinor,
                'earn_minor_per_unit'     => $draft->earnMinorPerUnit,
                'redemption_ratio_points' => $draft->redemptionRatioPoints,
                'redemption_ratio_minor'  => $draft->redemptionRatioMinor,
                'min_points_to_redeem'    => $draft->minPointsToRedeem,
                'max_redeem_pct_bps'      => $draft->maxRedeemPctBps,
                'is_active'               => true,
            ]);
        });
    }
}
