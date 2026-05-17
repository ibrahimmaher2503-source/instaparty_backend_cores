<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Application\Actions\SettlementRunAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('triggering reconciliation after a successful settlement run produces zero findings', function (): void {
    $walletRepo  = app(EloquentWalletRepository::class);
    $ledgerWriter = app(LedgerWriter::class);
    $ownerType   = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $correlationId = (string) Str::ulid();

    // ── 1. Post a commission_accrual group so the vendor wallet has a real balance ──
    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'test_commission_' . Str::ulid(),
        correlationId: $correlationId,
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Credit,
                amountMinor: 100_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformCommissionRealised->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformCommissionRealised->value,
                direction: LedgerDirection::Debit,
                amountMinor: 100_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    // ── 2. Post a withdrawal_reserve group (vendor debit → platform credit) ────
    $reserveResult = $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::WithdrawalReserve,
        currency: 'EGP',
        idempotencyKey: 'test_reserve_' . Str::ulid(),
        correlationId: $correlationId,
        causationId: null,
        initiatorType: 'vendor',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Debit,
                amountMinor: 50_000,
                entryType: LedgerEntryType::WithdrawalReserve,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformWithdrawalPayable->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformWithdrawalPayable->value,
                direction: LedgerDirection::Credit,
                amountMinor: 50_000,
                entryType: LedgerEntryType::WithdrawalReserve,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    // Get the vendor-side reserve entry id
    $reserveEntryId = DB::table('wallet_ledger')
        ->where('transaction_group_id', $reserveResult->groupId)
        ->where('wallet_id', $wallet->id)
        ->value('id');

    // ── 3. Create an approved withdrawal linked to the reserve entry ──────────
    $testWithdrawal = Withdrawal::factory()->approved()->create([
        'vendor_profile_id'         => $vp->id,
        'requested_amount_minor'    => 50_000,
        'requested_amount_currency' => 'EGP',
        'reserved_ledger_entry_id'  => $reserveEntryId,
    ]);

    // ── 4. Run the settlement batch ───────────────────────────────────────────
    $settlementResult = app(SettlementRunAction::class)->execute();

    expect($settlementResult->settled)->toBe(1);
    expect($settlementResult->failed)->toBe(0);
    expect($testWithdrawal->fresh()->status)->toBeInstanceOf(\App\Modules\Settlement\Domain\States\WithdrawalStatus\PaidState::class);

    // ── 5. Run reconciliation for the vendor wallet ───────────────────────────
    $wallet->refresh();

    $reconInput = new RunReconciliationInput(
        scopeType: 'wallet',
        scopeParams: ['wallet_id' => $wallet->id],
        triggerKind: 'manual',
        triggeredByUserId: null,
        idempotencyKey: null,
        correlationId: (string) Str::ulid(),
    );

    $reconResult = app(RunReconciliationAction::class)->execute($reconInput);

    // ── 6. Assert no findings ─────────────────────────────────────────────────
    $findings = ReconciliationFinding::where('reconciliation_run_id', $reconResult->runId)->get();

    expect($findings)->toHaveCount(0, 'Reconciliation should produce zero findings after a clean settlement run');
})->group('us7');
