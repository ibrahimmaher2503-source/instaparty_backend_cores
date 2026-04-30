<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Events\Dispatchable;

class VendorTypeRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $vendorProfileId,
        public readonly ProductType $productType,
        public readonly ?int $revokedBy = null,
        public readonly ?array $reason = null,
    ) {}
}
