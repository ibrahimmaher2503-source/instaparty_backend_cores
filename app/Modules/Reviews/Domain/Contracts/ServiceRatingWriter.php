<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface ServiceRatingWriter
{
    /**
     * Atomically sets services.rating_avg and services.rating_count.
     * The implementation must round rating_avg to 2 decimals to fit DECIMAL(3,2).
     *
     * @param  int    $serviceId   internal id (NOT public_id)
     * @param  float  $newAverage  0.00..5.00
     * @param  int    $newCount    >= 0
     */
    public function update(int $serviceId, float $newAverage, int $newCount): void;
}
