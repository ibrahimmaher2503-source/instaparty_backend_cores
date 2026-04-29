<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use Illuminate\Foundation\Events\Dispatchable;

class VendorApprovedForType
{
    use Dispatchable;

    public function __construct(public readonly VendorApprovedProductType $approval) {}
}
