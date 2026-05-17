---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- FR traceability: existing PRD coverage cited from `01_PRD.md`; new requirements use the `FR-EXT-NNN` prefix.
- Schema traceability: existing tables cited from `11_DB_Schema.md`; new tables marked `⚠️ NEW TABLE — not yet in 11_DB_Schema.md`.
- Phase alignment: cite existing phase from `09_Phasing_Plan.md` or propose extension and mark `⚠️ PHASE BACKFILL NEEDED`.
- Never suggest a package not in `10_Package_List.md`.
- Never suggest a Phase 2 feature.
- Never contradict the locked stack in `02_Tech_Decisions.md`.

API DOCUMENTATION CONSTRAINT:
- This feature ships as a Filament admin page only (no public HTTP API). No new endpoints to document in `.specify/memory/api-registry.md` or Bruno/Postman collections.
- Existing controller `BookingInterventionController::forceCancel` is unchanged and already registered.
---

# Feature Specification: Admin Booking Intervention Page

**Feature Branch**: `029-admin-booking-intervention`
**Created**: 2026-05-15
**Status**: Draft
**Input**: User description — "Build AdminBookingInterventionPage for stalled/late/rejected booking workflows. Admin can monitor and facilitate booking issues without choosing a replacement vendor on behalf of the customer."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Surface stalled and late-response bookings (Priority: P1)

As an operations admin I need a single page that lists every booking currently in trouble — vendor response is late, a vendor rejected, the customer has not yet reviewed a modification, or the booking has stalled — so I can triage issues before customers churn or vendors miss their SLA.

**Why this priority**: Without this view, admins discover problems only after customers complain or SLA breaches escalate. This is the foundational MVP slice — without the list, no other intervention is possible.

**Independent Test**: Seed bookings in each of the four problem states (late vendor response, vendor rejected, awaiting customer review, stalled). Open the page. Verify each booking appears exactly once with the correct trouble badge, the nearest response deadline, the booking total, and the customer reference, and that the list is filterable by trouble type and sortable by deadline.

**Acceptance Scenarios**:

1. **Given** a booking with `lifecycle_status = vendor_review` and at least one `booking_vendors.response_deadline < now()`, **When** the admin opens the Booking Intervention page, **Then** the booking is listed with a "Late vendor response" badge and the overdue deadline highlighted.
2. **Given** a booking where every `booking_vendors.sub_status = rejected`, **When** the admin opens the page, **Then** the booking is listed with a "Vendor rejection" badge and a count of how many vendors rejected.
3. **Given** a booking in `lifecycle_status = customer_review` with an open `booking_modifications` row older than 24 hours, **When** the admin opens the page, **Then** the booking is listed with a "Customer review pending" badge and the modification's age in hours.
4. **Given** a booking whose `lifecycle_status` is `submitted` or `vendor_review` and has been in that state longer than the stalled threshold (default 48 hours), **When** the admin opens the page, **Then** the booking is listed with a "Stalled" badge.
5. **Given** the page lists 100+ rows, **When** the admin filters by trouble type or by product type, **Then** only matching rows appear; default sort is by nearest deadline ascending; pagination defaults to 25 rows.

---

### User Story 2 — Inspect a troubled booking in one place (Priority: P1)

As an admin I need a detail/infolist view that shows everything I need to act — booking summary, vendor lineup with deadlines and sub-statuses, recent state transitions, open modifications, payment status, customer notes, and prior admin interventions — without bouncing across four Filament resources.

**Why this priority**: Triage is only useful when an admin can confirm the situation. Without the consolidated detail view, every intervention requires opening 3–5 other pages, which makes the page useless under load.

**Independent Test**: Open the detail view for a seeded booking that has multiple vendors, one open modification, two prior state transitions, and one prior admin intervention. Verify each section renders the live data without an Edit affordance, money columns format in EGP, and translatable text shows the current admin locale.

**Acceptance Scenarios**:

1. **Given** a booking with two vendors, one open modification, and one prior `BookingAdminIntervention`, **When** the admin opens the detail view, **Then** all four data groups are visible in distinct sections and read-only.
2. **Given** the admin switches the Filament locale to AR, **When** the detail view re-renders, **Then** translatable fields (customer notes, vendor business name, rejection reason) appear in Arabic.
3. **Given** the booking has `total_minor = 250000` and `total_currency = EGP`, **When** the admin opens the detail view, **Then** the total renders as `EGP 2,500.00` (`->money('EGP', divideBy: 100)`).

---

