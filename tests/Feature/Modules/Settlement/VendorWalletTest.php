<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\CreditWalletAction;
use App\Modules\Settlement\Application\Actions\DebitWalletAction;
use App\Modules\Settlement\Application\Services\WalletQueryService;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

const VENDOR_OWNER_TYPE = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────────────────────────────
// balance_minor = sum of ledger credits
// ─────────────────────────────────────────────────────────────────────────────

it('balance_minor reflects cumulative credits from CreditWalletAction', function (): void {
    $vp = VendorProfile::factory()->create();

    app(CreditWalletAction::class)->execute(
        ownerType: VENDOR_OWNER_TYPE,
        ownerId: $vp->id,
        amountMinor: 30000,
        currency: 'EGP',
        entryType: LedgerEntryType::CommissionCredit,
    );

    app(CreditWalletAction::class)->execute(
        ownerType: VENDOR_OWNER_TYPE,
        ownerId: $vp->id,
        amountMinor: 20000,
        currency: 'EGP',
        entryType: LedgerEntryType::CommissionCredit,
    );

    $balance = app(WalletQueryService::class)->balance($vp->id);

    expect($balance['balance_minor'])->toBe(50000);
    expect($balance['totals']['credits_minor'])->toBe(50000);
})->group('settlement', 'wallet', 'balance');

it('balance_minor decreases after DebitWalletAction', function (): void {
    $vp = VendorProfile::factory()->create();

    app(CreditWalletAction::class)->execute(
        ownerType: VENDOR_OWNER_TYPE,
        ownerId: $vp->id,
        amountMinor: 60000,
        currency: 'EGP',
        entryType: LedgerEntryType::CommissionCredit,
    );

    app(DebitWalletAction::class)->execute(
        ownerType: VENDOR_OWNER_TYPE,
        ownerId: $vp->id,
        amountMinor: 10000,
        currency: 'EGP',
        entryType: LedgerEntryType::RefundDebit,
    );

    $balance = app(WalletQueryService::class)->balance($vp->id);

    expect($balance['balance_minor'])->toBe(50000);
    expect($balance['totals']['debits_minor'])->toBe(10000);
})->group('settlement', 'wallet', 'balance');

// ─────────────────────────────────────────────────────────────────────────────
// available_minor = balance_minor - pending_withdrawal_minor
// ─────────────────────────────────────────────────────────────────────────────

it('available_minor equals balance when no pending withdrawal exists', function (): void {
    $vp = VendorProfile::factory()->create();

    Wallet::factory()->withBalance(100000)->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 0,
    ]);

    $balance = app(WalletQueryService::class)->balance($vp->id);

    expect($balance['available_minor'])->toBe(100000);
})->group('settlement', 'wallet', 'available');

it('available_minor equals balance minus pending_withdrawal_minor', function (): void {
    $vp = VendorProfile::factory()->create();

    Wallet::factory()->withBalance(100000)->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 30000,
    ]);

    $balance = app(WalletQueryService::class)->balance($vp->id);

    expect($balance['balance_minor'])->toBe(100000);
    expect($balance['pending_withdrawal_minor'])->toBe(30000);
    expect($balance['available_minor'])->toBe(70000);
})->group('settlement', 'wallet', 'available');

it('available_minor is zero when pending_withdrawal_minor equals balance', function (): void {
    $vp = VendorProfile::factory()->create();

    Wallet::factory()->withBalance(50000)->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 50000,
    ]);

    $balance = app(WalletQueryService::class)->balance($vp->id);

    expect($balance['available_minor'])->toBe(0);
})->group('settlement', 'wallet', 'available');

// ─────────────────────────────────────────────────────────────────────────────
// Cross-vendor isolation
// ─────────────────────────────────────────────────────────────────────────────

it('each vendor only sees their own balance', function (): void {
    $vpA = VendorProfile::factory()->create();
    $vpB = VendorProfile::factory()->create();

    Wallet::factory()->withBalance(80000)->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vpA->id,
    ]);
    Wallet::factory()->withBalance(25000)->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vpB->id,
    ]);

    $balanceA = app(WalletQueryService::class)->balance($vpA->id);
    $balanceB = app(WalletQueryService::class)->balance($vpB->id);

    expect($balanceA['balance_minor'])->toBe(80000);
    expect($balanceB['balance_minor'])->toBe(25000);
})->group('settlement', 'wallet', 'isolation');

it('ledger entries from one vendor do not appear in another vendor\'s ledger', function (): void {
    $vpA = VendorProfile::factory()->create();
    $vpB = VendorProfile::factory()->create();

    $walletA = Wallet::factory()->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vpA->id,
    ]);
    $walletB = Wallet::factory()->create([
        'owner_type' => VENDOR_OWNER_TYPE,
        'owner_id' => $vpB->id,
    ]);

    WalletLedgerEntry::factory()->commissionCredit()->count(3)->create(['wallet_id' => $walletA->id]);
    WalletLedgerEntry::factory()->commissionCredit()->count(1)->create(['wallet_id' => $walletB->id]);

    $ledgerA = app(WalletQueryService::class)->ledger($vpA->id);
    $ledgerB = app(WalletQueryService::class)->ledger($vpB->id);

    expect($ledgerA->count())->toBe(3);
    expect($ledgerB->count())->toBe(1);
})->group('settlement', 'wallet', 'isolation');
