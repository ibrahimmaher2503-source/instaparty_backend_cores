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
use App\Modules\Settlement\Infrastructure\Repositories\EloquentSnapshotRepository;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('historicalAsOf returns the balance the wallet had at a past point in time', function (): void {
    $walletRepo     = app(EloquentWalletRepository::class);
    $ledgerWriter   = app(LedgerWriter::class);
    $snapshotAction = app(CreateFinancialSnapshotAction::class);
    $snapshotRepo   = app(EloquentSnapshotRepository::class);
    $ownerType      = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $threeWeeksAgo = now()->subWeeks(3);

    // Post a 50,000-minor credit — this will be captured in the "old" snapshot.
    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'hist_old_' . Str::ulid(),
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Credit,
                amountMinor: 50_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformCommissionRealised->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformCommissionRealised->value,
                direction: LedgerDirection::Debit,
                amountMinor: 50_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    // Simulate a snapshot taken 3 weeks ago (when balance was 50,000).
    $snapshotAction->execute($wallet->id, $threeWeeksAgo);

    // Post an additional 30,000 credit today.
    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'hist_today_' . Str::ulid(),
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: $ownerType,
                walletOwnerId: $vp->id,
                direction: LedgerDirection::Credit,
                amountMinor: 30_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformCommissionRealised->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformCommissionRealised->value,
                direction: LedgerDirection::Debit,
                amountMinor: 30_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: $ownerType,
                counterAccountId: $vp->id,
            ),
        ],
    ));

    // Create today's snapshot (current balance 80,000).
    $snapshotAction->execute($wallet->id, now());

    // Historical read: balance at 3-weeks-ago + 1 hour should be 50,000.
    $historical = $snapshotRepo->historicalAsOf($wallet->id, $threeWeeksAgo->copy()->addHour());

    expect($historical)->not()->toBeNull();
    expect($historical->available_minor)->toBe(50_000);

    // Current snapshot: balance should be 80,000.
    $latest = $snapshotRepo->latestFor($wallet->id);
    expect($latest)->not()->toBeNull();
    expect($latest->available_minor)->toBe(80_000);
})->group('us9', 'snapshots');

it('historicalAsOf returns null when no snapshot predates the requested time', function (): void {
    $walletRepo     = app(EloquentWalletRepository::class);
    $snapshotRepo   = app(EloquentSnapshotRepository::class);
    $ownerType      = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $result = $snapshotRepo->historicalAsOf($wallet->id, now()->subYear());

    expect($result)->toBeNull();
})->group('us9', 'snapshots');
