<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Catalog\Application\Actions\ReleaseExpiredReservationsAction;
use Illuminate\Console\Command;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'catalog:release-expired-reservations';

    protected $description = 'Mark expired inventory reservations as expired';

    public function handle(ReleaseExpiredReservationsAction $action): int
    {
        $count = $action->execute();
        $this->info("Released {$count} expired reservation(s).");

        return Command::SUCCESS;
    }
}
