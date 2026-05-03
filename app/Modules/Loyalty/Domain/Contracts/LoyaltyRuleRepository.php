<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Application\DTOs\RuleDraft;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;

interface LoyaltyRuleRepository
{
    public function activeRuleFor(int $programId): ?LoyaltyRule;

    public function replaceActive(int $programId, RuleDraft $draft): LoyaltyRule;
}
