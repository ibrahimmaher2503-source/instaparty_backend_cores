<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;

readonly class VendorAutoSuspended
{
    public function __construct(
        public VendorProfile $vendorProfile,
        public VendorDocument $document,
    ) {}
}
