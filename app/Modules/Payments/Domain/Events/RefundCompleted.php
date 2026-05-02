<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $refundId,
        public int $paymentId,
        public int $bookingId,
        public int $amountMinor,
        public string $amountCurrency,
        public string $reasonCode,
    ) {}
}
