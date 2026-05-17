# Quickstart: Financial Ledger Hardening

**Audience**: Ibrahim (developer), running the feature locally to verify behaviour before/after the cut-over.
**Prerequisites**: working `instaparty_backend_cores` checkout, `php artisan migrate:fresh --seed` works, Redis running.

This walkthrough exercises the ledger writer, the projection cache, idempotency, the reconciliation command, and the admin endpoints. It is the local equivalent of the launch validation checklist.

---

## 1. Apply migrations and run the backfill

```pwsh
git checkout 028-financial-ledger-hardening
composer install
php artisan migrate          # adds the new columns + 4 new tables + the trigger
php artisan db:seed --class=Database\Seeders\FinancialSuspenseAccountsSeeder
php artisan ledger:backfill --batch=10000
php artisan ledger:diff      # expect: "Zero drift across N wallets"
```

If `ledger:diff` reports drift, **stop**. The shadow-write phase has detected an inconsistency. Investigate before continuing.

---

## 2. Exercise the ledger writer manually

```pwsh
php artisan tinker
```

```php
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Domain\Enums\{TransactionKind, LedgerDirection, SuspenseAccount};

$writer = app(LedgerWriter::class);

$result = $writer->post(new PostLedgerTransactionInput(
    kind: TransactionKind::ManualAdjustment,
    currency: 'EGP',
    idempotencyKey: 'tinker:adj:001',
    correlationId: \Symfony\Component\Uid\Ulid::generate(),
    causationId: null,
    initiatorType: 'system',
    initiatedByUserId: 1,
    entries: [
        new LedgerEntryInput(
            walletOwnerType: 'vendor',
            walletOwnerId: 1,
            direction: LedgerDirection::Credit,
            amountMinor: 50000, // 500 EGP
            entryType: 'manual_adjustment_credit',
            counterAccountType: 'platform_account',
            counterAccountId: SuspenseAccount::PlatformAdjustments->value,
        ),
        new LedgerEntryInput(
            walletOwnerType: 'platform_account',
            walletOwnerId: SuspenseAccount::PlatformAdjustments->value,
            direction: LedgerDirection::Debit,
            amountMinor: 50000,
            entryType: 'manual_adjustment_debit',
            counterAccountType: 'vendor',
            counterAccountId: 1,
        ),
    ],
    descriptionKey: 'settlement.ledger.manual_adjustment',
));

echo $result->groupPublicId . "\n";
echo json_encode($result->newBalances, JSON_PRETTY_PRINT) . "\n";
```

Expected: a single `ledger_transaction_groups` row, two `wallet_ledger` rows (one credit + one debit, balanced), and vendor 1's wallet cache `balance_minor` increased by 50000.

---

## 3. Verify idempotency

Re-run the same `$writer->post(...)` call with the same `idempotencyKey: 'tinker:adj:001'`. Expected:

```text
$result->wasIdempotentReplay === true
```

No second ledger group is created. Run `SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key = 'tinker:adj:001'` → must return 1.

Re-run with the same key but a different amount. Expected: `DuplicateIdempotencyKeyWithDifferentPayloadException` is thrown.

---

## 4. Verify the append-only trigger

```pwsh
php artisan tinker
```

```php
\DB::statement('UPDATE wallet_ledger SET amount_minor = 1 WHERE id = 1');
// Expected: QueryException — "wallet_ledger is append-only"

\DB::statement('DELETE FROM wallet_ledger WHERE id = 1');
// Expected: QueryException — "wallet_ledger is append-only"
```

If either statement succeeds, the trigger install migration is broken — fix before continuing.

---

## 5. Verify concurrent withdrawal protection

```pwsh
./vendor/bin/pest --group=concurrency tests/Feature/Modules/Settlement/Withdrawals/ConcurrentRequestsBlockOverdrawTest.php
```

Expected: the test forks 10 workers each requesting a 1000-EGP withdrawal against a 1000-EGP wallet; exactly 1 succeeds and 9 fail with "insufficient available balance". Test passes on Linux CI; on Windows it skips with a clear `pcntl required` message.

---

## 6. Trigger reconciliation manually

```pwsh
php artisan reconcile:run --scope=all
```

