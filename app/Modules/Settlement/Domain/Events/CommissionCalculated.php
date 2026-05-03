<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionCalculated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $commissionId,
        public int $vendorProfileId,
        public int $vendorShareMinor,
        public string $currency,
    ) {}
}
