<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\DebitWalletAction;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $walletRepo->firstOrCreate('platform_account', SuspenseAccount::PlatformAdjustments->value);

    // Pre-credit the vendor wallet so a debit is valid
    $this->vendorWallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        2,
    );

    // Directly set balance so DebitWalletAction won't throw insufficient funds
    \DB::table('wallets')->where('id', $this->vendorWallet->id)->update(['balance_minor' => 50000]);
});

it('DebitWalletAction produces exactly one transaction group with one debit entry', function (): void {
    $action = app(DebitWalletAction::class);

    $action->execute(
        walletId: $this->vendorWallet->id,
        amountMinor: 15000,
        currency: 'EGP',
        idempotencyKey: 'debit_test_' . uniqid(),
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
    );

    $groups = \DB::table('ledger_transaction_groups')->get();
    expect($groups)->toHaveCount(1);

    $entries = \DB::table('wallet_ledger')
        ->where('wallet_id', $this->vendorWallet->id)
        ->get();

    $debitEntries = $entries->where('direction', 'debit');
    expect($debitEntries)->toHaveCount(1);
    expect((int) $debitEntries->first()->amount_minor)->toBe(15000);
})->group('ledger', 'us1');
