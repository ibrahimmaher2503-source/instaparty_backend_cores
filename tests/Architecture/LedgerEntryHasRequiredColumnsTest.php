<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * T057 — Architecture guard: wallet_ledger table must have all Phase 4.9 columns.
 *
 * This test runs against the actual database schema (SQLite in CI, MySQL locally)
 * to ensure the ledger hardening migrations have been applied correctly.
 */

it('wallet_ledger has all Phase 4.9 required columns', function (string $column): void {
    expect(Schema::hasColumn('wallet_ledger', $column))
        ->toBeTrue("wallet_ledger is missing column: {$column}");
})->with([
    'direction',
    'running_balance_minor',
    'transaction_group_id',
    'counter_account_type',
    'counter_account_id',
    'correlation_id',
    'causation_id',
    'idempotency_key',
    'posted_at',
])->group('architecture', 'ledger', 'schema');

it('ledger_transaction_groups table exists with required columns', function (string $column): void {
    expect(Schema::hasTable('ledger_transaction_groups'))->toBeTrue();
    expect(Schema::hasColumn('ledger_transaction_groups', $column))
        ->toBeTrue("ledger_transaction_groups is missing column: {$column}");
})->with([
    'id',
    'public_id',
    'kind',
    'currency',
    'idempotency_key',
    'correlation_id',
    'causation_id',
    'initiated_by_user_id',
    'initiator_type',
    'metadata',
    'created_at',
])->group('architecture', 'ledger', 'schema');

it('financial_snapshots table exists with required columns', function (string $column): void {
    expect(Schema::hasTable('financial_snapshots'))->toBeTrue();
    expect(Schema::hasColumn('financial_snapshots', $column))
        ->toBeTrue("financial_snapshots is missing column: {$column}");
})->with([
    'id',
    'wallet_id',
    'snapshot_at',
    'as_of_ledger_entry_id',
    'balance_minor',
    'available_minor',
    'pending_withdrawal_minor',
    'currency',
    'checksum',
    'created_at',
])->group('architecture', 'ledger', 'schema');

it('reconciliation_runs table exists', function (): void {
    expect(Schema::hasTable('reconciliation_runs'))->toBeTrue();
})->group('architecture', 'ledger', 'schema');

it('reconciliation_findings table exists', function (): void {
    expect(Schema::hasTable('reconciliation_findings'))->toBeTrue();
})->group('architecture', 'ledger', 'schema');

it('wallets table has projection cache columns', function (string $column): void {
    expect(Schema::hasColumn('wallets', $column))
        ->toBeTrue("wallets is missing projection cache column: {$column}");
})->with([
    'last_ledger_entry_id',
    'last_projected_at',
])->group('architecture', 'ledger', 'schema');
