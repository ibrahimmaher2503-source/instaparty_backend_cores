# Contract: `ReverseCommissionOnRefundCompletedListener`

**Module**: Settlement | **Owner of the source event**: Payments

Cross-module event listener: subscribes to `App\Modules\Payments\Domain\Events\RefundCompleted` and reverses the corresponding commission(s) + wallet credit(s) proportionally.

---

## Subscription

| Source event | Source module | Listener | Queue |
|---|---|---|---|
| `RefundCompleted` | Payments (Phase 4.1) | `App\Modules\Settlement\Application\Listeners\ReverseCommissionOnRefundCompletedListener` | `database` (queued, async) |

Wired in `SettlementServiceProvider::boot()`.

---

## Input contract (event payload)

```php
final readonly class RefundCompleted {
    public function __construct(public int $refund_id) {}
}
```

The listener fetches the full refund + payment + booking item context via cross-module readers.

---

## Behavior

1. Resolve `RefundSnapshotDto` via `SettlementPaymentReader::findRefundById($event->refund_id)`.
2. If refund not found or status ≠ `completed`, log `error` and return.
3. Resolve the originating payment via `SettlementPaymentReader::findById($refund->payment_id)`.
4. Determine which commission(s) to reverse:
   - **Single-item refund**: `refund.booking_item_id` is non-null → reverse exactly that one commission row
   - **Full-payment refund**: `refund.booking_item_id` is null → reverse ALL commission rows for the payment (`commissions WHERE payment_id = refund.payment_id`)
5. For each affected commission, call `ReverseCommissionAction::execute($commission, $refundDto)` which:
   a. Computes `reversal_ratio = refund_amount_minor / payment_amount_minor` (or `1.0` if single-item refund equals the full item amount).
   b. Computes `commission_to_reverse = commission_minor * reversal_ratio` and `wallet_share_to_reverse = vendor_share_minor * reversal_ratio` using `Brick\Money` HALF_EVEN rounding.
   c. Updates `commissions.reversed_amount_minor += commission_to_reverse`.
   d. Updates `commissions.status` to `partially_reversed` (if `reversed_amount_minor < commission_minor`) or `reversed` (if equal).
   e. Calls `DebitWalletAction::execute()` to append `wallet_ledger` `refund_debit` entry of `wallet_share_to_reverse` (negative amount).
   f. Updates `wallets.balance_minor` cached value (may go negative — allowed, flagged in audit).
   g. Fires `CommissionReversed` and `WalletDebited` after commit.
6. If resulting `balance_minor < 0`, append `audit_logs` entry with severity `warn` flagging negative balance for admin review.

---

## Idempotency

- `wallet_ledger` UNIQUE on `(related_entity_type, related_entity_id, entry_type)` — for refund reversals, the related entity is the `Refund` row → at most one `refund_debit` entry per `(refund_id, entry_type='refund_debit')`.
- Duplicate event delivery: second `INSERT INTO wallet_ledger` fails with unique constraint violation → caught, logged as `info` "duplicate event ignored", job marked complete.
- `commissions.reversed_amount_minor` is updated atomically inside the transaction; idempotent guard ensures the same refund cannot apply twice.

---

## Failure modes

| Failure | Behavior |
|---|---|
| Refund not found | Log `error`; mark complete |
| Payment not found | Log `error`; mark complete |
| No commission rows for payment | Log `info` (commission may have been waived/zero); mark complete |
| Commission already fully reversed | Log `info`; skip |
| `DebitWalletAction` throws | Re-throw → queue retries (3 attempts, exponential backoff) |
| Resulting balance negative | Allowed; log `warn` to `audit_logs`; vendor blocked from further withdrawals (enforced at `RequestWithdrawalAction` validation) |

---

## Outputs

For each affected commission, the listener guarantees:

1. `commissions.reversed_amount_minor` increased by the proportional reversal amount
2. `commissions.status` updated to `partially_reversed` or `reversed`
3. Exactly one `wallet_ledger` entry with `entry_type = refund_debit` per (commission, refund) pair
4. `wallets.balance_minor` decreased by the proportional vendor share
5. `CommissionReversed` and `WalletDebited` events fired after commit
6. If balance ends negative, `audit_logs` entry of severity `warn`

---

## Proportionality math (verified by Pest)

**Example 1 — full refund of single-item payment:**
- Original: `commission_minor=15000, vendor_share_minor=85000, payment_amount_minor=100000`
- Refund: `refund_amount_minor=100000` (full)
- `reversal_ratio = 100000 / 100000 = 1.0`
- `commission_to_reverse = 15000 * 1.0 = 15000` (commission fully reversed)
- `wallet_share_to_reverse = 85000 * 1.0 = 85000` (full vendor share debited)
- Net wallet effect: −85000 (vendor returns the credit)
- Commission status: `reversed`

**Example 2 — partial refund (50%):**
- Same originals as above
- Refund: `refund_amount_minor=50000` (50%)
- `reversal_ratio = 50000 / 100000 = 0.5`
- `commission_to_reverse = 15000 * 0.5 = 7500`
- `wallet_share_to_reverse = 85000 * 0.5 = 42500`
- Sum: `7500 + 42500 = 50000` (= refund amount; invariant holds)
- Commission status: `partially_reversed`, `reversed_amount_minor = 7500`

**Example 3 — odd-amount refund (with HALF_EVEN rounding):**
- Originals: `commission_minor=10001, vendor_share_minor=89999, payment_amount_minor=100000`
- Refund: `refund_amount_minor=33333` (33.333%)
- `reversal_ratio = 33333 / 100000 = 0.33333`
- `commission_to_reverse = 10001 * 0.33333 = 3333.633...` → HALF_EVEN → `3334`
- `wallet_share_to_reverse = 89999 * 0.33333 = 29999.367...` → HALF_EVEN → `29999`
- Sum: `3334 + 29999 = 33333` (= refund amount exactly; HALF_EVEN minimizes aggregate bias)

---

## Pest test coverage

- `tests/Feature/Modules/Settlement/RefundReversalTest.php` — full + partial + multi-item
- `tests/Unit/Modules/Settlement/ProportionalReversalMathTest.php` — Brick\Money rounding correctness on edge cases
- Idempotency: duplicate event dispatch in `RefundReversalTest::test_duplicate_event_does_not_double_debit`
- Negative balance: `RefundReversalTest::test_refund_after_withdrawal_produces_negative_balance_with_audit_warn`
