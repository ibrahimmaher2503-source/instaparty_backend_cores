<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;

interface LoyaltyProgramRepository
{
    public function findByVendor(int $vendorProfileId): ?LoyaltyProgram;

    public function findByPublicId(string $publicId): ?LoyaltyProgram;

    public function create(ProgramDraft $draft, int $vendorProfileId): LoyaltyProgram;

    public function update(LoyaltyProgram $program, ProgramDraft $draft): LoyaltyProgram;

    public function setActive(LoyaltyProgram $program, bool $isActive): LoyaltyProgram;
}
