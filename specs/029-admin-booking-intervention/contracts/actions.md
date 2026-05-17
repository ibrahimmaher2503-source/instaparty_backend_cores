# Contracts — Application Actions

> All Actions live in `app/Modules/Booking/Application/Actions/` and follow `.claude/rules/actions.md`: one public `execute()` method, constructor injection, `DB::transaction(fn () => ...)`, `DB::afterCommit(...)` for events, no work outside the transaction.

---

## `SendVendorReminderAction`

```php
final class SendVendorReminderAction
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly IdempotencyService $idempotency,
    ) {}

    /**
     * @throws \DomainException if reminder is throttled, or vendor is no longer pending.
     */
    public function execute(BookingVendor $bookingVendor, int $adminId, ?string $note = null): BookingAdminIntervention;
}
```

- **Guard**: `bookingVendor->sub_status === VendorSubStatus::Pending`
- **Throttle**: `idempotency_keys` scope = `admin.intervention.vendor_reminder`, key = `bookingVendor->id`, TTL = 5 min
- **Persist**: `BookingAdminIntervention(type=VendorReminder, before_state={sub_status}, after_state={sub_status, note?})`
- **Audit**: `audit_logs(action='booking.vendor_reminder', user_id=adminId, changes={booking_vendor_id, note})`
- **Dispatch**: `NotificationDispatcher::dispatch('booking.vendor.reminder', vendorPrimaryUserId, NotificationAudience::Vendor, [...], BookingVendor::class, bookingVendor->id)`
- **Event** (after commit): `VendorReminderSent($bookingVendorId, $adminId)`

## `EscalateLateVendorResponseAction`

```php
final class EscalateLateVendorResponseAction
{
    public function __construct(
        private readonly AdminInboxWriter $inboxWriter,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @throws \DomainException if deadline has not passed, or vendor already non-pending.
     */
    public function execute(BookingVendor $bookingVendor, AdminInterventionDTO $dto): BookingAdminIntervention;
}
```

- **Guard**: `response_deadline + config('booking.intervention.deadline_grace_period_minutes', 0) < now()` AND `sub_status = Pending`
- **Row lock**: `BookingVendor::query()->whereKey($id)->lockForUpdate()->first()` inside the transaction
- **Persist**:
  - `BookingVendor.sub_status = TimedOut`
  - `BookingAdminIntervention(type=VendorTimeout, before_state={sub_status: pending}, after_state={sub_status: timed_out})`
  - `state_transitions(transitionable=BookingVendor, from=pending, to=timed_out, trigger_kind=admin, triggered_by=adminId, context={intervention_id, reason, booking_id})`
- **Inbox**: `AdminInboxWriter::create('booking_admin_intervention', intervention.id, AdminInboxSeverity::Medium, [en, ar title], [en, ar body], adminId)`
- **Audit**: `audit_logs(action='booking.vendor_timeout', ...)`
- **Dispatch (after commit, via listener)**:
  - `booking.vendor.timed_out_by_admin` → vendor primary user
  - `booking.vendor.timed_out_by_admin.customer` → customer
- **Event** (after commit): `BookingVendorTimedOut($bookingVendorId, $bookingId, $adminId, $reason)`

## `SuggestAlternativeVendorsAction`

```php
final class SuggestAlternativeVendorsAction
{
    public function __construct(
        private readonly AlternativeVendorFinder $finder,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @throws \DomainException if any vendor_profile_id fails the candidate filter, or list is empty.
     * @throws \InvalidArgumentException if $dto->vendorProfileIds size > config max.
     */
    public function execute(Booking $booking, SuggestedAlternativeVendorsDTO $dto): BookingAdminIntervention;
}
```

- **Guard**: `count(vendorProfileIds) >= 1 && <= config('booking.intervention.suggest_max_candidates', 5)`, every ID passes `AlternativeVendorFinder::validateCandidates($booking, $ids)`
- **Persist**: ONE `BookingAdminIntervention(type=VendorProposal, proposed_vendor_id=NULL, before_state={}, after_state={suggested_vendor_ids: [...], reason})`
- **MUST NOT**: create / modify any `booking_vendors` row. Static reflection test FR-EXT-011 enforces.
- **Audit**: `audit_logs(action='booking.suggest_alternatives', changes={suggested_vendor_ids, reason})`
- **Dispatch (after commit, via listener)**: `booking.alternatives.suggested` → customer (context includes the candidate vendor `public_id`s, never internal IDs)
- **Event** (after commit): `AdminSuggestedAlternativeVendors($bookingId, $adminId, $vendorProfileIds)`

