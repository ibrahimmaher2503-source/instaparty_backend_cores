<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcileFinancialsCommand extends Command
{
    protected $signature = 'reconcile:run
                            {--scope=all : Scope: all|wallet|vendor|date_range|recent_touch}
                            {--wallet-id= : Wallet ID (required when scope=wallet)}
                            {--vendor-id= : Vendor profile ID (required when scope=vendor)}
                            {--date-from= : ISO-8601 date start (required when scope=date_range)}
                            {--date-to= : ISO-8601 date end (required when scope=date_range)}
                            {--window=60min : Window for recent_touch scope (e.g. 60min, 2h)}';

    protected $description = 'Run financial reconciliation across wallets';

    public function handle(RunReconciliationAction $action): int
    {
        $scope = (string) $this->option('scope');

        $scopeParams = match ($scope) {
            'wallet'      => ['wallet_id' => (int) $this->option('wallet-id')],
            'vendor'      => ['vendor_id' => (int) $this->option('vendor-id')],
            'date_range'  => ['date_from' => $this->option('date-from'), 'date_to' => $this->option('date-to')],
            'recent_touch' => ['window' => $this->option('window')],
            default       => [],
        };

        $this->info("Starting reconciliation run (scope: {$scope}) ...");

        $result = $action->execute(new RunReconciliationInput(
            scopeType: $scope,
            scopeParams: $scopeParams,
            triggerKind: 'scheduled',
            triggeredByUserId: null,
            idempotencyKey: null,
            correlationId: (string) Str::ulid(),
        ));

        if ($result->wasIdempotentReplay) {
            $this->warn("A reconciliation run with scope={$scope} is already in progress ({$result->runPublicId}). Skipping.");

            return self::SUCCESS;
        }

        $this->info("Run {$result->runPublicId} completed with status: {$result->status}");
        $this->line("  Wallets scanned:   {$result->walletsScanned}");
        $this->line("  Findings:          {$result->findingsCount}");
        $this->line("  Auto-repaired:     {$result->autoRepairedCount}");
        $this->line("  Manual review:     {$result->manualReviewCount}");

        if ($result->manualReviewCount > 0) {
            $this->warn("{$result->manualReviewCount} finding(s) require manual review. Check the admin panel.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
