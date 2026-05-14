<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Illuminate\Support\Collection;

class EloquentLoyaltyRuleRepository implements LoyaltyRuleRepository
{
    public function activeRulesFor(int $programId): Collection
    {
        return LoyaltyRule::query()
            ->where('loyalty_program_id', $programId)
            ->active()
            ->currentlyValid()
            ->orderBy('id')
            ->get();
    }

    public function create(int $programId, RuleDraft $draft): LoyaltyRule
    {
        return LoyaltyRule::create([
            'loyalty_program_id' => $programId,
            'rule_kind' => $draft->ruleKind,
            'multiplier' => $draft->multiplier,
            'conditions' => $draft->conditions,
            'label' => $draft->label,
            'is_active' => $draft->isActive,
            'starts_at' => $draft->startsAt,
            'ends_at' => $draft->endsAt,
        ]);
    }

    public function update(LoyaltyRule $rule, RuleDraft $draft): LoyaltyRule
    {
        $rule->update([
            'rule_kind' => $draft->ruleKind,
            'multiplier' => $draft->multiplier,
            'conditions' => $draft->conditions,
            'label' => $draft->label,
            'is_active' => $draft->isActive,
            'starts_at' => $draft->startsAt,
            'ends_at' => $draft->endsAt,
        ]);

        return $rule->fresh();
    }

    public function deactivate(LoyaltyRule $rule): LoyaltyRule
    {
        $rule->update(['is_active' => false]);

        return $rule->fresh();
    }
}
