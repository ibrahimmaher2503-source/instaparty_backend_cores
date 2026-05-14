<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargebackResolved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $chargebackId,
        public int $paymentId,
        public ChargebackStatus $resolution,
        public int $resolvedByAdminId,
    ) {}
}