### User Story 3 — Send a vendor reminder (Priority: P1)

As an admin I need to send a reminder to a vendor whose response deadline is near or overdue, without leaving the page, so they re-engage before the customer is told the request failed.

**Why this priority**: This is the highest-volume, lowest-risk intervention. Many late responses are resolved by a single nudge.

**Independent Test**: Pick a booking-vendor with an upcoming deadline; trigger the reminder action; verify a `NotificationDispatch` row exists for that vendor's user, a `BookingAdminIntervention` row of type `vendor_reminder` is created, and an `audit_logs` row is appended.

**Acceptance Scenarios**:

1. **Given** a `booking_vendors` row in `sub_status = pending` with `response_deadline > now()`, **When** the admin clicks "Send reminder" and submits an optional note, **Then** a notification dispatch is queued to the vendor's primary user, a `BookingAdminIntervention` row of type `vendor_reminder` is created, an `audit_logs` row records the action, and a success toast appears.
2. **Given** the action is invoked twice within 5 minutes for the same vendor, **When** the second call runs, **Then** the second attempt is rejected with a "throttled" message (no duplicate dispatch).
3. **Given** the vendor row is in `sub_status = accepted` or `sub_status = rejected`, **When** the admin opens the action menu, **Then** the "Send reminder" action is hidden (a reminder only makes sense while a response is pending).

---

### User Story 4 — Escalate a late vendor response (Priority: P1)

As an admin I need a one-click escalation for a vendor whose deadline has passed, which marks the vendor as timed-out, fires the standard timeout event, opens the booking for customer-driven re-routing, and creates an inbox item on me so I can follow up.

**Why this priority**: Without explicit escalation, late vendors stay in pending forever and the booking never advances. This is the action that unblocks the customer.

**Independent Test**: Seed a booking-vendor with `response_deadline < now() − 2h` and `sub_status = pending`. Invoke escalation. Verify the vendor row moves to `timed_out`, a `StateTransition` row is appended, a `BookingAdminIntervention` of type `vendor_timeout` is created, an `AdminInboxItem` is generated, and the standard vendor-timeout notification dispatch fires.

**Acceptance Scenarios**:

1. **Given** a `booking_vendors` row with `response_deadline + grace_period < now()`, **When** the admin escalates with a required reason, **Then** the vendor's `sub_status` becomes `timed_out`, a state transition row is appended (`trigger_kind = admin`), a `BookingAdminIntervention` of type `vendor_timeout` is created, an audit log entry is appended, an `AdminInboxItem` is created for the acting admin with severity `medium`, and a domain event is fired after commit.
2. **Given** a vendor row whose deadline has not yet expired, **When** the admin tries to escalate, **Then** the action is rejected with a guard error ("deadline not yet passed").
3. **Given** the booking has only one vendor and escalation moves it to `timed_out`, **When** the commit completes, **Then** the booking's `lifecycle_status` is unchanged (advancing the booking remains the customer's choice); the booking still appears on the list with a "Stalled" badge.

---

### User Story 5 — Suggest alternative vendors without assigning one (Priority: P2)

As an admin I want to surface up to N candidate replacement vendors to the customer (matching category, governorate, availability) and record the suggestion so it can be seen on the customer side — **but the customer remains the final selector**. The page must never let me assign a replacement directly.

**Why this priority**: Customers stuck after a vendor rejection often don't know who to pick. A curated shortlist accelerates recovery without breaching the marketplace boundary. Lower than P1 because it depends on the discovery query and there is a manual workaround (customer browses themselves).

**Independent Test**: For a booking whose only vendor rejected, invoke the action; pick 2–3 alternative vendor profiles from the candidate list; submit. Verify a `BookingAdminIntervention` row of type `vendor_proposal` is created with the candidate vendor IDs in `after_state.suggested_vendor_ids`, that no `booking_vendors` row is added for any candidate, and that the customer-facing presentation shows the suggestions as advisory.

**Acceptance Scenarios**:

1. **Given** the admin opens the suggestion action on a rejected-vendor booking, **When** the candidate query runs, **Then** results are filtered by the rejected vendor's category, governorate coverage, product type, vendor approval for that product type, and availability for the booking's event window.
2. **Given** the admin selects up to N candidates (default `N = 5`), **When** submission succeeds, **Then** a single `BookingAdminIntervention` of type `vendor_proposal` is persisted with `proposed_vendor_id = NULL` and `after_state.suggested_vendor_ids = [...]` containing the chosen candidates, plus a customer-facing notification dispatch and audit log.
3. **Given** an admin attempts to programmatically call any "assign replacement vendor" endpoint or action, **When** the call is made, **Then** the platform refuses — there is no such action class, controller, or Filament action, and a regression test enforces its non-existence.

