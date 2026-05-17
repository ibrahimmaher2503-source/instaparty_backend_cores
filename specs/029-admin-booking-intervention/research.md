# Phase 0 Research — Admin Booking Intervention Page

> Resolves every NEEDS-CLARIFICATION-equivalent question from `plan.md` and locks down the design choices that Phase 1 (data-model + contracts) depends on.

---

## R-1 — Does the `chat_threads` table exist yet, and does it have nullable `frozen_at` / `frozen_by` columns?

**Decision**: Treat `chat_threads` as a soft dependency. If a migration creating `chat_threads` does not exist in `app/Modules/Communication/Database/Migrations/` at the start of implementation, defer `FreezeBookingChatAction` + `ResumeBookingChatAction` to the cut-list and add a single migration in this feature only when the base table is in place.

**Rationale**:
- `11_DB_Schema.md` lists `chat_threads` (Firestore audit mirror) in the Communication module, but a search of the actual migration tree found `notification_templates`, `notification_dispatches`, `notification_preferences`, `admin_inbox_routing_rules`, and `admin_inbox_items` only — no `chat_threads` migration yet.
- Creating the base `chat_threads` table inside the Booking feature would breach module ownership (Communication owns chat) and force a coupled migration that is out of this feature's scope.
- Two of the six required Actions depend on this table. Five do not. Shipping the five is far more valuable than blocking the whole page.

**Alternatives considered**:
- Create the `chat_threads` table inside this feature's migrations — **rejected** for breaking module ownership and bloating PR scope.
- Add `frozen_at` columns to `bookings` instead — **rejected** because the canonical chat is in Firestore; mirror columns belong on `chat_threads`.
- Skip chat freeze entirely — **rejected** because the user input explicitly requested it; deferral preserves the requirement.

**Implementation note**:
- Preflight check in `/speckit.implement`: `git grep -l create_chat_threads_table app/Modules/Communication/Database/Migrations`. If empty, comment out the two freeze Actions in `AdminBookingInterventionResource` and skip the `add_frozen_to_chat_threads` migration.
- Migration filename (when ready): `2026_05_15_100002_add_frozen_to_chat_threads.php` — adds nullable `frozen_at TIMESTAMP NULL`, `frozen_by BIGINT UNSIGNED NULL` (FK → `users.id` with `nullOnDelete`).

---

## R-2 — Throttling: `idempotency_keys` table vs. cache lock vs. column-level `last_reminder_at`?

**Decision**: Use the existing `idempotency_keys` table with per-Action `scope` strings: `admin.intervention.vendor_reminder` (TTL = 5 min, key = `booking_vendor_id`) and `admin.intervention.customer_review_reminder` (TTL = 4 h, key = `booking_id`). Reject duplicates by reading-then-inserting inside the Action's `DB::transaction`.

**Rationale**:
- The table already exists (Phase 4 / payments) and supports a `scope` + `ttl_seconds` model per the schema cheatsheet's recent additions (post-028 ledger hardening).
- It is durable across worker restarts (Redis cache lock is not, in dev profiles).
- Constitution principle VIII (idempotency) already mandates the table — reusing it costs nothing.
- A new column on `booking_vendors` or `booking_modifications` would add stateful baggage to records that are otherwise authoritative for vendor + modification flow only.

**Alternatives considered**:
- Redis `Cache::lock(...)` — **rejected** because lock TTL behavior changes when Redis is flushed, and audit-after-the-fact is harder.
- Add `last_reminded_at` column to `booking_vendors` — **rejected** because reminders are a cross-cutting admin action, not part of the vendor's authoritative record.

**Implementation note**:
- Key construction: `hash('xxh64', "{$scope}:{$bookingVendorId}")` for vendor reminder; `hash('xxh64', "{$scope}:{$bookingId}")` for customer review reminder.
- The row is inserted with `expires_at = now() + ttl_seconds`. A SELECT before INSERT (or INSERT IGNORE + check rows-affected) detects the duplicate; throw `\DomainException(__('booking.intervention.throttled'))` on conflict.

---

## R-3 — Where do `BookingVendor` state transitions go in the `state_transitions` table — and is the polymorphic schema ready?

**Decision**: Write the escalation transition as a polymorphic row with `transitionable_type = App\Modules\Booking\Domain\Models\BookingVendor`, `transitionable_id = bookingVendor.id`, `from_state = sub_status_before`, `to_state = 'timed_out'`, `trigger_kind = 'admin'`, `triggered_by = admin.id`, `context = {"intervention_id": ..., "reason": ...}`. The existing `state_transitions` table from Phase 6.5 already supports polymorphic transitionables; the schema matches `ForceCancelBookingAction`'s usage pattern.

