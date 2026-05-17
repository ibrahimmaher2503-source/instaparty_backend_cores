<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReconciliationRunCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $runId,
        public string $runPublicId,
        public string $status,
        public int $walletsScanned,
        public int $findingsCount,
        public int $autoRepairedCount,
        public int $manualReviewCount,
    ) {}
}
