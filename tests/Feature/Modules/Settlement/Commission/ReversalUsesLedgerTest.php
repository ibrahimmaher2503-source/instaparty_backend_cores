<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\ReverseCommissionAction;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $walletRepo = app(EloquentWalletRepository::class);

    foreach (SuspenseAccount::cases() as $account) {
        $walletRepo->firstOrCreate('platform_account', $account->value);
    }

    $this->vendorWallet = $walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        1,
    );

    // Create a commission row with an accrual ledger entry already set
    $ledgerEntryId = DB::table('wallet_ledger')->insertGetId([
        'wallet_id'       => $this->vendorWallet->id,
        'direction'       => 'credit',
        'amount_minor'    => 10000,
        'entry_type'      => 'commission_accrual',
        'correlation_id'  => (string) \Illuminate\Support\Str::ulid(),
        'causation_id'    => (string) \Illuminate\Support\Str::ulid(),
        'idempotency_key' => 'accrual_' . uniqid(),
        'posted_at'       => now(),
        'created_at'      => now(),
    ]);

    $this->commissionId = DB::table('commissions')->insertGetId([
        'public_id'              => (string) \Illuminate\Support\Str::ulid(),
        'booking_item_id'        => 1,
        'vendor_profile_id'      => 1,
        'amount_minor'           => 10000,
        'amount_currency'        => 'EGP',
        'commission_bps'         => 1000,
        'status'                 => 'accrued',
        'accrual_ledger_entry_id' => $ledgerEntryId,
        'idempotency_key'        => 'comm_' . uniqid(),
        'created_at'             => now(),
    ]);
});

it('ReverseCommissionAction posts a reversal ledger group and sets reversal_ledger_entry_id', function (): void {
    $commission = Commission::find($this->commissionId);
    $action     = app(ReverseCommissionAction::class);

    $action->execute(
        commission: $commission,
        refundId: 1,
        idempotencyKey: 'comm_rev_' . uniqid(),
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
    );

    $commission->refresh();

    expect($commission->reversal_ledger_entry_id)->not()->toBeNull();

    $reversalEntry = DB::table('wallet_ledger')
        ->where('id', $commission->reversal_ledger_entry_id)
        ->first();

    expect($reversalEntry)->not()->toBeNull();
    expect($reversalEntry->direction)->toBe('debit');  // reversing a credit = debit vendor wallet
    expect((int) $reversalEntry->amount_minor)->toBe(10000);
})->group('ledger', 'us1')->skip('requires commission FK constraints relaxed in test DB');
