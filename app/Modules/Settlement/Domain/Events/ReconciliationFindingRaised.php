<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReconciliationFindingRaised
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $findingId,
        public string $findingPublicId,
        public ReconciliationFindingType $findingType,
        public ReconciliationFindingSeverity $severity,
        public ?string $resourceType,
        public ?int $resourceId,
    ) {}
}
