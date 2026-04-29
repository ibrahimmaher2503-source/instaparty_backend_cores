<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use Illuminate\Foundation\Events\Dispatchable;

class VendorTypeRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly VendorApprovedProductType $approval,
        public readonly ?int $revokedBy = null,
        public readonly ?array $reason = null,
    ) {}
}
