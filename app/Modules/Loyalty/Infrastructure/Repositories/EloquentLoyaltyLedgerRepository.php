<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Events\LoyaltyLedgerEntryAppended;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Illuminate\Support\Facades\DB;

class EloquentLoyaltyLedgerRepository implements LoyaltyLedgerRepository
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
    ): LoyaltyLedgerEntry {
        $entry = LoyaltyLedgerEntry::create([
            'customer_id'             => $customerId,
            'vendor_profile_id'       => $vendorProfileId,
            'loyalty_program_id'      => $programId,
            'entry_type'              => $type->value,
            'points'                  => $points,
            'booking_id'              => $bookingId,
            'booking_item_id'         => $bookingItemId,
            'redemption_id'           => $redemptionId,
            'reversed_from_ledger_id' => $reversedFromLedgerId,
            'product_type'            => $productType,
            'reason'                  => $reason,
        ]);

        DB::afterCommit(fn () => event(new LoyaltyLedgerEntryAppended($entry)));

        return $entry;
    }

    public function balanceFor(int $customerId, int $vendorProfileId): int
    {
        return (int) LoyaltyLedgerEntry::where('customer_id', $customerId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->sum('points');
    }

    public function heldPointsFor(int $customerId, int $vendorProfileId): int
    {
        return (int) \App\Modules\Loyalty\Domain\Models\LoyaltyRedemption::where('customer_id', $customerId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('status', 'pending')
            ->sum('points_held');
    }
}
