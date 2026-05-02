<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $paymentId,
        public int $bookingId,
        public string $failureCode,
        public array $failureMessage,
    ) {}
}