Expected output:

```
Reconciliation run 01J... started.
  Wallets scanned: 142
  Findings: 0
  Auto-repaired: 0
  Manual review: 0
Status: clean
```

Then deliberately corrupt a wallet's cache to see drift detection:

```pwsh
php artisan tinker
```

```php
\DB::statement('SET @ledger_backfill_in_progress = 1');     // bypass for demo only
\App\Modules\Settlement\Domain\Models\Wallet::find(1)->update(['balance_minor' => 99999999]);
\DB::statement('SET @ledger_backfill_in_progress = NULL');
```

Re-run reconciliation:

```pwsh
php artisan reconcile:run --scope=wallet --wallet-id=1
```

Expected: 1 finding (severity `warning`, `wallet_cache_drift`), auto-repaired. Wallet 1's `balance_minor` is restored to its ledger-projected value.

---

## 7. Hit the admin endpoints

```pwsh
$token = "<admin sanctum token>"

# Status
curl -H "Authorization: Bearer $token" -H "Accept-Language: ar" https://localhost/admin/reconciliation/status

# Trigger
curl -X POST \
  -H "Authorization: Bearer $token" \
  -H "Idempotency-Key: 01J-local-trigger-001" \
  -H "Content-Type: application/json" \
  -d '{"scope_type":"all"}' \
  https://localhost/admin/reconciliation/trigger
```

Repeat the POST with the same `Idempotency-Key` — expected: same `run_public_id` returned (idempotent).

Repeat with the same key but `{"scope_type":"vendor","vendor_id":1}` — expected: HTTP 409.

---

## 8. Inspect the Filament admin views

Log in at `/admin`. Navigate to **Settlement → Reconciliation Runs**. Expected:
- List of runs sorted by `created_at` desc.
- Click into a run → detail page with run summary and a relation manager listing its findings.
- Switch the panel locale to AR using the language switcher → all column headers, finding descriptions, and status badges render in Arabic.

---

## 9. Run the full Pest suite for this feature

```pwsh
./vendor/bin/pest --group=ledger --group=reconciliation --group=concurrency --group=idempotency
./vendor/bin/pest tests/Feature/Modules/Settlement/ tests/Feature/Modules/Payments/Webhook tests/Feature/Modules/Payments/Refunds
./vendor/bin/pest tests/Architecture
```

All green is the precondition for cut-over.

---

## 10. Validate the success criteria locally

| SC | Local check |
|---|---|
| SC-001 (no direct writes) | `./vendor/bin/pest tests/Architecture/NoDirectWalletBalanceWritesTest.php` |
| SC-002 (balances match ledger) | `php artisan ledger:diff` — must report zero drift |
| SC-003 (100 duplicate webhooks → 1 capture) | `./vendor/bin/pest --filter=DuplicateWebhookProducesOneLedgerGroupTest` |
| SC-004 (50 concurrent withdrawals → 1 wins) | `./vendor/bin/pest tests/Feature/Modules/Settlement/Withdrawals/ConcurrentRequestsBlockOverdrawTest.php` |
| SC-005 (partial refund accounting) | `./vendor/bin/pest tests/Feature/Modules/Payments/Refunds/` |
| SC-006 (reconciliation under 5 min) | `time php artisan reconcile:run --scope=all` on the dev DB |
| SC-007 (drift detected and repaired) | Step 6 above |
| SC-008 (all Actions idempotent) | Step 3 above + `./vendor/bin/pest --group=idempotency` |
| SC-009 (correlation + causation) | `./vendor/bin/pest tests/Unit/Modules/Settlement/CausalChainTraversalTest.php` |
| SC-010 (settlement resumes after crash) | `./vendor/bin/pest --filter=ConcurrentSettlementResumeTest` |
| SC-011 (3-click forensic chain) | Manual: open Filament `wallet_ledger` row → relation → group → events |
| SC-012 (CI blocks direct writes) | Step 9 (architecture tests must be in CI green pipeline) |

---

## When all 12 success criteria pass locally → proceed to cut-over

1. Re-run shadow-write monitoring on staging for 24h.
2. Flip the feature flag.
3. Re-run `php artisan ledger:diff` in production.
4. Done.
