<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface VendorRatingWriter
{
    public function update(int $vendorProfileId, float $newAverage, int $newCount): void;
}
