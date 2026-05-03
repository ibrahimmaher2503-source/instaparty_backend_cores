<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;

final readonly class LoyaltyProgramConfigured
{
    public function __construct(
        public LoyaltyProgram $program,
    ) {}
}