## `FreezeBookingChatAction` *(gated on `chat_threads`)*

```php
final class FreezeBookingChatAction
{
    public function __construct(
        private readonly ChatThreadRepository $chatThreads,        // existing contract or new thin one
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @throws \DomainException if thread missing or already frozen.
     */
    public function execute(Booking $booking, int $adminId, string $reason): BookingAdminIntervention;
}
```

- **Guard**: `chat_threads` row exists for this booking; `frozen_at IS NULL`
- **Persist**: `chat_threads.frozen_at = now()`, `chat_threads.frozen_by = adminId`; `BookingAdminIntervention(type=ChatFrozen, before_state={frozen_at: null}, after_state={frozen_at, frozen_by, reason})`
- **Audit**: `audit_logs(action='booking.chat_frozen', changes={reason})`
- **Dispatch (after commit, via listener)**:
  - `booking.chat.frozen` → customer
  - `booking.chat.frozen` → each affected vendor user
  - Firestore mirror push via existing chat gateway (idempotent — push current `frozen_at`)
- **Event** (after commit): `BookingChatFrozen($bookingId, $adminId, $reason)`

## `ResumeBookingChatAction` *(gated; pair of Freeze)*

Same shape as `FreezeBookingChatAction` but inverse: guard `frozen_at IS NOT NULL`; clear both columns; intervention type `ChatResumed`; audit action `booking.chat_resumed`; event `BookingChatResumed`; notification `booking.chat.resumed`.

## `ResumeBookingReviewAction`

```php
final class ResumeBookingReviewAction
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly IdempotencyService $idempotency,
    ) {}

    /**
     * @throws \DomainException if no open modification, or reminder throttled.
     */
    public function execute(Booking $booking, int $adminId, ?string $note = null): BookingAdminIntervention;
}
```

- **Guard**: at least one `booking_modifications` row in status `pending` exists for this booking
- **Throttle**: `idempotency_keys` scope = `admin.intervention.customer_review_reminder`, key = `booking->id`, TTL = `config('booking.intervention.customer_review_reminder_cooldown_hours', 4)` h
- **Persist**: `BookingAdminIntervention(type=CustomerReviewReminder, before_state={open_modifications_count}, after_state={open_modifications_count, note?})`
- **MUST NOT**: change any state column on `bookings`, `booking_vendors`, or `booking_modifications`
- **Audit**: `audit_logs(action='booking.customer_review_reminder', ...)`
- **Dispatch (after commit, via listener)**: `booking.customer_review.reminder` → customer
- **Event** (after commit): `CustomerReviewReminderSent($bookingId, $adminId)`

## `CreateAdminInterventionNoteAction`

```php
final class CreateAdminInterventionNoteAction
{
    public function execute(Booking $booking, int $adminId, string $note): BookingAdminIntervention;
}
```

- **Validation**: `$note` length 1..2000 (enforced in Filament Form Action; defensive check in execute())
- **Persist**: `BookingAdminIntervention(type=AdminNote, reason=$note, before_state={}, after_state={})`
- **Audit**: `audit_logs(action='booking.admin_note', changes={note_length})` — note body itself is in `booking_admin_interventions.reason`; we do not duplicate the full text in `audit_logs.changes`
- **MUST NOT**: dispatch any notification; fire no event

---

## Constructor dependency conventions

- Every Action that calls Communication uses contracts (`NotificationDispatcher`, `AdminInboxWriter`) — never direct model imports.
- Every Action that throttles uses `IdempotencyService` from `app/Modules/Shared/Application/Services/IdempotencyService.php` (existing).
- `AlternativeVendorFinder` is a NEW contract introduced in `app/Modules/Discovery/Domain/Contracts/` and bound in `DiscoveryServiceProvider`. See research §R-5.

## Forbidden Action surface (FR-EXT-010 / FR-EXT-011)

- No class named, or whose name contains the substring, `AssignReplacementVendor` (case-insensitive) may exist anywhere under `App\Modules\Booking\` or `App\Modules\`.
- No method on any Booking Action may be named `assignReplacementVendor`.
- No Filament `Action::make('assignReplacement…')` may be registered in `BookingsMonitorResource`, `AdminBookingInterventionResource`, or any related resource / page.
- Architecture test `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` asserts all three.
