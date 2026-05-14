<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Events\LoyaltyLedgerEntryAppended;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EloquentLoyaltyLedgerRepository implements LoyaltyLedgerRepository
{
    public function append(
        int $userId,
        int $vendorProfileId,
        int $programId,
        LedgerDirection $direction,
        int $points,
        int $balanceAfter,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $reason = null,
        ?CarbonInterface $expiresAt = null,
    ): LoyaltyLedgerEntry {
        $entry = LoyaltyLedgerEntry::create([
            'user_id' => $userId,
            'vendor_profile_id' => $vendorProfileId,
            'loyalty_program_id' => $programId,
            'direction' => $direction,
            'points' => $points,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'expires_at' => $direction === LedgerDirection::Earn ? $expiresAt : null,
        ]);

        DB::afterCommit(fn () => event(new LoyaltyLedgerEntryAppended($entry)));

        return $entry;
    }

    public function balanceFor(int $userId, int $vendorProfileId): int
    {
        $latest = LoyaltyLedgerEntry::query()
            ->where('user_id', $userId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->orderByDesc('id')
            ->first(['balance_after']);

        return $latest === null ? 0 : max(0, (int) $latest->balance_after);
    }

    public function hasPriorEarnFor(int $userId, int $vendorProfileId): bool
    {
        return LoyaltyLedgerEntry::query()
            ->where('user_id', $userId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('direction', LedgerDirection::Earn->value)
            ->exists();
    }

    public function hasEarnForReference(string $referenceType, int $referenceId): bool
    {
        return LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Earn->value)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    public function hasAdjustForReference(string $referenceType, int $referenceId): bool
    {
        return LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Adjust->value)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    public function pointsEarnedTodayFor(int $userId, int $vendorProfileId): int
    {
        return (int) LoyaltyLedgerEntry::query()
            ->where('user_id', $userId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->where('direction', LedgerDirection::Earn->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('points');
    }
}
