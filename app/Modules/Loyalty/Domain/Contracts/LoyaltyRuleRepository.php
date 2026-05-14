<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Illuminate\Support\Collection;

interface LoyaltyRuleRepository
{
    /**
     * Active + currently-valid rules for a program, ordered for first-match resolution.
     *
     * @return Collection<int, LoyaltyRule>
     */
    public function activeRulesFor(int $programId): Collection;

    public function create(int $programId, RuleDraft $draft): LoyaltyRule;

    public function update(LoyaltyRule $rule, RuleDraft $draft): LoyaltyRule;

    public function deactivate(LoyaltyRule $rule): LoyaltyRule;
}
