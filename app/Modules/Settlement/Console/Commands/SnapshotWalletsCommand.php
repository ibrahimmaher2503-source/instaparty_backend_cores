<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use App\Modules\Settlement\Application\Actions\CreateFinancialSnapshotAction;
use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Console\Command;

class SnapshotWalletsCommand extends Command
{
    protected $signature = 'ledger:snapshot
                            {--all : Snapshot every wallet in the database}
                            {--wallet= : ID of the wallet to snapshot}';

    protected $description = 'Create financial balance snapshots anchored to the current ledger high-water mark';

    public function handle(CreateFinancialSnapshotAction $action): int
    {
        $walletId = $this->option('wallet');

        if ($walletId !== null) {
            $this->snapshotOne((int) $walletId, $action);

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $total = 0;
            $created = 0;
            $skipped = 0;

            Wallet::query()->select('id')->chunkById(200, function ($wallets) use ($action, &$total, &$created, &$skipped): void {
                foreach ($wallets as $wallet) {
                    $total++;
                    $snapshot = $action->execute($wallet->id);

                    if ($snapshot === null) {
                        $skipped++;
                    } elseif ($snapshot->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

            $this->info("Snapshot complete — {$created} created, {$skipped} skipped out of {$total} wallets.");

            return self::SUCCESS;
        }

        $this->error('Pass --all or --wallet=N');

        return self::FAILURE;
    }

    private function snapshotOne(int $walletId, CreateFinancialSnapshotAction $action): void
    {
        $snapshot = $action->execute($walletId);

        if ($snapshot === null) {
            $this->warn("Wallet #{$walletId} has no ledger entries — snapshot skipped.");

            return;
        }

        if ($snapshot->wasRecentlyCreated) {
            $this->info("Wallet #{$walletId} snapshotted at ledger entry #{$snapshot->as_of_ledger_entry_id}.");
        } else {
            $this->line("Wallet #{$walletId} already snapshotted today — skipped.");
        }
    }
}
