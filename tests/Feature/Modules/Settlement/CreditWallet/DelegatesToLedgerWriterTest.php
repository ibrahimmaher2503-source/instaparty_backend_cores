<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $walletRepo->firstOrCreate('platform_account', SuspenseAccount::PlatformAdjustments->value);
    $this->vendorWallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        1,
    );
});

it('CreditWalletAction produces exactly one transaction group with one credit entry', function (): void {
    $action = app(CreditWalletAction::class);

    $action->execute(
        walletId: $this->vendorWallet->id,
        amountMinor: 20000,
        currency: 'EGP',
        idempotencyKey: 'credit_test_' . uniqid(),
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
    );

    $groups = \DB::table('ledger_transaction_groups')->get();
    expect($groups)->toHaveCount(1);

    $entries = \DB::table('wallet_ledger')
        ->where('wallet_id', $this->vendorWallet->id)
        ->get();

    $creditEntries = $entries->where('direction', 'credit');
    expect($creditEntries)->toHaveCount(1);
    expect((int) $creditEntries->first()->amount_minor)->toBe(20000);
})->group('ledger', 'us1');

it('CreditWalletAction is idempotent — second call with same key produces no new rows', function (): void {
    $action         = app(CreditWalletAction::class);
    $idempotencyKey = 'credit_idempotent_' . uniqid();
    $params         = [
        'walletId'       => $this->vendorWallet->id,
        'amountMinor'    => 5000,
        'currency'       => 'EGP',
        'idempotencyKey' => $idempotencyKey,
        'correlationId'  => (string) \Illuminate\Support\Str::ulid(),
        'causationId'    => (string) \Illuminate\Support\Str::ulid(),
    ];

    $action->execute(...$params);
    $countAfterFirst = \DB::table('wallet_ledger')->count();

    $action->execute(...$params);
    expect(\DB::table('wallet_ledger')->count())->toBe($countAfterFirst);
})->group('ledger', 'us1');
