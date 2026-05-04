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
            'customer_id' => $request->customerId,
            'vendor_profile_id' => $request->vendorProfileId,
            'loyalty_program_id' => $request->programId,
            'loyalty_rule_id' => $request->ruleId,
            'booking_id' => $request->bookingId,
            'points_held' => $request->pointsToRedeem,
            'discount_minor' => $request->discountMinor,
            'discount_currency' => $request->discountCurrency,
            'status' => 'pending',
        ]);
    }

    public function findActiveForBooking(int $bookingId): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::where('booking_id', $bookingId)
            ->whereIn('status', ['pending', 'applied'])
            ->first();
    }

    public function findPendingForCustomerAndVendor(int $customerId, int $vendorProfileId): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::where('customer_id', $customerId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('status', 'pending')
            ->first();
    }

    public function findByPublicId(string $publicId): ?LoyaltyRedemption
    {
        return LoyaltyRedemption::where('public_id', $publicId)->first();
    }

    public function transitionTo(LoyaltyRedemption $redemption, string $state): LoyaltyRedemption
    {
        $redemption->status->transitionTo($state);
        $tsColumn = match ($state) {
            'applied' => 'applied_at',
            'voided' => 'voided_at',
            'reversed' => 'reversed_at',
            default => null,
        };
        if ($tsColumn) {
            $redemption->update([$tsColumn => now()]);
        }

        return $redemption->fresh();
    }
}
