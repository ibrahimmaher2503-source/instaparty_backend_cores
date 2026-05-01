<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'booking:release-expired-reservations';

    protected $description = 'Release held inventory reservations that have passed their TTL';

    public function handle(): int
    {
        $released = 0;

        DB::table('service_inventory_reservations')
            ->where('status', 'held')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(200, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$released): void {
                        DB::table('service_inventory_reservations')
                            ->where('id', $reservation->id)
                            ->update([
                                'status' => 'expired',
                                'updated_at' => now(),
                            ]);
                        $released++;
                    });
                }
            });

        $this->info("Released {$released} expired reservations.");

        return Command::SUCCESS;
    }
}
