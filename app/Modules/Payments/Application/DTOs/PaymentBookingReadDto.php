<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class PaymentBookingReadDto extends Data
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $customerId,
        public string $lifecycleStatus,
        public string $paymentStatus,
        public int $totalMinor,
        public string $totalCurrency,
        public ?Carbon $paymentHoldExpiresAt,
    ) {}
}
