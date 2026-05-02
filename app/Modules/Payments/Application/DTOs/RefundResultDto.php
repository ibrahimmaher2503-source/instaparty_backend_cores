<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use Spatie\LaravelData\Data;

class RefundResultDto extends Data
{
    public function __construct(
        public bool $success,
        public ?string $gatewayRef,
        public ?string $failureMessage,
    ) {}
}
