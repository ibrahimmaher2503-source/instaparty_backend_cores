<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;

final readonly class VendorOnboardingChecklistItemDTO
{
    public function __construct(
        public VendorOnboardingChecklistItemKey $key,
        public ChecklistItemStatus $status,
        public string $label,
        public ?string $subText,
        public ?string $url,
    ) {}
}
