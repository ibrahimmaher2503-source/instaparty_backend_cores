<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\ReconcileWalletAction;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('detects an unbalanced ledger transaction group and flags it high severity', function (): void {
    $vp         = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $walletRepo = app(EloquentWalletRepository::class);

    $wallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    // Insert a ledger_transaction_group bypassing the LedgerWriter
    $group = LedgerTransactionGroup::create([
        'public_id'      => (string) Str::ulid(),
        'kind'           => TransactionKind::PaymentCapture->value,
        'currency'       => 'EGP',
        'correlation_id' => (string) Str::ulid(),
        'posted_at'      => now(),
        'created_at'     => now(),
    ]);

    // Insert TWO debit entries but NO credit entry — unbalanced
    \App\Modules\Settlement\Domain\Models\WalletLedgerEntry::create([
        'wallet_id'             => $wallet->id,
        'entry_type'            => 'vendor_credit',
        'direction'             => 'debit',
        'amount_minor'          => 50_000,
        'running_balance_minor' => 50_000,
        'currency'              => 'EGP',
        'transaction_group_id'  => $group->id,
        'idempotency_key'       => 'test_debit_1_' . Str::ulid(),
        'posted_at'             => now(),
    ]);

    // Intentionally NO matching credit entry → creates imbalance

    $reconRepo = app(EloquentReconciliationRepository::class);
    $run       = $reconRepo->createRun(
        scopeType: 'wallet',
        scopeParams: ['wallet_id' => $wallet->id],
        triggerKind: 'manual',
        triggeredByUserId: null,
        idempotencyKey: null,
        correlationId: (string) Str::ulid(),
    );

    app(ReconcileWalletAction::class)->execute($wallet->id, $run->id);

    $finding = ReconciliationFinding::query()
        ->where('reconciliation_run_id', $run->id)
        ->where('finding_type', ReconciliationFindingType::UnbalancedTransactionGroup->value)
        ->first();

    expect($finding)->not()->toBeNull();
    expect($finding->severity->value)->toBe(ReconciliationFindingSeverity::High->value);
    expect($finding->resolution)->toBeNull();
    expect($finding->resource_type)->toBe('ledger_transaction_groups');
    expect($finding->resource_id)->toBe($group->id);
})->group('us6');
