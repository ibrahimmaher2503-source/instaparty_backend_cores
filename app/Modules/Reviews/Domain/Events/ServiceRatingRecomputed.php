<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ServiceRatingRecomputed
{
    use Dispatchable;

    public function __construct(
        public int $serviceId,
        public string $servicePublicId,
        public float $newAverage,
        public int $count,
    ) {}
}
