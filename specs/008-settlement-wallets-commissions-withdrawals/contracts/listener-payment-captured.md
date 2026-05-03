# Contract: `CalculateCommissionOnPaymentCapturedListener`

**Module**: Settlement | **Owner of the source event**: Payments

Cross-module event listener: subscribes to `App\Modules\Payments\Domain\Events\PaymentCaptured` and produces commission rows + wallet credits for every booking item in the captured payment.

---

## Subscription

| Source event | Source module | Listener | Queue |
|---|---|---|---|
| `PaymentCaptured` | Payments (Phase 4.0) | `App\Modules\Settlement\Application\Listeners\CalculateCommissionOnPaymentCapturedListener` | `database` (queued, async) |

Wired in `SettlementServiceProvider::boot()` via Laravel's event mapper.

---

## Input contract (event payload)

`PaymentCaptured` carries `payment_id` (BIGINT). The listener is responsible for fetching everything else through the cross-module `SettlementPaymentReader` and `SettlementBookingReader` contracts.

```php
final readonly class PaymentCaptured {
    public function __construct(public int $payment_id) {}
}
```

---

## Behavior

1. Resolve `PaymentSnapshotDto` via `SettlementPaymentReader::findById($event->payment_id)` (returns DTO, not Eloquent model).
2. If payment not found or status ≠ `captured`, log error to `audit_logs` and return (defensive).
3. Resolve all booking items via `SettlementBookingReader::itemsForPayment($payment->booking_id)` → returns `BookingItemSnapshotDto[]`.
4. For each booking item:
   a. Resolve commission rate:
      - Prefer `bookingItem.commission_bps` (snapshot from booking confirmation).
      - If null, fall back to `EloquentCommissionRateResolver::resolve($categoryId, $productType)` (4-level fallback ladder).
      - If still null after fallback, use 0 bps and log warning.
   b. Compute `commission_minor` and `vendor_share_minor` using `Brick\Money` HALF_EVEN rounding.
   c. Call `CalculateCommissionAction::execute(...)` which:
      - Creates `Commission` row with snapshot of `commission_bps`, `commission_minor`, `vendor_share_minor`.
      - Calls `CreditWalletAction::execute()` to append `wallet_ledger` `commission_credit` entry of `vendor_share_minor`.
      - Updates `wallets.balance_minor` cached value in the same transaction.
      - Fires `CommissionCalculated` and `WalletCredited` after commit.
5. Return.

---

## Idempotency

- `commissions.booking_item_id` UNIQUE constraint catches duplicate event delivery — second insert fails with `QueryException` for unique constraint violation.
- Listener catches the specific exception, logs an `info` audit row "duplicate event ignored", and returns success (so the queue marks the job as completed and doesn't retry forever).
- Net effect: same commission row, same wallet credit — exactly once.

---

## Failure modes

| Failure | Behavior |
|---|---|
| Payment not found | Log `error` to `audit_logs`; mark job complete (don't retry — payment isn't going to materialize) |
| Booking item missing | Log `error`; mark job complete |
| Commission rate missing at all 4 levels | Log `warn`; use 0 bps; vendor receives full amount |
| `CreditWalletAction` throws (DB error, etc.) | Re-throw → queue retries up to 3 times with exponential backoff (10s, 60s, 300s); after 3 failures, lands in `failed_jobs` table for admin review |
| Negative wallet balance produced (impossible on credit, but defensive) | Log `error`; insert anyway; flag for admin |

---

## Outputs

For each booking item processed, the listener guarantees:

1. Exactly one `commissions` row with status `calculated`
2. Exactly one `wallet_ledger` entry with `entry_type = commission_credit`
3. `wallets.balance_minor` updated to reflect the new credit
4. `CommissionCalculated` and `WalletCredited` events fired after commit

---

## Performance budget

- Listener completes within **5 seconds** of `PaymentCaptured` (queue latency + processing time)
- Processing time per booking item: ~50ms (4 indexed SELECTs + 2 INSERTs + 1 UPDATE)
- Typical payment has 1–3 booking items → total work ~150ms

---

## Pest test coverage

- `tests/Feature/Modules/Settlement/CommissionCalculationTest.php` — happy path for all 3 product types
- `tests/Feature/Modules/Settlement/CommissionFallbackTest.php` — all 4 fallback levels
- `tests/Unit/Modules/Settlement/EloquentCommissionRateResolverTest.php` — resolver isolation
- Idempotency tested via duplicate event dispatch in `CommissionCalculationTest::test_duplicate_event_does_not_double_credit`
