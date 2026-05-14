<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Console\Commands;

use App\Modules\Loyalty\Application\Actions\ExpireLoyaltyPointsAction;
use Illuminate\Console\Command;

class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire {--dry-run : Report what would be expired without writing}';

    protected $description = 'Expire loyalty points whose expires_at has passed';

    public function handle(ExpireLoyaltyPointsAction $action): int
    {
        if ($this->option('dry-run')) {
            $this->info('[dry-run] No ledger rows will be written.');

            return self::SUCCESS;
        }

        $count = $action->execute();

        $this->info("Loyalty: expired {$count} earn row(s).");

        return self::SUCCESS;
    }
}
