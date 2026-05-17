<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $walletRepo = app(EloquentWalletRepository::class);

    // Create all platform suspense wallets required for commission accrual
    foreach (SuspenseAccount::cases() as $account) {
        $walletRepo->firstOrCreate('platform_account', $account->value);
    }

    $this->vendorWallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        1,
    );
});

it('CalculateCommissionAction creates a commission row with accrual_ledger_entry_id set', function (): void {
    // This test requires a booking item — we use a factory or direct row insertion
    // to avoid coupling to Booking module internals.
    $bookingItemId = \DB::table('booking_items')->insertGetId([
        'public_id'        => (string) \Illuminate\Support\Str::ulid(),
        'booking_id'       => 1,  // will be skipped if FK constraint not enforced in test DB
        'booking_vendor_id' => 1,
        'service_id'       => 1,
        'product_type'     => 'rental',
        'item_status'      => 'confirmed',
        'quantity'         => 1,
        'unit_price_minor' => 100000,
        'total_price_minor' => 100000,
        'total_currency'   => 'EGP',
        'commission_bps'   => 1000,  // 10%
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $action     = app(CalculateCommissionAction::class);
    $commission = $action->execute(bookingItemId: $bookingItemId);

    expect($commission)->toBeInstanceOf(Commission::class);
    expect($commission->accrual_ledger_entry_id)->not()->toBeNull();

    // Verify the ledger entry exists
    $ledgerEntry = \DB::table('wallet_ledger')
        ->where('id', $commission->accrual_ledger_entry_id)
        ->first();

    expect($ledgerEntry)->not()->toBeNull();
    expect($ledgerEntry->direction)->toBe('credit');  // vendor wallet credited with commission
})->group('ledger', 'us1')->skip('requires booking_items foreign keys relaxed in test DB');
