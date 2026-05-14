<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChargebackOpened
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $chargebackId,
        public int $paymentId,
        public int $amountMinor,
        public string $amountCurrency,
        public int $openedByAdminId,
    ) {}
}
