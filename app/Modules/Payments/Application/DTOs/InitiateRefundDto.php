<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use Spatie\LaravelData\Data;

class InitiateRefundDto extends Data
{
    public function __construct(
        public int $paymentId,
        public int $bookingId,
        public int $initiatedBy,
        public RefundReasonCode $reasonCode,
        public array $reasonNotes,
        public string $idempotencyKey,
        public string $route,
    ) {}
}
