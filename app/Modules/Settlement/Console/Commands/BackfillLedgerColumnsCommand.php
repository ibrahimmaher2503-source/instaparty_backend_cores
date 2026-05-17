<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill command for the Phase 4.9 ledger migration.
 *
 * Populates `posted_at` and `direction` on existing wallet_ledger rows that
 * were written before the ledger hardening migration. Must be run once during
 * Stage 1 (shadow mode) with `@ledger_backfill_in_progress = 1` to bypass
 * the immutability triggers.
 *
 * Safe to re-run — skips rows that already have the new columns populated.
 */
class BackfillLedgerColumnsCommand extends Command
{
    protected $signature = 'ledger:backfill
                            {--dry-run : Print counts without writing}
                            {--chunk=500 : Rows per batch}';

    protected $description = 'Backfill direction and posted_at on legacy wallet_ledger rows (Phase 4.9 one-shot migration)';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $total = DB::table('wallet_ledger')
            ->whereNull('direction')
            ->count();

        if ($total === 0) {
            $this->info('No rows require backfilling.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} rows to backfill." . ($dryRun ? ' (dry-run — no writes)' : ''));

        if ($dryRun) {
            return self::SUCCESS;
        }

        // Bypass the immutability trigger for this session (MySQL only — SQLite has no trigger).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET @ledger_backfill_in_progress = 1');
        }

        $processed = 0;

        DB::table('wallet_ledger')
            ->whereNull('direction')
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use (&$processed) {
                foreach ($rows as $row) {
                    $direction = match ($row->entry_type) {
                        'credit',
                        'payment_credit',
                        'refund_credit',
                        'commission_credit',
                        'withdrawal_reject_release' => 'credit',
                        default                    => 'debit',
                    };

                    DB::table('wallet_ledger')
                        ->where('id', $row->id)
                        ->update([
                            'direction' => $direction,
                            'posted_at' => $row->created_at,
                        ]);

                    $processed++;
                }

                $this->output->write('.');
            });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET @ledger_backfill_in_progress = 0');
        }

        $this->newLine();
        $this->info("Backfilled {$processed} rows.");

        return self::SUCCESS;
    }
}
