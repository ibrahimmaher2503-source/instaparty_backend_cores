<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Events\Dispatchable;

class VendorSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly VendorProfile $vendorProfile,
        public readonly ?int $suspendedBy = null,
    ) {}
}
