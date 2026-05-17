<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\CreateFinancialSnapshotAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('snapshot available_minor and pending_minor reproduce the ledger-projected balance', function (): void {
    $walletRepo     = app(EloquentWalletRepository::class);
    $ledgerWriter   = app(LedgerWriter::class);
    $ledgerRepo     = app(EloquentLedgerRepository::class);
    $snapshotAction = app(CreateFinancialSnapshotAction::class);
    $ownerType      = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'snap_balance_' . Str::ulid(),
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Credit,
                amountMinor: 80_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformCommissionRealised->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformCommissionRealised->value,
                direction: LedgerDirection::Debit,
                amountMinor: 80_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    $projection = $ledgerRepo->projectionFromLedger($wallet->id);
    $snapshot   = $snapshotAction->execute($wallet->id);

    expect($snapshot)->not()->toBeNull("Snapshot must be created when ledger has entries");
    expect($snapshot->wasRecentlyCreated)->toBeTrue();
    expect($snapshot->available_minor)->toBe($projection->balanceMinor);
    expect($snapshot->pending_minor)->toBe($projection->pendingWithdrawalMinor);
    expect($snapshot->as_of_ledger_entry_id)->toBe($projection->lastLedgerEntryId);
    expect($snapshot->currency)->toBe('EGP');
    expect($snapshot->checksum)->toHaveLength(64);

    // Projection cache on the wallet itself must also match.
    $wallet->refresh();
    expect($snapshot->available_minor)->toBe($wallet->balance_minor);
})->group('us9', 'snapshots');

it('snapshot is idempotent for the same wallet on the same calendar day', function (): void {
    $walletRepo     = app(EloquentWalletRepository::class);
    $ledgerWriter   = app(LedgerWriter::class);
    $snapshotAction = app(CreateFinancialSnapshotAction::class);
    $ownerType      = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'snap_idem_' . Str::ulid(),
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Credit,
                amountMinor: 20_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformCommissionRealised->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformCommissionRealised->value,
                direction: LedgerDirection::Debit,
                amountMinor: 20_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    $first  = $snapshotAction->execute($wallet->id);
    $second = $snapshotAction->execute($wallet->id);

    expect($first)->not()->toBeNull();
    expect($second)->not()->toBeNull();
    expect($first->id)->toBe($second->id);
    expect($second->wasRecentlyCreated)->toBeFalse();
})->group('us9', 'snapshots');

it('returns null when the wallet has no ledger entries', function (): void {
    $walletRepo     = app(EloquentWalletRepository::class);
    $snapshotAction = app(CreateFinancialSnapshotAction::class);
    $ownerType      = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $snapshot = $snapshotAction->execute($wallet->id);

    expect($snapshot)->toBeNull();
})->group('us9', 'snapshots');
