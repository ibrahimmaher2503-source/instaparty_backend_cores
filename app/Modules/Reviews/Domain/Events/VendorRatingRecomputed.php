<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class VendorRatingRecomputed
{
    use Dispatchable;

    public function __construct(
        public int $vendorProfileId,
        public string $vendorPublicId,
        public float $newAverage,
        public int $count,
    ) {}
}
