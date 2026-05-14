<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Application\DTOs\RedemptionRequest;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;

class EloquentLoyaltyRedemptionRepository implements LoyaltyRedemptionRepository
{
    public function create(RedemptionRequest $request): LoyaltyRedemption
    {
        return LoyaltyRedemption::create([
            'user_id' => $request->userId,
            'vendor_profile_id' => $request->vendorProfileId,
            'loyalty_program_id' => $request->programId,
            'booking_id' => $request->bookingId,
            'points_redeemed' => $request->pointsRedeemed,
            'amount_minor' => $request->amountMinor,
            'amount_currency' => $request->amountCurrency,
        ]);
    }

    public function findActiveForBooking(int $bookingId): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::query()->where('booking_id', $bookingId)->first();
    }

    public function findByPublicId(string $publicId): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::query()->where('public_id', $publicId)->first();
    }
}
