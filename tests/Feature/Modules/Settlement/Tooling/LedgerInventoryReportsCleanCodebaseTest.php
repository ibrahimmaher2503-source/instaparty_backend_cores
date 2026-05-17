<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('ledger:inventory reports zero null-direction entries after posting transactions via LedgerWriter', function (): void {
    $walletRepo   = app(EloquentWalletRepository::class);
    $ledgerWriter = app(LedgerWriter::class);
    $ownerType    = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    $vp     = VendorProfile::factory()->create();
    $wallet = $walletRepo->firstOrCreate($ownerType, $vp->id, 'EGP');

    $ledgerWriter->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'inv_test_' . Str::ulid(),
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

    Artisan::call('ledger:inventory', ['--json' => true]);

    $summary = json_decode(trim(Artisan::output()), true);

    expect($summary)->toBeArray();
    expect($summary['null_direction_entries'])->toBe(0);
    expect($summary['total_entries'])->toBeGreaterThanOrEqual(2);
})->group('us8');
