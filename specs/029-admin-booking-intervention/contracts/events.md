# Contracts — Domain Events

> All events live in `app/Modules/Booking/Domain/Events/`. All are fired via `DB::afterCommit(fn () => event(...))`. All implement `Illuminate\Foundation\Events\Dispatchable` and `Illuminate\Queue\SerializesModels`.

---

## `VendorReminderSent`

```php
final class VendorReminderSent
{
    public function __construct(
        public readonly int $bookingVendorId,
        public readonly int $adminId,
    ) {}
}
```

- **Listeners**: none in this PR (dispatch happens inside the Action, not via a listener — kept inline to avoid an extra hop)

## `BookingVendorTimedOut`

```php
final class BookingVendorTimedOut
{
    public function __construct(
        public readonly int $bookingVendorId,
        public readonly int $bookingId,
        public readonly ?int $triggeredByAdminId,    // NULL when fired by the scheduled job
        public readonly string $reason,
    ) {}
}
```

- **Listeners**:
  - `OnBookingVendorTimedOutCreateInboxItem` (Booking module → calls `AdminInboxWriter`)
  - `OnBookingVendorTimedOutNotifyVendor` (Booking module → calls `NotificationDispatcher`)
  - `OnBookingVendorTimedOutNotifyCustomer` (Booking module → calls `NotificationDispatcher`)
  - **All listeners implement `ShouldQueue`** — no synchronous external work from event listeners.

## `AdminSuggestedAlternativeVendors`

```php
final class AdminSuggestedAlternativeVendors
{
    public function __construct(
        public readonly int $bookingId,
        public readonly int $adminId,
        /** @var array<int> */
        public readonly array $vendorProfileIds,
        public readonly string $reason,
    ) {}
}
```

- **Listeners**:
  - `OnAdminSuggestedAlternativeVendorsNotifyCustomer` — dispatches `booking.alternatives.suggested` with the candidate vendors' `public_id`s in the context payload.

## `BookingChatFrozen` *(gated)*

```php
final class BookingChatFrozen
{
    public function __construct(
        public readonly int $bookingId,
        public readonly int $adminId,
        public readonly string $reason,
    ) {}
}
```

- **Listeners**:
  - `OnBookingChatFrozenPushFirestore` — pushes the `frozen_at` flag to the Firestore mirror via existing chat gateway contract.
  - `OnBookingChatFrozenNotifyParties` — dispatches `booking.chat.frozen` to customer + each vendor user.

## `BookingChatResumed` *(gated; pair of frozen)*

Same payload as `BookingChatFrozen`. Listener `OnBookingChatResumedPushFirestore` clears the flag; `OnBookingChatResumedNotifyParties` dispatches `booking.chat.resumed`.

## `CustomerReviewReminderSent`

```php
final class CustomerReviewReminderSent
{
    public function __construct(
        public readonly int $bookingId,
        public readonly int $adminId,
    ) {}
}
```

- **Listeners**:
  - `OnCustomerReviewReminderSentNotifyCustomer` — dispatches `booking.customer_review.reminder`.

---

## Ordering guarantees

- All events are dispatched via `DB::afterCommit(...)` — listeners never observe a half-committed mutation.
- Queue workers process listeners with at-least-once semantics. Listeners are idempotent: each `NotificationDispatcher::dispatch(...)` call inserts a unique row keyed by `(template_id, user_id, reference_type, reference_id, dedup_window)` per the dispatcher's existing dedup contract.

## Architecture test enforcement

- `tests/Architecture/EventsFireAfterCommitTest.php` (NEW) — greps every Action in `app/Modules/Booking/Application/Actions/` for `event(new ` and asserts the call lives inside a `DB::afterCommit(...)` closure. Failure = constitution principle IX violation.
