<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Reviews\Domain\Contracts\ServiceRatingWriter;
use Illuminate\Support\Facades\DB;

class EloquentServiceRatingWriter implements ServiceRatingWriter
{
    public function update(int $serviceId, float $newAverage, int $newCount): void
    {
        DB::table('services')
            ->where('id', $serviceId)
            ->update([
                'rating_avg' => round($newAverage, 2),
                'rating_count' => $newCount,
            ]);
    }
}
