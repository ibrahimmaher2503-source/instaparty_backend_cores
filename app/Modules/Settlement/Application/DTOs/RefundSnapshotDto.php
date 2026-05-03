<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\DTOs;

use Carbon\Carbon;

final readonly class RefundSnapshotDto
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $paymentId,
        public ?int $bookingItemId,
        public int $amountMinor,
        public string $currency,
        public Carbon $completedAt,
    ) {}
}
