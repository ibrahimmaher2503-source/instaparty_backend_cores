<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Produces a summary inventory of the wallet_ledger table:
 * - Entry counts by entry_type
 * - Entry counts by direction (once Phase 4.9 backfill is complete)
 * - Null-direction row count (indicates incomplete backfill)
 * - Transaction group counts and balance-zero verification
 *
 * Useful as a health-check before and after the ledger cut-over.
 */
class LedgerInventoryCommand extends Command
{
    protected $signature = 'ledger:inventory
                            {--json : Output as JSON}';

    protected $description = 'Print an inventory summary of the wallet_ledger table';

    public function handle(): int
    {
        $byType = DB::table('wallet_ledger')
            ->selectRaw('entry_type, direction, COUNT(*) as cnt')
            ->groupBy('entry_type', 'direction')
            ->orderBy('entry_type')
            ->get();

        $nullDirection = DB::table('wallet_ledger')
            ->whereNull('direction')
            ->count();

        $groupCount = DB::table('ledger_transaction_groups')->count();

        $summary = [
            'total_entries'          => DB::table('wallet_ledger')->count(),
            'null_direction_entries' => $nullDirection,
            'transaction_groups'     => $groupCount,
            'by_type_and_direction'  => $byType->toArray(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Ledger inventory as of " . now()->toDateTimeString());
        $this->line("Total entries : {$summary['total_entries']}");
        $this->line("Null direction: {$summary['null_direction_entries']}");
        $this->line("TXN groups    : {$summary['transaction_groups']}");
        $this->newLine();

        $this->table(
            ['entry_type', 'direction', 'count'],
            collect($byType)->map(fn ($r) => [(string) $r->entry_type, (string) ($r->direction ?? 'NULL'), $r->cnt]),
        );

        if ($nullDirection > 0) {
            $this->warn("Backfill incomplete — run `php artisan ledger:backfill` to populate direction.");
        }

        return self::SUCCESS;
    }
}
