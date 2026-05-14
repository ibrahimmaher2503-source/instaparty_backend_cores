<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Spatie\LaravelData\Data;

class OpenChargebackDto extends Data
{
    public function __construct(
        public int $paymentId,
        public int $adminUserId,
        public array $reason,
        public int $amountMinor,
        public string $amountCurrency,
        public ?string $gatewayCaseId = null,
        public ?array $adminNotes = null,
    ) {}
}
