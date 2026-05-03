<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use Brick\Money\Money;
use Spatie\LaravelData\Data;

class InitiatePaymentDto extends Data
{
    public function __construct(
        public int $bookingId,
        public int $payerId,
        public PaymentMethod $method,
        public Money $amount,
        public string $idempotencyKey,
        public string $route,
    ) {}
}
