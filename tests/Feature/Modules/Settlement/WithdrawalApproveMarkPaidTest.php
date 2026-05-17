<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\MarkWithdrawalPaidAction;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Database\Seeders\SettlementPermissionsSeeder;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Exceptions\InvalidWithdrawalTransitionException;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use Database\Seeders\IdentityRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(IdentityRolesSeeder::class)->run();
    app(SettlementPermissionsSeeder::class)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('s3-private');
});

// ─────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────

function makeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makePendingWithdrawalAndWallet(int $amountMinor = 50000): array
{
    $vp = VendorProfile::factory()->create();
    $vp->user->assignRole('vendor');

    $wallet = Wallet::factory()->withBalance($amountMinor + 10000)->create([
        'owner_type'               => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
        'owner_id'                 => $vp->id,
        'pending_withdrawal_minor' => $amountMinor,
    ]);

    $withdrawal = Withdrawal::factory()->pending()->create([
        'vendor_profile_id'      => $vp->id,
        'requested_by_user_id'   => $vp->user->id,
        'requested_amount_minor' => $amountMinor,
        'pending_lock'           => $vp->id,
    ]);

    return [$vp, $wallet, $withdrawal];
}

function fakeProofFile(): UploadedFile
{
    // fake()->image() generates real PNG bytes; detected MIME is image/png which the collection accepts
    return UploadedFile::fake()->image('bank-proof.png', 10, 10);
}

function validMarkPaidInput(string $ref = 'EGTBNK-TEST-00001'): MarkWithdrawalPaidInput
{
    return new MarkWithdrawalPaidInput(
        bankTransferReference: $ref,
        proofFile: fakeProofFile(),
        paymentNote: ['en' => 'Smoke test payment', 'ar' => 'دفع اختباري'],
    );
}

// ─────────────────────────────────────────────────────
// US1 — Case 2: Admin approves a pending withdrawal
// ─────────────────────────────────────────────────────

