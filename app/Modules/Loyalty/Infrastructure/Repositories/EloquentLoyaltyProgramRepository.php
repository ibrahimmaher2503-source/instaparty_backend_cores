<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Application\DTOs\ProgramDraft;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;

class EloquentLoyaltyProgramRepository implements LoyaltyProgramRepository
{
    public function findByVendor(int $vendorProfileId): ?LoyaltyProgram
    {
        return LoyaltyProgram::query()->where('vendor_profile_id', $vendorProfileId)->first();
    }

    public function findByPublicId(string $publicId): ?LoyaltyProgram
    {
        return LoyaltyProgram::query()->where('public_id', $publicId)->first();
    }

    public function create(ProgramDraft $draft, int $vendorProfileId): LoyaltyProgram
    {
        return LoyaltyProgram::create([
            'vendor_profile_id' => $vendorProfileId,
            'is_active' => $draft->isActive,
            'points_per_currency_unit' => $draft->pointsPerCurrencyUnit,
            'points_value_minor' => $draft->pointsValueMinor,
            'points_value_currency' => $draft->pointsValueCurrency,
            'min_points_to_redeem' => $draft->minPointsToRedeem,
            'max_redeem_pct' => $draft->maxRedeemPct,
            'points_expire_after_days' => $draft->pointsExpireAfterDays,
            'name' => $draft->name,
            'terms' => $draft->terms,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function update(LoyaltyProgram $program, ProgramDraft $draft): LoyaltyProgram
    {
        $program->update([
            'is_active' => $draft->isActive,
            'points_per_currency_unit' => $draft->pointsPerCurrencyUnit,
            'points_value_minor' => $draft->pointsValueMinor,
            'points_value_currency' => $draft->pointsValueCurrency,
            'min_points_to_redeem' => $draft->minPointsToRedeem,
            'max_redeem_pct' => $draft->maxRedeemPct,
            'points_expire_after_days' => $draft->pointsExpireAfterDays,
            'name' => $draft->name,
            'terms' => $draft->terms,
            'updated_by' => auth()->id(),
        ]);

        return $program->fresh();
    }

    public function setActive(LoyaltyProgram $program, bool $isActive): LoyaltyProgram
    {
        $program->update([
            'is_active' => $isActive,
            'updated_by' => auth()->id(),
        ]);

        return $program->fresh();
    }
}
