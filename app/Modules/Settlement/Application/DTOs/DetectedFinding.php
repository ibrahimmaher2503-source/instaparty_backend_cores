<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;

final readonly class DetectedFinding
{
    public function __construct(
        public ReconciliationFindingType $findingType,
        public ReconciliationFindingSeverity $severity,
        public ?string $resourceType,
        public ?int $resourceId,
        public ?array $expected,
        public ?array $actual,
        public ?array $delta,
        public ?string $descriptionKey = null,
        public ?array $descriptionParams = null,
    ) {}
}
