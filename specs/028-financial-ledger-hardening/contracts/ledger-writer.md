# Contract: Ledger Writer

**Module**: `app/Modules/Settlement/`
**Domain Contract**: `App\Modules\Settlement\Domain\Contracts\LedgerWriter`
**Default implementation**: `App\Modules\Settlement\Application\Actions\PostLedgerTransactionAction`

This contract is the **only** authorised entry point for writing to `wallet_ledger`. Every money-mutating Action across Payments and Settlement delegates to it.

## Interface

```php
namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\LedgerTransactionResult;

interface LedgerWriter
{
    /**
     * Post one balanced double-entry transaction.
     *
     * The implementation MUST:
     *  - Acquire a wallet-scoped lock for every wallet referenced in $input->entries.
     *  - Verify SUM(debits) == SUM(credits) per currency; reject unbalanced groups.
     *  - Honour the supplied idempotency key: if a group with the same key exists,
     *    return the existing LedgerTransactionResult without writing anything new.
     *  - Insert exactly one ledger_transaction_groups row + N wallet_ledger rows
     *    inside one DB transaction.
     *  - Project the affected wallets' balance cache inside the same DB transaction.
     *  - Emit LedgerTransactionPosted via DB::afterCommit.
     *
     * Throws:
     *  - UnbalancedTransactionException
     *  - DuplicateIdempotencyKeyWithDifferentPayloadException (HTTP 409 equivalent)
     *  - WalletCurrencyMismatchException
     *  - LockAcquisitionTimeoutException (retryable)
     */
    public function post(PostLedgerTransactionInput $input): LedgerTransactionResult;
}
```

## Input DTO (`PostLedgerTransactionInput`)

```php
final readonly class PostLedgerTransactionInput
{
    public function __construct(
        public TransactionKind $kind,
        public string $currency,                    // ISO-4217
        public string $idempotencyKey,              // REQUIRED — caller-supplied
        public string $correlationId,               // ULID — from Context if available
        public ?string $causationId,                // ULID of upstream cause
        public string $initiatorType,               // 'system'|'webhook'|'admin'|...
        public ?int $initiatedByUserId,
        public array $entries,                      // list<LedgerEntryInput> — each side of double-entry
        public ?string $descriptionKey = null,
        public ?array $descriptionParams = null,
        public ?array $metadata = null,
    ) {}
}

final readonly class LedgerEntryInput
{
    public function __construct(
        public string $walletOwnerType,             // 'vendor'|'platform_account'|...
        public int|string $walletOwnerId,           // int for vendors, SuspenseAccount enum for platform
        public LedgerDirection $direction,          // debit|credit
        public int $amountMinor,                    // UNSIGNED magnitude
        public string $entryType,                   // wallet_ledger.entry_type ENUM value
        public string $counterAccountType,
        public int|string $counterAccountId,
        public ?string $relatedEntityType = null,
        public ?int $relatedEntityId = null,
        public ?string $entryIdempotencyKey = null, // optional per-entry key (for refunds)
    ) {}
}
```

## Output DTO (`LedgerTransactionResult`)

```php
final readonly class LedgerTransactionResult
{
    public function __construct(
        public int $groupId,
        public string $groupPublicId,
        public bool $wasIdempotentReplay,           // true if returned from prior call
        public array $ledgerEntryIds,               // wallet_ledger.id values
        public array $affectedWalletIds,
        public array $newBalances,                  // [walletId => balanceMinor]
    ) {}
}
```

## Invariants enforced by the writer

1. **Balanced entries**: `SUM(debits) == SUM(credits)` per currency within the group. Application check first; DB CHECK constraint as backstop.
2. **Same currency within a group**: All entries share `$input->currency`. Mixed currencies are a future feature (out of scope).
3. **Idempotency**: Same `$input->idempotencyKey` returns the prior result without side effects. Same key + different payload throws `DuplicateIdempotencyKeyWithDifferentPayloadException`.
4. **Lock acquisition order**: Wallets are locked in ascending `wallet_id` order to prevent deadlock.
5. **Atomic projection**: Cache update on `wallets` happens in the same DB transaction as the ledger insert. A failure in either path rolls back both.
6. **Event timing**: `LedgerTransactionPosted` fires via `DB::afterCommit()` — never inside the transaction.

## Forbidden usage

- Calling `Wallet::increment()` / `Wallet::decrement()` / `Wallet::update(['balance_minor' => ...])` anywhere outside `ProjectWalletBalanceAction`. Enforced by `tests/Architecture/NoDirectWalletBalanceWritesTest`.
- Creating `WalletLedgerEntry` rows directly via `WalletLedgerEntry::create(...)` outside `PostLedgerTransactionAction`. Enforced by `tests/Architecture/LedgerEntryHasRequiredColumnsTest`.

## Example: payment capture transaction

```php
$writer->post(new PostLedgerTransactionInput(
    kind: TransactionKind::PaymentCapture,
    currency: 'EGP',
    idempotencyKey: "capture:{$payment->id}",
    correlationId: Context::get('correlation_id'),
    causationId: $webhookEventId,
    initiatorType: 'webhook',
    initiatedByUserId: null,
    entries: [
        new LedgerEntryInput(
            walletOwnerType: 'platform_account',
            walletOwnerId: SuspenseAccount::GatewayInTransit->value,
            direction: LedgerDirection::Debit,
            amountMinor: $payment->amount_minor,
            entryType: 'payment_capture',
            counterAccountType: 'platform_account',
            counterAccountId: SuspenseAccount::PlatformClearing->value,
            relatedEntityType: 'payment',
            relatedEntityId: $payment->id,
        ),
        new LedgerEntryInput(
            walletOwnerType: 'platform_account',
            walletOwnerId: SuspenseAccount::PlatformClearing->value,
            direction: LedgerDirection::Credit,
            amountMinor: $payment->amount_minor,
            entryType: 'payment_capture',
            counterAccountType: 'platform_account',
            counterAccountId: SuspenseAccount::GatewayInTransit->value,
            relatedEntityType: 'payment',
            relatedEntityId: $payment->id,
        ),
    ],
    descriptionKey: 'settlement.ledger.payment_capture',
    descriptionParams: ['payment_id' => $payment->public_id],
));
```

Commission accrual would post a second transaction (separate group) that debits `platform_clearing` and credits the vendor wallet + `platform_commission_receivable`. Each transaction is independently balanced.
