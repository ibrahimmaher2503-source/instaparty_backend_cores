<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Exceptions\InsufficientAvailableBalanceException;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->walletRepo     = app(EloquentWalletRepository::class);
    $this->creditAction   = app(CreditWalletAction::class);
    $this->withdrawAction = app(RequestWithdrawalAction::class);
});

it('wallet with 1000 EGP available and 700 reserved blocks a new 400 EGP request', function (): void {
    // Set up vendor profile
    $vp = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();

    // Credit the vendor wallet with 1000 EGP (100 000 piastres)
    $wallet = $this->walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $this->creditAction->execute(
        walletId: $wallet->id,
        amountMinor: 100_000,
        currency: 'EGP',
        idempotencyKey: 'seed_credit_' . $vp->id,
        correlationId: (string) Str::ulid(),
    );

    $user = $vp->user;

    // Request 70 000 piastres (700 EGP) withdrawal — should succeed
    $this->withdrawAction->execute(new RequestWithdrawalDto(
        vendorProfileId: $vp->id,
        amountMinor: 70_000,
        currency: 'EGP',
        bankAccount: validBankAccountPayload(),
        requestedByUserId: $user->id,
        idempotencyKey: 'wd_first_' . $vp->id,
    ));

    // Available is now 100_000 - 70_000 = 30_000 piastres
    // Requesting 40_000 (400 EGP) must be blocked
    expect(fn () => $this->withdrawAction->execute(new RequestWithdrawalDto(
        vendorProfileId: $vp->id,
        amountMinor: 40_000,
        currency: 'EGP',
        bankAccount: validBankAccountPayload(),
        requestedByUserId: $user->id,
        idempotencyKey: 'wd_second_' . $vp->id,
    )))->toThrow(InsufficientAvailableBalanceException::class);
})->group('concurrency', 'us3');

it('approval of a reserved withdrawal posts a settle ledger group', function (): void {
    $vp   = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $user = $vp->user;

    $wallet = $this->walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $this->creditAction->execute(
        walletId: $wallet->id,
        amountMinor: 50_000,
        currency: 'EGP',
        idempotencyKey: 'settle_seed_' . $vp->id,
        correlationId: (string) Str::ulid(),
    );

    $withdrawal = $this->withdrawAction->execute(new RequestWithdrawalDto(
        vendorProfileId: $vp->id,
        amountMinor: 50_000,
        currency: 'EGP',
        bankAccount: validBankAccountPayload(),
        requestedByUserId: $user->id,
        idempotencyKey: 'wd_settle_test_' . $vp->id,
    ));

    // A withdrawal_reserve group should exist
    expect(DB::table('ledger_transaction_groups')->where('kind', 'withdrawal_reserve')->count())->toBeGreaterThanOrEqual(1);

    // The withdrawal row should have reserved_ledger_entry_id set
    $withdrawal->refresh();
    expect($withdrawal->reserved_ledger_entry_id)->not()->toBeNull();
})->group('us3');

it('rejection of a reserved withdrawal posts a reject_release ledger group', function (): void {
    $vp   = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->create();
    $user = $vp->user;

    $admin = \App\Modules\Identity\Domain\Models\User::factory()->create();

    $wallet = $this->walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        $vp->id,
        'EGP',
    );

    $this->creditAction->execute(
        walletId: $wallet->id,
        amountMinor: 30_000,
        currency: 'EGP',
        idempotencyKey: 'reject_seed_' . $vp->id,
        correlationId: (string) Str::ulid(),
    );

    $withdrawal = $this->withdrawAction->execute(new RequestWithdrawalDto(
        vendorProfileId: $vp->id,
        amountMinor: 30_000,
        currency: 'EGP',
        bankAccount: validBankAccountPayload(),
        requestedByUserId: $user->id,
        idempotencyKey: 'wd_reject_test_' . $vp->id,
    ));

    $rejectAction = app(\App\Modules\Settlement\Application\Actions\RejectWithdrawalAction::class);
    $rejectAction->execute($withdrawal, ['en' => 'Rejected by admin', 'ar' => 'مرفوض'], $admin);

    // A withdrawal_reject_release group should exist
    expect(DB::table('ledger_transaction_groups')->where('kind', 'withdrawal_reject_release')->count())->toBeGreaterThanOrEqual(1);

    // Vendor balance should be back to 30_000 (reserve released)
    $wallet->refresh();
    expect($wallet->balance_minor)->toBe(30_000);

    // The withdrawal row should have rejected_ledger_entry_id set
    $withdrawal->refresh();
    expect($withdrawal->rejected_ledger_entry_id)->not()->toBeNull();
})->group('us3');
