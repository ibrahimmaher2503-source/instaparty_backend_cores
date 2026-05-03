<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservationsAction
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            return ServiceInventoryReservation::query()
                ->where('status', ReservationStatus::Held)
                ->where('expires_at', '<', now())
                ->update(['status' => ReservationStatus::Expired]);
        });
    }
}
