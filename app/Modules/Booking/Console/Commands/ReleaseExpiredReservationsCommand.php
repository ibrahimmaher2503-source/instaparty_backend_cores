<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'booking:release-expired-reservations';

    protected $description = 'Release held inventory reservations that have passed their TTL';

    public function handle(): int
    {
        $released = 0;

        ServiceInventoryReservation::where('status', ReservationStatus::Held)
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$released): void {
                        $reservation->update([
                            'status' => ReservationStatus::Expired,
                        ]);
                        $released++;
                    });
                }
            });

        $this->info("Released {$released} expired reservations.");

        return Command::SUCCESS;
    }
}
