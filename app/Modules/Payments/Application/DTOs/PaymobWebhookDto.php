<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Brick\Money\Money;
use Spatie\LaravelData\Data;

class PaymobWebhookDto extends Data
{
    public function __construct(
        public string $gatewayRef,
        public bool $success,
        public ?string $failureCode,
        public ?Money $capturedAmount,
        public array $rawPayload,
        public string $transactionType = 'PURCHASE',
    ) {}
}
