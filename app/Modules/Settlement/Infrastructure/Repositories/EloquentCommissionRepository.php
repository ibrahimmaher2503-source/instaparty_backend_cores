<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Domain\Models\Commission;
use Illuminate\Database\Eloquent\Collection;

class EloquentCommissionRepository
{
    public function findByBookingItemId(int $bookingItemId): ?Commission
    {
        return Commission::where('booking_item_id', $bookingItemId)->first();
    }

    /**
     * @return Collection<int, Commission>
     */
    public function findByPaymentId(int $paymentId): Collection
    {
        return Commission::where('payment_id', $paymentId)->get();
    }
}
