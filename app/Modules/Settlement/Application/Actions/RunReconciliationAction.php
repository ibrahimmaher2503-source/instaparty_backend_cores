<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\ReconciliationRunResult;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Events\ReconciliationRunCompleted;
use App\Modules\Settlement\Domain\Events\ReconciliationRunStarted;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Locks\RedisWalletLocker;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RunReconciliationAction
{
    public function __construct(
        private readonly ReconcileWalletAction $reconcileWallet,
        private readonly EloquentReconciliationRepository $reconciliationRepo,
    ) {}

    public function execute(RunReconciliationInput $input): ReconciliationRunResult
    {
        $scopeHash = md5(serialize([$input->scopeType, $input->scopeParams]));
        $lockKey   = "lock:reconcile:{$scopeHash}";

        $lock = Cache::lock($lockKey, 900); // 15 min TTL

        if (! $lock->get()) {
            // Another run with the same scope is in progress — return existing
            $existing = \App\Modules\Settlement\Domain\Models\ReconciliationRun::query()
                ->where('scope_type', $input->scopeType)
                ->whereIn('status', [ReconciliationStatus::Running->value, ReconciliationStatus::Queued->value])
                ->latest('created_at')
                ->first();

            if ($existing !== null) {
                return new ReconciliationRunResult(
                    runId: $existing->id,
                    runPublicId: (string) $existing->public_id,
                    status: $existing->status->value,
                    walletsScanned: (int) $existing->wallets_scanned,
                    findingsCount: (int) $existing->findings_count,
                    autoRepairedCount: (int) $existing->auto_repaired_count,
                    manualReviewCount: (int) $existing->manual_review_count,
                    wasIdempotentReplay: true,
                );
            }
        }

        try {
            $run = $this->reconciliationRepo->createRun(
                scopeType: $input->scopeType,
                scopeParams: $input->scopeParams ?: null,
                triggerKind: $input->triggerKind,
                triggeredByUserId: $input->triggeredByUserId,
                idempotencyKey: $input->idempotencyKey,
                correlationId: $input->correlationId,
            );

            $this->reconciliationRepo->markRunning($run);

            DB::afterCommit(fn () => event(new ReconciliationRunStarted(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                scopeType: $input->scopeType,
            )));

            $walletIds        = $this->resolveWalletIds($input);
            $walletsScanned   = 0;
            $findingsCount    = 0;
            $autoRepairedCount = 0;
            $manualReviewCount = 0;

            foreach ($walletIds as $walletId) {
                $result = $this->reconcileWallet->execute($walletId, $run->id);
                $walletsScanned++;
                $findingsCount     += $result->findingsCount;
                $autoRepairedCount += $result->autoRepairedCount;
                $manualReviewCount += $result->manualReviewCount;
            }

            $finalStatus = match (true) {
                $findingsCount === 0         => ReconciliationStatus::Clean,
                $manualReviewCount > 0       => ReconciliationStatus::RequiresManualReview,
                default                      => ReconciliationStatus::Repaired,
            };

            $this->reconciliationRepo->finalise($run, $finalStatus, [
                'wallets_scanned'    => $walletsScanned,
                'findings_count'     => $findingsCount,
                'auto_repaired_count' => $autoRepairedCount,
                'manual_review_count' => $manualReviewCount,
            ]);

            DB::afterCommit(fn () => event(new ReconciliationRunCompleted(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                status: $finalStatus->value,
                walletsScanned: $walletsScanned,
                findingsCount: $findingsCount,
                autoRepairedCount: $autoRepairedCount,
                manualReviewCount: $manualReviewCount,
            )));

            return new ReconciliationRunResult(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                status: $finalStatus->value,
                walletsScanned: $walletsScanned,
                findingsCount: $findingsCount,
                autoRepairedCount: $autoRepairedCount,
                manualReviewCount: $manualReviewCount,
            );
        } finally {
            $lock->forceRelease();
        }
    }

    /** @return array<int> */
    private function resolveWalletIds(RunReconciliationInput $input): array
    {
        return match ($input->scopeType) {
            'wallet' => [(int) ($input->scopeParams['wallet_id'] ?? 0)],
            'vendor' => Wallet::query()
                ->where('owner_type', 'App\Modules\Identity\Domain\Models\VendorProfile')
                ->where('owner_id', (int) ($input->scopeParams['vendor_id'] ?? 0))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'date_range' => Wallet::query()
                ->whereHas('ledgerEntries', fn ($q) => $q
                    ->whereBetween('created_at', [
                        $input->scopeParams['date_from'] ?? now()->subMonth()->toDateString(),
                        $input->scopeParams['date_to'] ?? now()->toDateString(),
                    ])
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'recent_touch' => Wallet::query()
                ->whereHas('ledgerEntries', fn ($q) => $q
                    ->where('created_at', '>=', now()->sub(
                        \Carbon\CarbonInterval::fromString($input->scopeParams['window'] ?? '60min')
                    ))
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            default => Wallet::query()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        };
    }
}
