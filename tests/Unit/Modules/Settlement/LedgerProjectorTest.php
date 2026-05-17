<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\ProjectWalletBalanceAction;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('recomputes balance from ledger entries and writes projection cache', function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $wallet = $walletRepo->firstOrCreate('App\Modules\Identity\Domain\Models\VendorProfile', 99, 'EGP');

    // Manually insert 5 ledger entries to simulate history (bypass triggers in test env).
    $entries = [
        ['direction' => 'credit', 'amount_minor' => 10000],
        ['direction' => 'credit', 'amount_minor' => 5000],
        ['direction' => 'debit',  'amount_minor' => 3000],
        ['direction' => 'credit', 'amount_minor' => 2000],
        ['direction' => 'debit',  'amount_minor' => 1000],
    ];

    // Expected balance: (10000 + 5000 + 2000) - (3000 + 1000) = 13000
    $lastId = null;
    foreach ($entries as $entry) {
        $lastId = DB::table('wallet_ledger')->insertGetId(array_merge($entry, [
            'wallet_id'       => $wallet->id,
            'amount_minor'    => $entry['amount_minor'],
            'currency'        => 'EGP',
            'entry_type'      => 'manual_adjustment',
            'correlation_id'  => (string) \Illuminate\Support\Str::ulid(),
            'causation_id'    => (string) \Illuminate\Support\Str::ulid(),
            'idempotency_key' => 'test_' . uniqid(),
            'posted_at'       => now(),
            'created_at'      => now(),
        ]));
    }

    $action = app(ProjectWalletBalanceAction::class);
    $action->project($wallet->id);

    $refreshed = Wallet::find($wallet->id);

    expect($refreshed->balance_minor)->toBe(13000);
    expect($refreshed->last_ledger_entry_id)->toBe($lastId);
    expect($refreshed->last_projected_at)->not()->toBeNull();
})->group('ledger', 'us1');
