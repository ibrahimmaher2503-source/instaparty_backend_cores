<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Spatie\LaravelData\Data;

class PaymentIntentDto extends Data
{
    public function __construct(
        public string $gatewayRef,
        public string $redirectUrl,
        public ?array $rawResponse,
    ) {}
}