**Rationale**:
- The existing `ForceCancelBookingAction` writes to the same table with `transitionable_type = Booking::class`. Reusing the table for `BookingVendor` transitions matches the documented polymorphic design and avoids a sub-status-specific history table.
- The polymorphic morph map already routes `App\Modules\Booking\Domain\Models\BookingVendor` (verified via `BookingServiceProvider`).
- Append-only — fits constitution principle V.

**Alternatives considered**:
- Dedicated `booking_vendor_state_transitions` table — **rejected** as schema bloat; polymorphic ledger is the canonical pattern.
- Write only to `booking_admin_interventions` and skip the state-transition row — **rejected** because the timing transition is a legitimate state machine move and other listeners (vendor analytics, possibly future SLA dashboards) expect it.

---

## R-4 — Reuse existing `VendorResponseDeadlineExceeded` event, or coin `BookingVendorTimedOut`?

**Decision**: Search the codebase for any existing `VendorResponseDeadlineExceeded` (or near-synonym) event during implementation. If present, reuse it. If absent, create `App\Modules\Booking\Domain\Events\BookingVendorTimedOut` with payload `(int $bookingVendorId, int $bookingId, ?int $triggeredByAdminId, string $reason)`.

**Rationale**:
- Phase 3 / 6.5 may already have an automatic timeout job that fires the same event when a scheduled task expires deadlines. If so, the admin escalation should reuse it to keep listeners (analytics, audit, customer notification) wired exactly once.
- If no such event exists, naming the new event `BookingVendorTimedOut` (past tense, matching `BookingForceCancelled`'s convention) keeps the event vocabulary consistent.

**Alternatives considered**:
- Always create a new event — **rejected** because it forks the listener wiring and risks double-firing customer notifications.
- Always reuse an event — **rejected** because we cannot assume it exists yet; the conditional decision keeps both paths safe.

**Implementation note**:
- Preflight: `grep -r "class VendorResponseDeadlineExceeded\|class BookingVendorTimedOut\|class VendorTimedOut" app/Modules/Booking/Domain/Events/`. Document the choice in the implementation commit message.

---

## R-5 — Candidate query for `SuggestAlternativeVendorsAction`: build inline, or call a Discovery contract?

**Decision**: Introduce a thin read contract `App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder` with `findCandidates(Booking $booking, ?int $limit = 5): Collection<VendorProfile>` implemented by `EloquentAlternativeVendorFinder` in `app/Modules/Discovery/Infrastructure/Repositories/`. The Booking Action depends on the contract, not on the implementation.

**Rationale**:
- Discovery already owns vendor / service browse queries; the admin candidate filter is the same shape with extra constraints (approved-for-type, governorate coverage, availability for the event window).
- A contract keeps the cross-module boundary clean (constitution §I).
- It also makes the candidate logic unit-testable in isolation from the Action.

**Filter criteria** (all AND-ed):
1. `vendor_profiles.approval_status = 'approved'` AND `deleted_at IS NULL`
2. Joined `vendor_approved_product_types` row exists for the booking's `product_type`
3. `vendor_coverage_areas` includes the booking's governorate (resolved via `booking_addresses.governorate_id`)
4. Vendor has at least one published service in the same category as the rejected vendor's category (joined via `services.category_id`)
5. No `service_availability_blocks` or `service_excluded_dates` overlap the booking's event window
6. Exclude the originally-rejecting vendor and any vendor already in `booking_vendors` for this booking
7. Sort by: published-services-count DESC, then `vendor_profiles.rating_average` DESC, then ID ASC
8. Limit configurable per `config('booking.intervention.suggest_max_candidates', 5)`

**Alternatives considered**:
- Inline the query inside the Action — **rejected** for breaking cross-module boundary (Booking would directly join `services`, `vendor_coverage_areas` from Catalog + Identity).
- Reuse `SearchVendorsAction` from Discovery — **rejected** because that path serves customer-facing search with Meilisearch; here we need a deterministic SQL filter, no fuzzy ranking.

---

## R-6 — `AdminInboxItem` write path: direct model use or contract?

**Decision**: Introduce `App\Modules\Communication\Domain\Contracts\AdminInboxWriter` with `create(string $sourceType, int $sourceId, AdminInboxSeverity $severity, array $title, array $body, ?int $assignedToAdminId = null): AdminInboxItem`. Booking listeners depend on this contract.

**Rationale**:
- Cross-module imports of `AdminInboxItem` from Booking listeners would violate constitution §I and trip `NoCrossModuleModelImportsTest`.
- The contract is thin (5-arg create) and idiomatic to the existing Identity / Catalog cross-module bindings.

**Implementation note**:
- Binding registered in `CommunicationServiceProvider::register()`.
- Existing `AdminInboxResource` is unaffected.

---

## R-7 — Notification template event keys: which ones already exist, which are new?

**Decision**: Audit `notification_templates` for existing event keys; reuse where possible, seed new rows otherwise. Required keys:

| Event key | Source action | Audience | EN / AR rows needed? |
|---|---|---|---|
| `booking.vendor.reminder` | `SendVendorReminderAction` | Vendor (primary user) | Yes — seed both |
| `booking.vendor.timed_out_by_admin` | `EscalateLateVendorResponseAction` (vendor side) | Vendor | Likely new — seed |
| `booking.vendor.timed_out_by_admin.customer` | same Action (customer side) | Customer | Likely new — seed |
| `booking.alternatives.suggested` | `SuggestAlternativeVendorsAction` | Customer | New — seed |
| `booking.chat.frozen` | `FreezeBookingChatAction` | Customer + Vendor | New — seed (gated) |
| `booking.chat.resumed` | `ResumeBookingChatAction` | Customer + Vendor | New — seed (gated) |
| `booking.customer_review.reminder` | `ResumeBookingReviewAction` | Customer | New — seed |

**Rationale**:
- `notification_templates` already enforces `(event_key, channel, audience)` UNIQUE per the schema cheatsheet; we cannot accidentally duplicate.
- Seeding via a dedicated migration (`2026_05_15_100001_seed_intervention_notification_templates.php`) keeps the rows reproducible and version-controlled.

**Alternatives considered**:
- Lazy-create templates on first dispatch — **rejected** because `NotificationDispatcher` expects pre-seeded templates and would otherwise throw a `TemplateNotFound` error.

---

## R-8 — Filament resource shape: full Resource + Pages, or a single custom Page?

**Decision**: Full `AdminBookingInterventionResource` with `ListBookingInterventions` and `ViewBookingIntervention` pages. The resource is read-only (`canCreate = false`, no `EditAction`), exposes its custom Actions on the row, and uses a custom Eloquent query that joins/derives the four trouble buckets.

**Rationale**:
- Filament Resource gives free pagination, sortable columns, filters, search, badges, and per-row Actions — exactly the surface we need.
- A custom `Page` would require re-implementing pagination + filters from scratch.
- Read-only + custom Actions is a documented Filament v3 pattern (see `.claude/rules/filament-components.md` §2 "Custom action convention" and `BookingsMonitorResource.php`).

**Alternatives considered**:
- Reuse `BookingsMonitorResource` and add the six Actions there — **rejected** because that resource is scoped to `vendor_review` + `customer_review` lifecycle states only; our trouble-bucket query is broader (stalled, rejected by all vendors, late deadline) and adding it would dilute that resource's intent.

---

## R-9 — Permissions: piggy-back on `force_cancel_booking` or split granular?

**Decision**: Split granular per Action — one Shield permission each (`booking.intervene.send_vendor_reminder`, `booking.intervene.escalate_vendor_timeout`, etc.) plus a master `booking.intervene.access` gate for the page itself. Run `php artisan shield:generate --all` and grant the bundle to the `admin` role in the seeder.

**Rationale**:
- Lets us delegate "send reminder" to junior ops without unlocking force-cancel.
- Filament Action `visible()` closures consume permissions directly.
- Cost is one seeder edit + seven permission rows — negligible.

**Alternatives considered**:
- One omnibus `booking.intervene` permission — **rejected** because it would force "give junior staff everything or nothing."

---

## R-10 — Test database state: how do we seed bookings into each trouble bucket?

**Decision**: Add four factory states on `BookingFactory`: `state(fn () => [...])->stalledWithLateVendor()`, `->withAllVendorsRejected()`, `->withCustomerReviewPending()`, `->stalled()`. Each composes existing relationships and sets timestamps.

**Rationale**:
- The Pest tests in `tests/Feature/Modules/Booking/AdminIntervention/AdminBookingInterventionPageTest.php` need deterministic fixtures for the listing query.
- Reusable factory states keep test setup short and shared with the listing-page test, the per-bucket filter test, and the per-product-type triplet.

**Alternatives considered**:
- Inline SQL setup in each test — **rejected** for brittleness and duplication.

---

## Open questions deferred to `/speckit.tasks`

None. All design decisions are locked.
