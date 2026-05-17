<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\ReconcileWalletResult;
use App\Modules\Settlement\Domain\Contracts\ReconciliationDetector;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Events\ReconciliationFindingRaised;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use Illuminate\Support\Facades\DB;

class ReconcileWalletAction
{
    public function __construct(
        private readonly ReconciliationDetector $detector,
        private readonly ProjectWalletBalanceAction $projector,
        private readonly EloquentReconciliationRepository $reconciliationRepo,
    ) {}

    public function execute(int $walletId, int $reconciliationRunId): ReconcileWalletResult
    {
        $run      = \App\Modules\Settlement\Domain\Models\ReconciliationRun::findOrFail($reconciliationRunId);
        $findings = [];

        DB::transaction(function () use ($walletId, $run, &$findings): void {
            Wallet::query()->lockForUpdate()->findOrFail($walletId);

            $allDetections = array_merge(
                $this->detector->detectCacheDrift($walletId),
                $this->detector->detectOrphanedRefunds($walletId),
                $this->detector->detectOrphanedLedgerEntries($walletId),
                $this->detector->detectUnbalancedGroups($walletId),
                $this->detector->detectCommissionsWithoutSnapshot($walletId),
                $this->detector->detectWithdrawalsWithoutReserve($walletId),
                $this->detector->detectNegativeVendorBalances($walletId),
                $this->detector->detectCurrencyMismatches($walletId),
            );

            foreach ($allDetections as $detected) {
                $finding = $this->reconciliationRepo->recordFinding($run, $detected);
                $findings[] = $finding;

                // Auto-repair wallet cache drift (warning severity)
                if ($detected->severity === ReconciliationFindingSeverity::Warning) {
                    $this->projector->project($walletId);
                    $finding->update(['resolution' => 'auto_repaired', 'resolved_at' => now()]);
                }

                // Emit event for high-severity findings so listeners can notify admins
                if ($detected->severity === ReconciliationFindingSeverity::High) {
                    DB::afterCommit(fn () => event(new ReconciliationFindingRaised(
                        findingId: $finding->id,
                        findingPublicId: (string) $finding->public_id,
                        findingType: $detected->findingType,
                        severity: $detected->severity,
                        resourceType: $detected->resourceType,
                        resourceId: $detected->resourceId,
                    )));
                }
            }
        });

        $autoRepaired  = count(array_filter($findings, fn ($f) => $f->resolution === 'auto_repaired'));
        $manualReview  = count($findings) - $autoRepaired;

        return new ReconcileWalletResult(
            walletId: $walletId,
            findingsCount: count($findings),
            autoRepairedCount: $autoRepaired,
            manualReviewCount: $manualReview,
        );
    }
}
