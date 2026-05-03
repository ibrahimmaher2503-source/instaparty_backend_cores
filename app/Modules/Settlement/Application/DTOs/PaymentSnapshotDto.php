<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use Carbon\Carbon;

final readonly class PaymentSnapshotDto
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $bookingId,
        public int $amountMinor,
        public string $currency,
        public PaymentStatus $status,
        public Carbon $capturedAt,
    ) {}
}
