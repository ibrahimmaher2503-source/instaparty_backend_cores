<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Enums\RejectionState;
use Carbon\CarbonImmutable;

final readonly class VendorOnboardingChecklistDTO
{
    public function __construct(
        public int $vendorId,
        public bool $isSuspended,
        public ?CarbonImmutable $suspendedAt,
        public ?string $suspensionReason,
        public RejectionState $rejectionState,
        public ?string $rejectionReason,
        /** @var array<int, VendorOnboardingChecklistItemDTO> */
        public array $items,
        public int $completedCount,
        public int $totalCount,
        public int $progressPercent,
        public ?VendorOnboardingChecklistItemDTO $nextRecommendedAction,
        /** @var array<int, ProductType> */
        public array $approvedProductTypes,
    ) {}
}