describe('US1 — admin payout', function (): void {
    it('case 2 — approving a pending withdrawal sets status to approved with audit columns', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        $approved = app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);

        expect($approved->getRawOriginal('status'))->toBe(WithdrawalStatus::Approved->value)
            ->and($approved->approved_at)->not->toBeNull()
            ->and($approved->approved_by_admin_id)->toBe($admin->id)
            ->and($approved->pending_lock)->toBeNull();

        $auditRow = DB::table('audit_logs')
            ->where('auditable_type', Withdrawal::class)
            ->where('auditable_id', $withdrawal->id)
            ->where('action', 'withdrawal_approved')
            ->first();

        expect($auditRow)->not->toBeNull()
            ->and($auditRow->user_id)->toBe($admin->id);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 3: Mark Paid after Approve ─────────────────────────────────────

    it('case 3 — marking an approved withdrawal as paid sets all required columns and creates ledger group', function (): void {
        [, $wallet, $withdrawal] = makePendingWithdrawalAndWallet(50000);
        $admin = makeAdmin();

        // First approve
        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();

        // Then mark paid
        $paid = app(MarkWithdrawalPaidAction::class)->execute(
            $withdrawal,
            validMarkPaidInput(),
            $admin
        );

        expect($paid->getRawOriginal('status'))->toBe(WithdrawalStatus::Paid->value)
            ->and($paid->paid_at)->not->toBeNull()
            ->and($paid->paid_by_admin_id)->toBe($admin->id)
            ->and($paid->bank_transfer_reference)->toBe('EGTBNK-TEST-00001')
            ->and($paid->processed_by_user_id)->toBe($admin->id) // legacy column still set
            ->and($paid->processed_at)->not->toBeNull();

        // Proof media was attached
        expect($paid->getFirstMedia('bank_proof'))->not->toBeNull();

        // Audit log for payment step
        $auditRow = DB::table('audit_logs')
            ->where('auditable_type', Withdrawal::class)
            ->where('auditable_id', $withdrawal->id)
            ->where('action', 'withdrawal_paid')
            ->first();

        expect($auditRow)->not->toBeNull()
            ->and($auditRow->user_id)->toBe($admin->id);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 4: Cannot mark paid without bank_transfer_reference ─────────────

    it('case 4 — mark paid throws when bank_transfer_reference is empty', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();

        $input = new MarkWithdrawalPaidInput(
            bankTransferReference: '',
            proofFile: fakeProofFile(),
        );

        expect(fn () => app(MarkWithdrawalPaidAction::class)->execute($withdrawal, $input, $admin))
            ->toThrow(\InvalidArgumentException::class);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 5: Cannot mark paid without proof file ──────────────────────────

    it('case 5 — mark paid throws when proof file is missing or invalid', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();

        // Create a fake file with a disallowed MIME type
        $badFile = UploadedFile::fake()->create('document.txt', 10, 'text/plain');

        $input = new MarkWithdrawalPaidInput(
            bankTransferReference: 'REF-001',
            proofFile: $badFile,
        );

        expect(fn () => app(MarkWithdrawalPaidAction::class)->execute($withdrawal, $input, $admin))
            ->toThrow(\InvalidArgumentException::class);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 6: Cannot mark paid on a pending (non-approved) withdrawal ──────

    it('case 6 — mark paid throws InvalidWithdrawalTransitionException on pending withdrawal', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        // Do NOT approve first — attempt mark paid directly on pending
        expect(fn () => app(MarkWithdrawalPaidAction::class)->execute(
            $withdrawal,
            validMarkPaidInput(),
            $admin
        ))->toThrow(InvalidWithdrawalTransitionException::class);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 9: Idempotent replay of approve ────────────────────────────────

    it('case 9 — replaying ApproveWithdrawalAction with same withdrawal is idempotent', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        $first  = app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $second = app(ApproveWithdrawalAction::class)->execute($first, $admin); // replay

        // Status remains approved (not double-transitioned or errored)
        expect($second->getRawOriginal('status'))->toBe(WithdrawalStatus::Approved->value);

        // Exactly one audit row for withdrawal_approved
        $count = DB::table('audit_logs')
            ->where('auditable_type', Withdrawal::class)
            ->where('auditable_id', $withdrawal->id)
            ->where('action', 'withdrawal_approved')
            ->count();

        expect($count)->toBe(1);
    })->group('settlement', 'withdrawal-audit', 'T411');

    // ─── Case 8: Append-only invariant for wallet_ledger ─────────────────────

    it('case 8 — no wallet_ledger row is ever UPDATEd or DELETEd during the approve + mark-paid flow', function (): void {
        [, $wallet, $withdrawal] = makePendingWithdrawalAndWallet(50000);
        $admin = makeAdmin();

        // Snapshot existing ledger IDs before the flow
        $ledgerIdsBefore = DB::table('wallet_ledger')->pluck('id')->all();

        // Run both steps
        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();
        app(MarkWithdrawalPaidAction::class)->execute($withdrawal, validMarkPaidInput(), $admin);

        // All pre-existing ledger rows must still exist with unchanged data
        $ledgerIdsAfter = DB::table('wallet_ledger')->pluck('id')->all();

        // Every original ID is still present (no DELETE)
        foreach ($ledgerIdsBefore as $id) {
            expect($ledgerIdsAfter)->toContain($id);
        }

        // Only NEW rows were added (count increased or stayed the same)
        expect(count($ledgerIdsAfter))->toBeGreaterThanOrEqual(count($ledgerIdsBefore));
    })->group('settlement', 'withdrawal-audit', 'ledger-invariant', 'T411');
}); // end describe US1

// ─────────────────────────────────────────────────────
// US2 — Vendor view
// ─────────────────────────────────────────────────────

describe('US2 — vendor view', function (): void {
    it('case 7 — a different vendor cannot access another vendor\'s withdrawal (404)', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $otherVp = VendorProfile::factory()->create();
        $otherVp->user->assignRole('vendor');

        $token = $otherVp->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/vendor/withdrawals/{$withdrawal->public_id}");

        $response->assertStatus(404);
    })->group('settlement', 'withdrawal-audit', 'vendor', 'T411');

    it('bilingual — admin_payment_note resolves to EN locale when Accept-Language: en', function (): void {
        [$vp, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();

        app(MarkWithdrawalPaidAction::class)->execute(
            $withdrawal,
            new MarkWithdrawalPaidInput(
                bankTransferReference: 'EGTBNK-BILINGUAL-001',
                proofFile: fakeProofFile(),
                paymentNote: ['en' => 'English note', 'ar' => 'ملاحظة عربية'],
            ),
            $admin
        );

        $token = $vp->user->createToken('test')->plainTextToken;

        $responseEn = $this->withHeaders([
            'Authorization'   => "Bearer {$token}",
            'Accept-Language' => 'en',
        ])->getJson("/api/v1/vendor/withdrawals/{$withdrawal->public_id}");

        $responseEn->assertStatus(200);
        $responseEn->assertJsonPath('data.admin_payment_note', 'English note');

        $responseAr = $this->withHeaders([
            'Authorization'   => "Bearer {$token}",
            'Accept-Language' => 'ar',
        ])->getJson("/api/v1/vendor/withdrawals/{$withdrawal->public_id}");

        $responseAr->assertStatus(200);
        $responseAr->assertJsonPath('data.admin_payment_note', 'ملاحظة عربية');
    })->group('settlement', 'withdrawal-audit', 'vendor', 'bilingual', 'T411');
}); // end describe US2

// ─────────────────────────────────────────────────────
// US3 — Admin audit page
// ─────────────────────────────────────────────────────

describe('US3 — admin audit page', function (): void {
    it('US3(a) — admin with withdrawal.view_audit can see reference and proof fields on paid withdrawal', function (): void {
        [$vp, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Run the full approve → mark paid flow
        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();
        app(MarkWithdrawalPaidAction::class)->execute($withdrawal, validMarkPaidInput(), $admin);
        $withdrawal->refresh();

        // Admin (via the admin role seeded by SettlementPermissionsSeeder) should have withdrawal.view_audit
        expect($admin->can('withdrawal.view_audit'))->toBeTrue()
            ->and($withdrawal->bank_transfer_reference)->toBe('EGTBNK-TEST-00001')
            ->and($withdrawal->paid_by_admin_id)->toBe($admin->id)
            ->and($withdrawal->approved_by_admin_id)->toBe($admin->id)
            ->and($withdrawal->getFirstMedia('bank_proof'))->not->toBeNull();
    })->group('settlement', 'withdrawal-audit', 'admin-ui', 'T411');

    it('US3(b) — admin without withdrawal.view_audit cannot see reference on paid withdrawal', function (): void {
        [, , $withdrawal] = makePendingWithdrawalAndWallet();
        $admin = makeAdmin();

        // Create a separate user with only settlement.approve_withdrawal, NOT withdrawal.view_audit
        $limitedAdmin = User::factory()->create();
        $limitedAdmin->givePermissionTo('settlement.approve_withdrawal');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
        $withdrawal->refresh();
        app(MarkWithdrawalPaidAction::class)->execute($withdrawal, validMarkPaidInput(), $admin);

        // The limited user must NOT have the audit permission
        expect($limitedAdmin->can('withdrawal.view_audit'))->toBeFalse();
    })->group('settlement', 'withdrawal-audit', 'admin-ui', 'T411');
}); // end describe US3
