<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Exceptions\UnbalancedTransactionException;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $walletRepo = app(EloquentWalletRepository::class);
    $walletRepo->firstOrCreate('platform_account', SuspenseAccount::PlatformAdjustments->value);
    $walletRepo->firstOrCreate('App\Modules\Identity\Domain\Models\VendorProfile', 1);

    $this->action = app(PostLedgerTransactionAction::class);
});

it('throws UnbalancedTransactionException when debits do not equal credits', function (): void {
    $input = new PostLedgerTransactionInput(
        kind: TransactionKind::ManualAdjustment,
        currency: 'EGP',
        idempotencyKey: 'unbalanced_' . uniqid(),
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                walletOwnerId: 1,
                direction: LedgerDirection::Credit,
                amountMinor: 10000,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformAdjustments->value,
                descriptionKey: 'test',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                direction: LedgerDirection::Debit,
                amountMinor: 9000,  // intentionally wrong — 1000 imbalance
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                counterAccountId: 1,
                descriptionKey: 'test',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
        ],
        descriptionKey: 'test',
        descriptionParams: [],
        metadata: [],
    );

    expect(fn () => $this->action->post($input))
        ->toThrow(UnbalancedTransactionException::class);
})->group('ledger', 'us1');

it('creates no rows when transaction is unbalanced', function (): void {
    $before = \DB::table('ledger_transaction_groups')->count();

    $input = new PostLedgerTransactionInput(
        kind: TransactionKind::ManualAdjustment,
        currency: 'EGP',
        idempotencyKey: 'no_rows_' . uniqid(),
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                walletOwnerId: 1,
                direction: LedgerDirection::Credit,
                amountMinor: 500,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformAdjustments->value,
                descriptionKey: 'test',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
            // Missing offsetting debit — net imbalance of 500
        ],
        descriptionKey: 'test',
        descriptionParams: [],
        metadata: [],
    );

    try {
        $this->action->post($input);
    } catch (UnbalancedTransactionException) {
    }

    expect(\DB::table('ledger_transaction_groups')->count())->toBe($before);
})->group('ledger', 'us1');
