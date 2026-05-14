<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Foundation\Events\Dispatchable;

class VendorProfileResubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly VendorProfile $vendorProfile,
        public readonly ChangeRequest $changeRequest,
    ) {}
}