---

### User Story 6 — Freeze or resume the booking conversation (Priority: P2)

As an admin I need to temporarily freeze the chat thread for a contested booking so neither party escalates further mid-investigation, and I need to resume it once the issue is contained.

**Why this priority**: Freezing prevents abuse mid-dispute but is rarely needed; lower priority than reminders/escalations.

**Independent Test**: Freeze chat for a booking; verify the chat thread's mirror row records a frozen status, the Firestore gateway is called to push the frozen flag, a `BookingAdminIntervention` of type `chat_frozen` is created, and a notification dispatch is queued to both parties. Resume; verify the inverse.

**Acceptance Scenarios**:

1. **Given** a chat thread linked to the booking, **When** the admin freezes it with a reason, **Then** the chat-thread row records `frozen_at` and `frozen_by`, a downstream signal is queued for the Firestore mirror, an audit log + intervention row are appended, and parties receive a `chat_frozen` notification dispatch.
2. **Given** a frozen chat thread, **When** the admin resumes it with a reason, **Then** `frozen_at` is cleared, a resume signal is dispatched, and an intervention of type `chat_resumed` is recorded.
3. **Given** the chat thread is already frozen, **When** the admin tries to freeze again, **Then** the action is rejected with an "already frozen" guard.

---

### User Story 7 — Resume a stalled customer-review and record admin notes (Priority: P2)

As an admin I need (a) a one-click action that re-pings the customer when their review of a vendor modification has stalled past N hours, and (b) a free-text "intervention note" action that records context (call log, WhatsApp summary, escalation rationale) on the booking without changing any state.

**Why this priority**: These are everyday hygiene actions. Lower priority because they do not change booking state.

**Independent Test**: For a booking in `customer_review` with an open modification > 24h old, invoke the resume-review action; verify the customer receives the `customer_review_pending_reminder` notification dispatch and a `customer_review_reminder` intervention row is appended. Separately, invoke the note action with a 200-char message; verify an `admin_note` intervention with the message text persisted, an audit log, and no state changes anywhere else.

**Acceptance Scenarios**:

1. **Given** a booking in `customer_review` with an unanswered open modification, **When** the admin invokes "Resume customer review", **Then** a notification dispatch with event key `booking.customer_review.reminder` is queued to the customer user, a `BookingAdminIntervention` of type `customer_review_reminder` is created, an audit log row is appended, and no booking or vendor state changes.
2. **Given** the open modification is younger than the configured reminder cooldown (default 4 hours since the last reminder), **When** the admin tries to resume again, **Then** the action is rejected with a throttled-message guard.
3. **Given** the admin submits an intervention note of length 1–2000 characters, **When** the action runs, **Then** an `admin_note` intervention is persisted with the note in `reason`, an audit log row is appended, and the note appears in the detail view's intervention history section.

---

### Edge Cases

- A booking has multiple problem badges (e.g., one vendor late + another rejected). The list shows the highest-severity badge first and exposes all tags in the row's tooltip or secondary column.
- A booking is `completed` or `cancelled` while open in the detail view — actions that would mutate state are hidden, but historical intervention rows remain visible.
- The chat thread for a booking does not exist yet (lazy-created on first message). Freezing must either create the row or fail safely with a localized "no chat thread" message.
- The candidate suggestion query returns zero matches. The action surfaces a localized "no candidates available" message and refuses to persist an empty `vendor_proposal` intervention.
- The acting admin lacks one of the required permissions (e.g., `booking.intervene.send_vendor_reminder`). The action button is hidden in the row, and the action endpoint refuses with a 403 / Filament authorization exception.
- Two admins simultaneously escalate the same vendor row. The second call observes `sub_status = timed_out` and is rejected with a guard error; no duplicate intervention or notification dispatch is created.
- The notification dispatch backend (queue) is temporarily unavailable. The intervention row and audit log are still persisted; the dispatch is enqueued and retried per the standard `event_outbox` / `notification_dispatches` retry policy — the intervention is not rolled back.

## Requirements *(mandatory)*

### Functional Requirements

**Page surface and listing**

