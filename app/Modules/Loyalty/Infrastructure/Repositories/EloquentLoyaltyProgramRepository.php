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
        return LoyaltyProgram::where('vendor_profile_id', $vendorProfileId)->first();
    }

    public function findByPublicId(string $publicId): ?LoyaltyProgram
    {
        return LoyaltyProgram::where('public_id', $publicId)->first();
    }

    public function create(ProgramDraft $draft, int $vendorProfileId): LoyaltyProgram
    {
        return LoyaltyProgram::create([
            'vendor_profile_id' => $vendorProfileId,
            'name' => $draft->name,
            'terms' => $draft->terms,
            'currency' => $draft->currency,
            'status' => $draft->status,
            'expiration_days' => $draft->expirationDays,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function updateStatus(LoyaltyProgram $program, string $status): LoyaltyProgram
    {
        $program->update(['status' => $status, 'updated_by' => auth()->id()]);

        return $program->fresh();
    }

    public function update(LoyaltyProgram $program, ProgramDraft $draft): LoyaltyProgram
    {
        $program->update([
            'name' => $draft->name,
            'terms' => $draft->terms,
            'currency' => $draft->currency,
            'status' => $draft->status,
            'expiration_days' => $draft->expirationDays,
            'updated_by' => auth()->id(),
        ]);

        return $program->fresh();
    }
}
