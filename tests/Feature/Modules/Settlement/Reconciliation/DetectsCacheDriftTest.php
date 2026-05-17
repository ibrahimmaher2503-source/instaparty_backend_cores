<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Application\Actions\ReconcileWalletAction;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('detects wallet cache drift and auto-repairs it', function (): void {
    $vp         = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $walletRepo = app(EloquentWalletRepository::class);

    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    app(CreditWalletAction::class)->execute(
        walletId: $wallet->id,
        amountMinor: 100_000,
        currency: 'EGP',
        idempotencyKey: 'drift_seed_' . $vp->id,
        correlationId: (string) Str::ulid(),
    );

    // Perturb the cache directly — simulates a stale projection
    $wallet->update(['balance_minor' => 99_000]);

    // Create a reconciliation run to associate findings with
    $reconRepo = app(EloquentReconciliationRepository::class);
    $run       = $reconRepo->createRun(
        scopeType: 'wallet',
        scopeParams: ['wallet_id' => $wallet->id],
        triggerKind: 'manual',
        triggeredByUserId: null,
        idempotencyKey: null,
        correlationId: (string) Str::ulid(),
    );

    $result = app(ReconcileWalletAction::class)->execute($wallet->id, $run->id);

    expect($result->findingsCount)->toBeGreaterThanOrEqual(1);
    expect($result->autoRepairedCount)->toBeGreaterThanOrEqual(1);

    // A wallet_cache_drift finding should exist
    $finding = ReconciliationFinding::query()
        ->where('reconciliation_run_id', $run->id)
        ->where('finding_type', ReconciliationFindingType::WalletCacheDrift->value)
        ->first();

    expect($finding)->not()->toBeNull();
    expect($finding->severity->value)->toBe(ReconciliationFindingSeverity::Warning->value);
    expect($finding->resolution)->toBe('auto_repaired');

    // Cache must be restored to the ledger-projected value
    $wallet->refresh();
    expect((int) $wallet->balance_minor)->toBe(100_000);
})->group('us6');
