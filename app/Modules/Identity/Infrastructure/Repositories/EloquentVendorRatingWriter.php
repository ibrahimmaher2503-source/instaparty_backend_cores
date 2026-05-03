<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Reviews\Domain\Contracts\VendorRatingWriter;
use Illuminate\Support\Facades\DB;

class EloquentVendorRatingWriter implements VendorRatingWriter
{
    public function update(int $vendorProfileId, float $newAverage, int $newCount): void
    {
        DB::table('vendor_profiles')
            ->where('id', $vendorProfileId)
            ->update([
                'rating_avg'   => round($newAverage, 2),
                'rating_count' => $newCount,
            ]);
    }
}
