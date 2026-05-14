<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Carbon\CarbonInterface;

interface LoyaltyLedgerRepository
{
    /**
     * Append a new ledger row. Append-only: never update.
     *
     * @param  LedgerDirection  $direction  earn|redeem|expire|adjust
     * @param  int  $points  unsigned magnitude (direction implies sign)
     * @param  int  $balanceAfter  pre-computed running balance after this row
     * @param  ?array  $reason  translatable {en,ar} reason payload
     * @param  ?CarbonInterface  $expiresAt  only meaningful for direction=earn
     */
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
    ): LoyaltyLedgerEntry;

    /**
     * Current balance for (user, vendor). Reads latest balance_after, not a sum.
     */
    public function balanceFor(int $userId, int $vendorProfileId): int;

    /**
     * Whether any direction='earn' ledger row exists for (user, vendor).
     * Used by first-booking rule resolution.
     */
    public function hasPriorEarnFor(int $userId, int $vendorProfileId): bool;

    /**
     * Idempotency check: whether an earn row already exists for a given
     * (reference_type, reference_id) — e.g., booking_item:123.
     */
    public function hasEarnForReference(string $referenceType, int $referenceId): bool;

    /**
     * Whether any adjust row exists for a given (reference_type, reference_id).
     * Used to make reverse/void redemption idempotent.
     */
    public function hasAdjustForReference(string $referenceType, int $referenceId): bool;

    /**
     * Sum of points earned today (since UTC midnight) for (user, vendor).
     * Used by per-day earn cap.
     */
    public function pointsEarnedTodayFor(int $userId, int $vendorProfileId): int;
}
