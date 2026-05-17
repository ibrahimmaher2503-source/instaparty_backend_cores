<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->walletRepo = app(EloquentWalletRepository::class);
    $this->action     = app(PostLedgerTransactionAction::class);

    $this->walletRepo->firstOrCreate('platform_account', SuspenseAccount::PlatformAdjustments->value);
    $this->walletRepo->firstOrCreate('App\Modules\Identity\Domain\Models\VendorProfile', 1);
});

function buildTestInput(string $key, TransactionKind $kind = TransactionKind::ManualAdjustment, int $amount = 1000): PostLedgerTransactionInput
{
    return new PostLedgerTransactionInput(
        kind: $kind,
        currency: 'EGP',
        idempotencyKey: $key,
        correlationId: (string) \Illuminate\Support\Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                walletOwnerId: 1,
                direction: LedgerDirection::Credit,
                amountMinor: $amount,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformAdjustments->value,
            ),
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
                direction: LedgerDirection::Debit,
                amountMinor: $amount,
                entryType: LedgerEntryType::ManualAdjustment,
                counterAccountType: 'App\Modules\Identity\Domain\Models\VendorProfile',
                counterAccountId: 1,
            ),
        ],
    );
}

it('posting twice with same key returns wasIdempotentReplay=true', function (): void {
    $key   = 'idempotency-replay-' . uniqid();
    $input = buildTestInput($key);

    $first  = $this->action->post($input);
    $second = $this->action->post($input);

    expect($first->wasIdempotentReplay)->toBeFalse();
    expect($second->wasIdempotentReplay)->toBeTrue();
    expect($second->groupId)->toBe($first->groupId);

    // No duplicate rows created
    expect(DB::table('ledger_transaction_groups')->where('idempotency_key', $key)->count())->toBe(1);
    expect(DB::table('wallet_ledger')->where('transaction_group_id', $first->groupId)->count())->toBe(2);
})->group('idempotency', 'us2');

it('posting twice with same key but different kind throws DuplicateIdempotencyKeyWithDifferentPayloadException', function (): void {
    $key    = 'idempotency-conflict-' . uniqid();
    $input1 = buildTestInput($key, TransactionKind::ManualAdjustment);
    $input2 = buildTestInput($key, TransactionKind::SuspenseMovement); // different kind

    $this->action->post($input1);

    expect(fn () => $this->action->post($input2))
        ->toThrow(DuplicateIdempotencyKeyWithDifferentPayloadException::class);
})->group('idempotency', 'us2');