- **FR-EXT-001**: System MUST provide a Filament admin page at `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/` (or `Pages/AdminBookingInterventionPage`) titled "Booking Intervention", grouped under the Booking navigation group used by `BookingsMonitorResource`, available only to users with the `booking.intervene.access` permission.
  ⚠️ BACKFILL NEEDED: add this Filament admin page to `01_PRD.md` §11 (Admin Journey) and `09_Phasing_Plan.md` Phase 6 / Phase 7 — currently captured implicitly in `08_Admin_Journey.md` "stalled booking workflow" but not as an explicit deliverable.
- **FR-EXT-002**: The page MUST list bookings tagged with one or more of the four trouble buckets — `late_vendor_response`, `vendor_rejection`, `customer_review_pending`, `stalled` — derived from the live state of `bookings`, `booking_vendors`, `booking_modifications`, and the configured stalled thresholds. The `bookings` table itself MUST NOT be denormalised with a new `trouble_status` column.
- **FR-EXT-003**: The list MUST be filterable by trouble bucket, `product_type` (`rental` / `sale` / `digital`), governorate, and nearest deadline window (`overdue`, `< 4h`, `< 24h`, `> 24h`), and sortable by nearest deadline, submission time, total amount, and customer.
- **FR-EXT-004**: Every list row MUST show: booking reference, customer display name, product-type badge, lifecycle/payment/fulfillment status badges, trouble badge(s) with severity colors, nearest vendor response deadline, total in EGP rendered via `->money('EGP', divideBy: 100)`.
- **FR-EXT-005**: The list MUST default to sort by nearest deadline ascending and paginate at 25/50/100 per page.

**Detail / infolist**

- **FR-EXT-006**: The detail view MUST present at least the following read-only sections: Booking summary, Vendors (with sub-status, response deadline, responded_at, rejection reason), Open modifications, State transitions (latest 20), Payments, Customer notes (`booking_customer_notes`), and Intervention history (`booking_admin_interventions`).
- **FR-EXT-007**: The detail view MUST NOT expose any field-edit affordance. All mutating operations go through the dedicated actions in FR-EXT-008.

**Actions (one Action class per use case, per `.claude/rules/actions.md`)**

- **FR-EXT-008**: System MUST provide six new Application Action classes under `app/Modules/Booking/Application/Actions/`, each with a single `execute()` method, constructor injection, and `DB::transaction` wrapping:
  - `SendVendorReminderAction` — queues a `notification_dispatches` row via `NotificationDispatcher` keyed to the vendor's primary user with event key `booking.vendor.reminder`, appends `BookingAdminIntervention` of type `vendor_reminder`, appends `audit_logs`, fires no state change. Throttled per `(booking_vendor_id, action=vendor_reminder)` for 5 minutes via the existing `idempotency_keys` table (scope = `admin.intervention.vendor_reminder`).
  - `EscalateLateVendorResponseAction` — guards `response_deadline + grace_period < now()`, mutates `booking_vendors.sub_status` to `VendorSubStatus::TimedOut`, appends `StateTransition` (transitionable = `BookingVendor`), creates `BookingAdminIntervention` of type `vendor_timeout`, creates `AdminInboxItem` with severity `medium`, fires the standard vendor-timeout domain event after commit, queues notification dispatch.
  - `SuggestAlternativeVendorsAction` — accepts an array of `vendor_profile_id`s (length 1..N, default `N = 5`), validates each candidate matches category, governorate coverage, product type, vendor approval, and availability for the booking window, creates one `BookingAdminIntervention` of type `vendor_proposal` with `proposed_vendor_id = NULL` and `after_state.suggested_vendor_ids = [...]`, queues a customer notification dispatch with event key `booking.alternatives.suggested`. MUST NOT create or modify any `booking_vendors` row.
  - `FreezeBookingChatAction` — guards thread is not already frozen, sets `chat_threads.frozen_at = now()` and `chat_threads.frozen_by = admin.id`, dispatches a Firestore mirror signal via the existing chat gateway contract, appends `BookingAdminIntervention` of type `chat_frozen`, appends audit log, queues `chat.frozen` notification dispatch to both customer and each affected vendor user.
  - `ResumeBookingReviewAction` — guards open modification exists and last reminder ≥ cooldown ago (default 4h), queues `booking.customer_review.reminder` notification dispatch to the customer, appends `BookingAdminIntervention` of type `customer_review_reminder`, appends audit log, MUST NOT change any state column.
  - `CreateAdminInterventionNoteAction` — appends `BookingAdminIntervention` of type `admin_note` with `reason` = note body (1..2000 chars), appends audit log, MUST NOT change any state column, MUST NOT dispatch any notification.
