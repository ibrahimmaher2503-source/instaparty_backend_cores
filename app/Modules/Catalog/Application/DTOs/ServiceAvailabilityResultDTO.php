<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Carbon\Carbon;

final class ServiceAvailabilityResultDTO
{
    public function __construct(
        public readonly bool $available,
        public readonly ProductType $productType,
        public readonly ?string $reasonCode,
        public readonly ?int $remainingQuantity,
        public readonly ?Carbon $startsAt,
        public readonly ?Carbon $endsAt,
    ) {}
}
