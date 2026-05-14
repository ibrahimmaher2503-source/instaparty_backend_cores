<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Spatie\LaravelData\Data;

class PingResult extends Data
{
    public function __construct(
        public bool $success,
        public int $latencyMs,
        public string $gatewayCode,
        public ?string $errorMessage = null,
    ) {}
}
