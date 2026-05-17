<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use App\Modules\Settlement\Application\Actions\SettlementRunAction;
use Illuminate\Console\Command;

class SettleRunCommand extends Command
{
    protected $signature = 'settle:run';

    protected $description = 'Process all approved withdrawal payouts in a crash-safe batch';

    public function handle(SettlementRunAction $action): int
    {
        $this->info('Starting settlement batch run...');

        $result = $action->execute();

        $this->info("Settled:  {$result->settled}");

        if ($result->failed > 0) {
            $this->warn("Failed:   {$result->failed}");
            foreach ($result->errors as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
