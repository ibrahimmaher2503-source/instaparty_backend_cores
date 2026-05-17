<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─────────────────────────────────────────────────────
// T411 — RejectWithdrawalAction full flow
// ─────────────────────────────────────────────────────

it('rejecting a withdrawal sets status to rejected with EN+AR reason', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    $wallet = Wallet::factory()->withBalance(100000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 50000,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
        'requested_amount_minor' => 50000,
        'requested_amount_currency' => 'EGP',
        'pending_lock' => $vp->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $reason = ['en' => 'Insufficient documentation.', 'ar' => 'المستندات غير كافية.'];

    $rejected = app(RejectWithdrawalAction::class)->execute($withdrawal, $reason, $admin);

    expect($rejected->status)->toBe(WithdrawalStatus::Rejected)
        ->and($rejected->rejected_reason)->toBe($reason)
        ->and($rejected->processed_by_user_id)->toBe($admin->id)
        ->and($rejected->processed_at)->not->toBeNull()
        ->and($rejected->pending_lock)->toBeNull();
})->group('settlement', 'admin', 'filament', 'T411');

it('rejecting a withdrawal releases pending_withdrawal_minor', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    $wallet = Wallet::factory()->withBalance(100000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 50000,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
        'requested_amount_minor' => 50000,
        'requested_amount_currency' => 'EGP',
        'pending_lock' => $vp->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    app(RejectWithdrawalAction::class)->execute(
        $withdrawal,
        ['en' => 'Rejected.', 'ar' => 'مرفوض.'],
        $admin,
    );

    expect($wallet->fresh()->pending_withdrawal_minor)->toBe(0);
})->group('settlement', 'admin', 'filament', 'T411');

it('rejecting a withdrawal does NOT create a ledger debit entry', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    Wallet::factory()->withBalance(100000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 50000,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
        'requested_amount_minor' => 50000,
        'requested_amount_currency' => 'EGP',
        'pending_lock' => $vp->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $ledgerCountBefore = WalletLedgerEntry::count();

    app(RejectWithdrawalAction::class)->execute(
        $withdrawal,
        ['en' => 'Rejected.', 'ar' => 'مرفوض.'],
        $admin,
    );

    // No new ledger entry — balance is untouched
    expect(WalletLedgerEntry::count())->toBe($ledgerCountBefore);
})->group('settlement', 'admin', 'filament', 'T411');

it('rejecting a withdrawal appends an audit_log row', function (): void {
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    Wallet::factory()->withBalance(100000)->create([
        'owner_type' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id' => $vp->id,
        'pending_withdrawal_minor' => 50000,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id' => $vp->id,
        'requested_by_user_id' => $vp->user->id,
        'requested_amount_minor' => 50000,
        'requested_amount_currency' => 'EGP',
        'pending_lock' => $vp->id,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    app(RejectWithdrawalAction::class)->execute(
        $withdrawal,
        ['en' => 'Rejected.', 'ar' => 'مرفوض.'],
        $admin,
    );

    $auditRow = DB::table('audit_logs')
        ->where('auditable_type', Withdrawal::class)
        ->where('auditable_id', $withdrawal->id)
        ->where('action', 'withdrawal_rejected')
        ->first();

    expect($auditRow)->not->toBeNull()
        ->and($auditRow->user_id)->toBe($admin->id);
})->group('settlement', 'admin', 'filament', 'T411');