- **FR-EXT-009**: Every Action MUST fire its domain event using `DB::afterCommit(...)` (per `.claude/rules/actions.md`) and MUST NOT do any work after the transaction other than scheduling the post-commit dispatch.

**Hard boundary — admin cannot assign replacement vendor**

- **FR-EXT-010**: The codebase MUST NOT contain a class, controller method, Filament action, or route named `AssignReplacementVendorAction`, `assignReplacementVendor`, or semantically equivalent. The suggestion action is advisory only.
- **FR-EXT-011**: System MUST include a regression test that uses static reflection on the `App\Modules\Booking` namespace to assert no class name or method name matches the forbidden pattern, and that no Filament `Action` has the `assign_replacement_vendor` permission/action key. Test name: `it forbids admin from assigning a replacement vendor`.
- **FR-EXT-012**: System MUST include a policy `BookingAdminInterventionPolicy::create()` (or extend existing `BookingPolicy`) such that an intervention with `intervention_type = vendor_proposal` MUST have `proposed_vendor_id = NULL` enforced both at the Action layer (validation) and at the policy layer.

**Permissions, audit, notifications**

- **FR-EXT-013**: System MUST register the following Shield permissions (run `php artisan shield:generate --all` after the resource is added):
  - `booking.intervene.access`
  - `booking.intervene.send_vendor_reminder`
  - `booking.intervene.escalate_vendor_timeout`
  - `booking.intervene.suggest_alternative_vendors`
  - `booking.intervene.freeze_chat`
  - `booking.intervene.resume_customer_review`
  - `booking.intervene.create_note`
- **FR-EXT-014**: Every Action MUST append an `audit_logs` row (`auditable_type = Booking`, `auditable_id = booking.id`) with a meaningful `action` slug (`booking.vendor_reminder`, `booking.vendor_timeout`, `booking.suggest_alternatives`, `booking.chat_frozen`, `booking.chat_resumed`, `booking.customer_review_reminder`, `booking.admin_note`) and a `changes` JSON payload that includes the affected `booking_vendor_id` (where relevant) and the admin's reason.
- **FR-EXT-015**: Every reminder/escalation Action MUST either insert a `notification_dispatches` row via the `NotificationDispatcher` contract (`App\Modules\Communication\Domain\Contracts\NotificationDispatcher`) or queue a job that does so. No Action may bypass the dispatcher contract.

**Filament resource shape**

- **FR-EXT-016**: The Filament resource MUST live under `app/Modules/Booking/Filament/Resources/`, must auto-discover via the module provider, and must use only components listed in `.claude/rules/filament-components.md` (TextColumn, IconColumn, badges, SelectFilter, custom Action with form + confirmation, Infolist sections).
- **FR-EXT-017**: All admin-facing strings MUST come from translation files (EN + AR) under `app/Modules/Booking/Resources/lang/{en,ar}/booking.php` and `admin.php`. RTL must be respected for AR.
- **FR-EXT-018**: Money columns MUST use `->money('EGP', divideBy: 100)` and `product_type` columns MUST use the per-type colored badge convention from `.claude/rules/filament-components.md` §2.

**Configurable thresholds**

- **FR-EXT-019**: Stalled-threshold (default 48h), grace period after deadline (default 0), customer-review reminder cooldown (default 4h), and "suggest N max candidates" (default 5) MUST be read from `config/booking.php` under a new `intervention` key, not hardcoded.
  ⚠️ NEW CONFIG KEY — to be added to `config/booking.php`; no schema change.

**Testing**

- **FR-EXT-020**: System MUST provide Pest feature tests covering, at minimum:
  (a) page authorization — non-admin and admin-without-`booking.intervene.access` both blocked;
  (b) each Action's happy path including audit_log + intervention + dispatch row presence;
  (c) each Action's guard paths (deadline-not-passed, throttled reminder, double freeze, empty candidate list, oversize note);
  (d) the regression test from FR-EXT-011;
  (e) locale test for the detail view in AR;
  (f) per-product-type coverage for the listing query (rental / sale / digital).
  Tests must follow Pest grouping conventions (`->group('booking', 'intervention')`).

### Key Entities

