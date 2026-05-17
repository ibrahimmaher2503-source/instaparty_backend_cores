<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Compares `wallets.balance_minor` (projection cache) against the running total
 * derived from `wallet_ledger` (canonical source of truth).
 *
 * Run during shadow stage to detect any drift before cutting over.
 */
class LedgerDiffCommand extends Command
{
    protected $signature = 'ledger:diff
                            {--wallet= : Restrict to a single wallet ID}
                            {--currency=EGP : Currency filter}
                            {--fail-on-drift : Exit non-zero if any wallet has drift}';

    protected $description = 'Compare wallet balance cache against ledger-derived balances and report drift';

    public function handle(): int
    {
        $walletId = $this->option('wallet') ? (int) $this->option('wallet') : null;
        $currency = $this->option('currency');

        $query = DB::table('wallets as w')
            ->select([
                'w.id',
                'w.owner_type',
                'w.owner_id',
                'w.balance_minor as cached_balance',
                DB::raw("
                    (SELECT COALESCE(
                        SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE 0 END) -
                        SUM(CASE WHEN direction = 'debit'  THEN amount_minor ELSE 0 END)
                    , 0)
                    FROM wallet_ledger
                    WHERE wallet_id = w.id
                    ) AS ledger_balance
                "),
            ])
            ->where('w.currency', $currency);

        if ($walletId !== null) {
            $query->where('w.id', $walletId);
        }

        $rows = $query->get();

        $driftRows = $rows->filter(fn ($r) => (int) $r->cached_balance !== (int) $r->ledger_balance);

        if ($driftRows->isEmpty()) {
            $this->info("All {$rows->count()} wallets are consistent.");

            return self::SUCCESS;
        }

        $this->warn("Found {$driftRows->count()} wallet(s) with drift:");

        $tableData = $driftRows->map(fn ($r) => [
            $r->id,
            "{$r->owner_type}:{$r->owner_id}",
            number_format($r->cached_balance),
            number_format($r->ledger_balance),
            number_format((int) $r->ledger_balance - (int) $r->cached_balance),
        ])->toArray();

        $this->table(
            ['wallet_id', 'owner', 'cached_balance', 'ledger_balance', 'delta'],
            $tableData,
        );

        return $this->option('fail-on-drift') ? self::FAILURE : self::SUCCESS;
    }
}
