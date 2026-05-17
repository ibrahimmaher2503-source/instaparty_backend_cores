<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Application\DTOs\DetectedFinding;

interface ReconciliationDetector
{
    /** @return list<DetectedFinding> */
    public function detectCacheDrift(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectOrphanedRefunds(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectOrphanedLedgerEntries(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectUnbalancedGroups(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectCommissionsWithoutSnapshot(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectWithdrawalsWithoutReserve(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectNegativeVendorBalances(int $walletId): array;

    /** @return list<DetectedFinding> */
    public function detectCurrencyMismatches(int $walletId): array;
}
