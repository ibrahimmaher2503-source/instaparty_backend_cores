<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Application\DTOs\RedemptionRequest;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;

interface LoyaltyRedemptionRepository
{
    public function create(RedemptionRequest $request): LoyaltyRedemption;

    public function findActiveForBooking(int $bookingId): ?LoyaltyRedemption;

    public function findPendingForCustomerAndVendor(int $customerId, int $vendorProfileId): ?LoyaltyRedemption;

    public function findByPublicId(string $publicId): ?LoyaltyRedemption;

    public function transitionTo(LoyaltyRedemption $redemption, string $state): LoyaltyRedemption;
}
