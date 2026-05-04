<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\ApproveAndMarkWithdrawalPaidAction;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────

function makeSettlementAdminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makePendingWithdrawalWithWallet(int $amountMinor = 50000): array
{
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    $wallet = Wallet::factory()->withBalance($amountMinor + 10000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => $amountMinor,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
        'requested_amount_minor' => $amountMinor,
        'requested_amount_currency' => 'EGP',
        'pending_lock' => $vp->id,
    ]);

    return [$vp, $wallet, $withdrawal];
}

// ─────────────────────────────────────────────────────
// T410 — Withdrawals queue only shows pending records
// ─────────────────────────────────────────────────────

it('WithdrawalsQueueResource query only returns pending withdrawals', function (): void {
    $vp = VendorProfile::factory()->create();

    Withdrawal::factory()->pending()->create(['vendor_profile_id' => $vp->id, 'requested_by_user_id' => $vp->user->id]);
    Withdrawal::factory()->paid()->create(['vendor_profile_id' => $vp->id, 'requested_by_user_id' => $vp->user->id, 'pending_lock' => null]);
    Withdrawal::factory()->rejected()->create(['vendor_profile_id' => $vp->id, 'requested_by_user_id' => $vp->user->id, 'pending_lock' => null]);

    $results = WithdrawalsQueueResource::getEloquentQuery()->get();

    expect($results)->toHaveCount(1);

    /** @var Withdrawal $record */
    $record = $results->first();
    expect($record->status)->toBe(WithdrawalStatus::Pending);
})->group('settlement', 'admin', 'filament', 'T410');

// ─────────────────────────────────────────────────────
// T410 — ApproveAndMarkWithdrawalPaidAction full flow
// ─────────────────────────────────────────────────────

it('approving a withdrawal sets status to paid and creates a withdrawal_debit ledger entry', function (): void {
    [, $wallet, $withdrawal] = makePendingWithdrawalWithWallet(50000);
    $admin = makeSettlementAdminUser();

    $proof = UploadedFile::fake()->create('bank-proof.pdf', 100, 'application/pdf');

    $approved = app(ApproveAndMarkWithdrawalPaidAction::class)->execute($withdrawal, $proof, $admin);

    expect($approved->status)->toBe(WithdrawalStatus::Paid)
        ->and($approved->paid_at)->not->toBeNull()
        ->and($approved->processed_at)->not->toBeNull()
        ->and($approved->processed_by_user_id)->toBe($admin->id)
        ->and($approved->paid_amount_minor)->toBe(50000);

    // Wallet debit ledger entry created
    $ledger = WalletLedgerEntry::where('wallet_id', $wallet->id)
        ->where('entry_type', LedgerEntryType::WithdrawalDebit->value)
        ->first();

    expect($ledger)->not->toBeNull()
        ->and(abs($ledger->amount_minor))->toBe(50000);
})->group('settlement', 'admin', 'filament', 'T410');

it('approving a withdrawal decrements pending_withdrawal_minor on the wallet', function (): void {
    [, $wallet, $withdrawal] = makePendingWithdrawalWithWallet(50000);
    $admin = makeSettlementAdminUser();

    $pendingBefore = $wallet->fresh()->pending_withdrawal_minor;

    $proof = UploadedFile::fake()->create('bank-proof.pdf', 100, 'application/pdf');
    app(ApproveAndMarkWithdrawalPaidAction::class)->execute($withdrawal, $proof, $admin);

    expect($wallet->fresh()->pending_withdrawal_minor)->toBe($pendingBefore - 50000);
})->group('settlement', 'admin', 'filament', 'T410');

it('approving a withdrawal appends an audit_log row', function (): void {
    [, , $withdrawal] = makePendingWithdrawalWithWallet(50000);
    $admin = makeSettlementAdminUser();

    $proof = UploadedFile::fake()->create('bank-proof.pdf', 100, 'application/pdf');
    app(ApproveAndMarkWithdrawalPaidAction::class)->execute($withdrawal, $proof, $admin);

    $auditRow = DB::table('audit_logs')
        ->where('auditable_type', Withdrawal::class)
        ->where('auditable_id', $withdrawal->id)
        ->where('action', 'withdrawal_paid')
        ->first();

    expect($auditRow)->not->toBeNull()
        ->and($auditRow->user_id)->toBe($admin->id);
})->group('settlement', 'admin', 'filament', 'T410');

// ─────────────────────────────────────────────────────
// T412 — Admin permission check
// ─────────────────────────────────────────────────────

it('a vendor user does not have the approve_withdrawal permission', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    expect($vp->user->can('approve_withdrawal'))->toBeFalse();
})->group('settlement', 'admin', 'filament', 'T412');

it('an admin user has the approve_withdrawal permission', function (): void {
    $admin = makeSettlementAdminUser();

    expect($admin->can('approve_withdrawal'))->toBeTrue();
})->group('settlement', 'admin', 'filament', 'T412');
