<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;

interface LoyaltyLedgerRepository
{
    public function append(
        LedgerEntryType $type,
        int $customerId,
        int $vendorProfileId,
        int $programId,
        int $points,
        array $reason,
        ?int $bookingId = null,
        ?int $bookingItemId = null,
        ?int $redemptionId = null,
        ?int $reversedFromLedgerId = null,
        ?string $productType = null,
    ): LoyaltyLedgerEntry;

    public function balanceFor(int $customerId, int $vendorProfileId): int;

    public function heldPointsFor(int $customerId, int $vendorProfileId): int;
}
