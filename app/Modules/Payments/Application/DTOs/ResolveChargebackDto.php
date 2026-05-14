<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Payments\Domain\Enums\ChargebackStatus;
use Spatie\LaravelData\Data;

class ResolveChargebackDto extends Data
{
    public function __construct(
        public int $chargebackId,
        public int $adminUserId,
        public ChargebackStatus $status,
        public ?array $adminNotes = null,
    ) {}
}