- **Booking** (existing — `bookings` table per `11_DB_Schema.md`): the focus of the page.
- **BookingVendor** (existing — `booking_vendors`): provides `response_deadline`, `sub_status`, and is the target of the reminder, escalation, and (post-action) timeout mutation.
- **BookingModification** (existing — `booking_modifications`): used to detect "customer review pending".
- **BookingAdminIntervention** (existing — model already present at `app/Modules/Booking/Domain/Models/BookingAdminIntervention.php`): the durable record of every intervention. Used by all six Actions. ⚠️ MUST extend the existing `InterventionType` enum with new cases: `VendorReminder`, `ChatFrozen`, `ChatResumed`, `CustomerReviewReminder`. (Existing cases `ForceCancel`, `VendorTimeout`, `VendorProposal`, `AdminNote` are reused.)
- **AdminInboxItem** (existing — `app/Modules/Communication/Domain/Models/AdminInboxItem.php`): created by the escalation Action so the acting admin can track follow-ups.
- **NotificationDispatch** (existing — `notification_dispatches`): inserted via the `NotificationDispatcher` contract for every reminder, escalation, suggestion, freeze, resume, and customer-review reminder.
- **StateTransition** (existing — polymorphic `state_transitions`): appended by the escalation Action with `transitionable_type = BookingVendor` and `trigger_kind = admin`.
- **AuditLog** (existing — `audit_logs`): appended by every Action.
- **IdempotencyKey** (existing — `idempotency_keys`): used by `SendVendorReminderAction` and `ResumeBookingReviewAction` to throttle per-target reminders.
- **ChatThread** (existing — `chat_threads` MySQL audit mirror of the Firestore conversation): receives `frozen_at` / `frozen_by` toggles from the freeze/resume actions.
  ⚠️ SCHEMA CHECK: confirm `chat_threads` already has nullable `frozen_at` and `frozen_by` columns; if not, add them in this feature's migration.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: From a cold session, an admin can identify and act on the oldest stalled booking in under 90 seconds (open the page, pick the top-sorted row, send a reminder or escalate).
- **SC-002**: Page first-paint with 100 trouble bookings completes in under 1.5 seconds on the standard admin browser profile; sorting/filtering re-renders in under 800 ms.
- **SC-003**: Vendor reminder dispatches reach the vendor inbox within 1 minute of the admin clicking the action 99% of the time, measured against existing `notification_dispatches.sent_at` metrics.
- **SC-004**: After this page ships, time-to-resolution for stalled bookings (measured from `submitted_at` to either `confirmed` or `cancelled`) drops by at least 30% versus the prior 30-day baseline.
- **SC-005**: Zero "ghost" replacement assignments — over a 90-day audit window, no `booking_vendors` row exists whose creator is an admin user; verified by query and by the FR-EXT-011 regression test in CI.
- **SC-006**: Every intervention has a matching `audit_logs` row — verified by a nightly reconciliation that joins `booking_admin_interventions` to `audit_logs` and reports any orphans (target: 0).
- **SC-007**: Each of the four trouble buckets is exercised at least once a week in production within the first 30 days — confirms the page is the operational tool, not just a dashboard.

## Assumptions

- The existing `BookingAdminIntervention` model and its 4-case `InterventionType` enum will be extended (4 new cases) rather than replaced; existing data is compatible.
- The existing `NotificationDispatcher` contract is the only path to push customer/vendor notifications; no new transport mechanism is introduced.
- "Stalled" is a derived attribute (no new column on `bookings`). All Phase 1 stalled detection happens at query time with appropriate indexes already present per `11_DB_Schema.md`.
- The chat thread freeze persists the flag both in MySQL (`chat_threads.frozen_at`) and pushes it to Firestore via the existing chat gateway; if `chat_threads` is currently a write-only audit mirror, this feature adds the flag columns within scope.
- The "customer remains the final selector" constraint is enforced both by the absence of a replacement-assignment Action and by the policy check on `vendor_proposal` interventions; the customer-side acceptance flow that lets a user pick from suggestions is OUT OF SCOPE for this spec and is handled by the existing customer rebooking flow.
- Phase alignment: this work belongs to Phase 6 (Reporting + Audit + Admin tools) per `09_Phasing_Plan.md`. ⚠️ PHASE BACKFILL NEEDED — `09_Phasing_Plan.md` does not enumerate this page; add it explicitly to Phase 6 admin tooling.
- This feature ships as Filament-only; no public HTTP API endpoints are added. The existing `BookingInterventionController::forceCancel` endpoint is untouched.
- No new third-party package is required. All work uses packages already listed in `docs/specs/10_Package_List.md` (Filament v3, Shield, Spatie translatable, Spatie permission, the existing notification stack).
- Permission grants for the new Shield abilities are added to the admin role seeder in the same PR; no UI for permission management is built here.
- The page is admin-only. There is no customer-facing or vendor-facing component beyond the notifications the existing dispatch system already produces.
