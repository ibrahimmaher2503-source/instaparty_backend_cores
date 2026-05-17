<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->walletRepo = app(EloquentWalletRepository::class);
    $this->action     = app(PostLedgerTransactionAction::class);

    // Seed the platform_adjustments suspense wallet
    $this->platformWallet = $this->walletRepo->firstOrCreate(
        'platform_account',
        SuspenseAccount::PlatformAdjustments->value,
    );

    $this->vendorWallet = $this->walletRepo->firstOrCreate(
        'App\Modules\Identity\Domain\Models\VendorProfile',
        1,
    );
});

it('posts a balanced 2-entry transaction and creates the expected rows', function (): void {
    $idempotencyKey = 'test_balanced_' . uniqid();

    $input = new PostLedgerTransactionInput(
        kind: TransactionKind::ManualAdjustment,
        currency: 'EGP',
        idempotencyKey: $idempotencyKey,
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
                descriptionKey: 'test.credit',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                direction: LedgerDirection::Debit,
                amountMinor: 10000,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                counterAccountId: 1,
                descriptionKey: 'test.debit',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
        ],
        descriptionKey: 'test.balanced_transaction',
        descriptionParams: [],
        metadata: [],
    );

    $result = $this->action->post($input);

    expect($result->wasIdempotentReplay)->toBeFalse();
    expect($result->groupId)->toBeInt();

    // One transaction group
    expect(\DB::table('ledger_transaction_groups')->where('id', $result->groupId)->count())->toBe(1);

    // Two ledger entries
    expect(\DB::table('wallet_ledger')->where('transaction_group_id', $result->groupId)->count())->toBe(2);

    // Vendor wallet balance advanced
    $vendorWallet = Wallet::find($this->vendorWallet->id);
    expect($vendorWallet->balance_minor)->toBe(10000);

    // last_ledger_entry_id is set
    expect($vendorWallet->last_ledger_entry_id)->not()->toBeNull();
})->group('ledger', 'us1');

it('returns wasIdempotentReplay=true on a second identical post', function (): void {
    $key = 'idempotency_replay_' . uniqid();

    $input = new PostLedgerTransactionInput(
        kind: TransactionKind::ManualAdjustment,
        currency: 'EGP',
        idempotencyKey: $key,
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: (string) \Illuminate\Support\Str::ulid(),
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                walletOwnerId: 1,
                direction: LedgerDirection::Credit,
                amountMinor: 5000,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformAdjustments->value,
                descriptionKey: 'test.credit',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                direction: LedgerDirection::Debit,
                amountMinor: 5000,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                counterAccountId: 1,
                descriptionKey: 'test.debit',
                descriptionParams: [],
                relatedEntityType: null,
                relatedEntityId: null,
            ),
        ],
        descriptionKey: 'test.replay',
        descriptionParams: [],
        metadata: [],
    );

    $first  = $this->action->post($input);
    $second = $this->action->post($input);

    expect($second->wasIdempotentReplay)->toBeTrue();
    expect($second->groupId)->toBe($first->groupId);
    expect(\DB::table('wallet_ledger')->where('transaction_group_id', $first->groupId)->count())->toBe(2);
})->group('ledger', 'us1');
