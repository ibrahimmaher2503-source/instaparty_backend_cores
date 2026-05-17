<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

final readonly class ReconciliationRunResult
{
    public function __construct(
        public int $runId,
        public string $runPublicId,
        public string $status,
        public int $walletsScanned,
        public int $findingsCount,
        public int $autoRepairedCount,
        public int $manualReviewCount,
        public bool $wasIdempotentReplay = false,
    ) {}
}
