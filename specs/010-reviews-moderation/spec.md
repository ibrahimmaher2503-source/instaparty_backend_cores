# Feature Specification: Reviews + Moderation

**Phase ID**: 5.1 — Reviews (1 day, Week 6) — see [`docs/specs/09_Phasing_Plan.md`](../../docs/specs/09_Phasing_Plan.md) §Phase 5.1
**Feature Branch**: `010-reviews-moderation`
**Created**: 2026-05-03
**Status**: Draft
**ADR**: [`ADR-0011 — Reviews Module`](../../docs/adr/0011-reviews-module.md) (Accepted)
**Input**: User description: *"Customer rates service + vendor after booking complete. Admin moderates → service/vendor rating averages update."*

---

## Phase Context

| Field | Value |
|---|---|
| **Phase** | 5.1 — Reviews |
| **Week / Days** | Week 6, Day 1 (1 day) |
| **Goal** | Customer rates service + vendor after a booking item / booking vendor completes; admin moderates pending reviews; approved reviews update aggregate ratings. |
| **Tables touched** | `service_reviews`, `vendor_reviews`, `review_responses`, `review_moderation_log` (per [`11_DB_Schema.md`](../../docs/specs/11_DB_Schema.md) §10) |
| **PRD coverage** | [`01_PRD.md`](../../docs/specs/01_PRD.md) §1 (review capability in product overview), §6.3 step 7 (admin moderates reviews), §5.1 (reviews included in Phase 1 scope). The PRD's numbered FR-1…FR-30 do not enumerate reviews explicitly — they live in the Software Description and the customer/admin journey diagrams. ([`05_Software_Description.md`](../../docs/specs/05_Software_Description.md), [`06_Customer_Journey.md`](../../docs/specs/06_Customer_Journey.md) line 16, [`08_Admin_Journey.md`](../../docs/specs/08_Admin_Journey.md) line 228) |
| **Module** | New module `app/Modules/Reviews/` (per ADR-0011) |
| **Blocks** | None — independent of booking flow once `booking_items.item_status = 'completed'` is reachable |
| **Depends on** | Phase 1.x Identity (users, customer_profiles, vendor_profiles), Phase 2.x Catalog (services), Phase 3.x Booking (booking_items, booking_vendors with `completed` lifecycle), Phase 5.0 Communication (optional — for moderation notifications) |

---

## Clarifications

### Session 2026-05-03

- Q: When admin rejects a review, can the customer resubmit a new review for the same `booking_item` / `booking_vendor`? → A: **Rejection is terminal**; the customer cannot self-service resubmit. Support can intervene manually (`hidden→approved` or soft-delete) for true edge cases.
- Q: Is `approved → rejected` a valid moderation transition? → A: **Yes** — direct `approved → rejected` is allowed as an irreversible takedown for severe violations (fraud, threats, doxxing). Canonical state machine: `pending → {approved, rejected}`, `approved ↔ hidden`, `approved → rejected`, `hidden → approved`. Once `rejected`, terminal.
- Q: How is the reviewer identified on public review listings? → A: **First name only** (e.g., "Ahmed", "ياسمين"). No surname, no last initial, no avatar. Pulled from `users.first_name` (or equivalent — confirm exact column in plan).
- Q: Is `Idempotency-Key` required or optional on review submission endpoints? → A: **Optional but supported**. The DB UNIQUE constraint on `booking_item_id` / `booking_vendor_id` is the primary deduplication safeguard. When the header is present, the standard 24h replay middleware honors it; when absent, the request proceeds normally and any true duplicate surfaces as a 409.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Customer submits a service review (Priority: P1)

After a customer's `booking_item` reaches `completed` status, the customer wants to share their experience by leaving a 1–5 star rating and optional written feedback. This is the primary review path because it directly informs future buyers and powers the average rating shown on every service detail page.

**Why this priority**: Without this story, the platform cannot collect any structured feedback, breaking the trust loop the marketplace depends on. The MVP Phase 1 cannot ship without service-level reviews.

**Independent Test**: Seed a customer + a vendor + a service + a `booking_item` with `item_status='completed'`. Submit a review with a 5-star rating and a body. Verify a `service_reviews` row exists with `moderation_status='pending'`. Verify the customer cannot submit a second review for the same `booking_item`.

**Acceptance Scenarios**:

1. **Given** a customer with a completed `booking_item` for a rental service and no existing review for it, **When** the customer submits a review with `rating=5` and a non-empty body, **Then** the system creates a `service_reviews` row with `moderation_status='pending'` and returns the created review (with its public ID and pending status).
2. **Given** a customer with a completed `booking_item` for a sale service, **When** the customer submits a review, **Then** the same flow succeeds — eligibility is judged on the `completed` lifecycle marker, not the product type.
3. **Given** a customer with a completed `booking_item` for a digital service, **When** the customer submits a review, **Then** the same flow succeeds.
4. **Given** a customer with a `booking_item` still in `vendor_review` (not completed), **When** the customer attempts to submit a review, **Then** the system rejects the request with a clear error stating the booking item must be completed before review.
5. **Given** a customer who already submitted a review for `booking_item` X, **When** they attempt to submit another review for the same `booking_item`, **Then** the system rejects with a "review already exists" error and does not create a duplicate row.
6. **Given** a customer who is not the booking owner of `booking_item` X, **When** they attempt to submit a review for `booking_item` X, **Then** the system rejects with an authorization error.
7. **Given** the customer's preferred locale is `ar`, **When** they submit a review without explicit locale, **Then** the persisted `locale` value is `ar`.

---

### User Story 2 — Customer submits a vendor review (Priority: P1)

In addition to per-service reviews, the customer wants to rate the vendor as a whole — covering communication, professionalism, and overall delivery — once **all** of that vendor's items in the booking are completed.

**Why this priority**: Vendor-level ratings drive vendor profile credibility and discovery ranking. They are equal-weight to service reviews per the locked schema decision in [`11_DB_Schema.md`](../../docs/specs/11_DB_Schema.md) §1 ("Reviews: BOTH `service_reviews` + `vendor_reviews`").

**Independent Test**: Seed a customer + a vendor + 2 services + a booking with 2 `booking_items` for that vendor, both `completed`. Submit a vendor review tied to the `booking_vendor`. Verify a `vendor_reviews` row exists with `moderation_status='pending'`. Verify a second submission for the same `booking_vendor` is blocked.

**Acceptance Scenarios**:

1. **Given** a `booking_vendor` whose every constituent `booking_item` has `item_status='completed'`, **When** the customer submits a vendor review with `rating=4` and a body, **Then** the system creates a `vendor_reviews` row with `moderation_status='pending'`.
2. **Given** a `booking_vendor` where at least one `booking_item` is not yet completed, **When** the customer attempts to submit a vendor review, **Then** the system rejects with a clear "all items must be completed" error.
3. **Given** the customer already submitted a vendor review for `booking_vendor` X, **When** they attempt a second submission for the same `booking_vendor`, **Then** the system rejects with a "review already exists" error.
4. **Given** a customer who is not the booking owner, **When** they attempt to submit a vendor review for that `booking_vendor`, **Then** the system rejects with an authorization error.

---

### User Story 3 — Admin moderates a pending review (Priority: P1)

An admin or moderator opens the review moderation queue, reads each pending submission, and decides whether to approve (publish to the public service/vendor page), reject (block from publishing), or hide (take down a previously approved review). Every decision is recorded to an append-only audit log.

**Why this priority**: Without moderation, abusive, fake, or off-topic reviews would publish immediately, eroding trust. The Admin Journey ([`08_Admin_Journey.md`](../../docs/specs/08_Admin_Journey.md) step 26) explicitly mandates this gate.

**Independent Test**: Seed 3 pending reviews (mix of service + vendor). As an admin, open the moderation page, approve one, reject one (with a reason), hide one (already-approved fixture). Verify each transition wrote a `review_moderation_log` row and that approved reviews now appear in the public listing while rejected/hidden reviews do not.

**Acceptance Scenarios**:

1. **Given** a `service_reviews` row with `moderation_status='pending'`, **When** an admin approves it, **Then** the row's `moderation_status` becomes `approved`, `moderated_by` is set to the admin's user ID, `moderated_at` is set, and a `review_moderation_log` row is appended (`from_status='pending'`, `to_status='approved'`, `moderator_id=admin.id`).
2. **Given** a pending review, **When** an admin rejects it with a reason, **Then** the row becomes `rejected` and the reason is captured (translatable JSON) on `review_moderation_log.reason`.
3. **Given** a previously approved `vendor_reviews` row, **When** an admin hides it, **Then** the row becomes `hidden`, the public listing no longer includes it, and the vendor's average rating is recomputed to exclude it.
4. **Given** a review approval, **When** the moderation transaction commits, **Then** a `ReviewApproved` event fires (after commit) and a queued listener recomputes the parent service or vendor's average rating.
5. **Given** a non-admin user (customer, vendor), **When** they call the moderation endpoint or open the moderation page, **Then** the system returns 403 / hides the page.

---

### User Story 4 — Public viewer reads approved reviews on a service or vendor (Priority: P2)

A prospective customer browsing a service detail page or vendor profile sees only approved reviews, ordered most-recent first, with rating, body (in their current locale), and submission date — no PII beyond the reviewer's display name initial.

**Why this priority**: Reviews are useless to the marketplace if they're collected but never displayed. P2 because the read-side UI consumption may be served from Catalog/Discovery, but the data contract must exist in this phase.

**Independent Test**: Seed 5 approved + 2 pending + 1 rejected service reviews on a service. Hit the public list endpoint. Verify only the 5 approved are returned, ordered by `created_at DESC`, with translatable body returned in `Accept-Language` and reviewer first name (only) in each item.

**Acceptance Scenarios**:

1. **Given** a service with 5 approved + 2 pending + 1 rejected reviews, **When** any user (auth or guest) requests the public review list for that service, **Then** the response contains exactly the 5 approved reviews.
2. **Given** the request includes `Accept-Language: en`, **When** the response is built, **Then** any translatable error or label messages are in English; the review body is returned as the original text plus its `locale` value (so the client can render direction hints).
3. **Given** pagination is requested, **When** the list endpoint is called with a cursor, **Then** the response uses the standard cursor-pagination envelope.

---

### User Story 5 — Vendor responds to a review (Priority: P3 — DEFERRED)

A vendor wants to publicly reply to a customer's review (e.g., to clarify, apologize, or thank). **This story is in the cut-list and deferred to Phase 6.0** — only the database schema (`review_responses`) and minimal placeholder action are scaffolded in 5.1.

**Why this priority**: Defers per the phase plan to keep 5.1 to a single day. The schema is built so Phase 6.0 can wire the endpoint without migrations.

**Acceptance Scenarios** (informational only — not implemented in 5.1):

1. **Given** a vendor whose service or vendor profile received an approved review, **When** the vendor submits a response (in Phase 6.0), **Then** a `review_responses` row is created and enters its own moderation flow.

---

### Edge Cases

- **Review body empty / null** — body is optional per schema (`TEXT YES`); a review with no body must still be valid as long as `rating` is 1–5.
- **Review body with `<script>` or HTML** — body is stored as plain text and rendered escaped on consumer surfaces. No HTML/markdown parsing in 5.1.
- **Customer deletes their account before moderation** — soft-delete on `service_reviews` / `vendor_reviews` allows their review to be soft-deleted alongside the user; pending reviews from a deleted user are excluded from the moderation queue.
- **Soft-deleted booking_item** — if the underlying `booking_item` is soft-deleted after the review is submitted, the review remains. If it's soft-deleted **before** review submission, the review submission is rejected (the eligibility reader treats soft-deleted as not eligible).
- **Rapid duplicate submission (race)** — two simultaneous submissions for the same `booking_item_id` rely on the DB UNIQUE constraint to surface as a 409 on the loser; the first wins.
- **`hidden → approved` reinstate** — admin can reinstate a hidden review (transition `hidden → approved`), logged in `review_moderation_log` and triggers re-aggregation. ENUM allows it.
- **Booking refunded after review** — out of scope for 5.1 eligibility; the review remains valid. Phase 6.0 may revisit whether refunded items can have reviews suppressed.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-R1**: Customers MUST be able to submit a service review tied to a `booking_item` only when the underlying `booking_item.item_status = 'completed'`.
- **FR-R2**: Customers MUST be able to submit a vendor review tied to a `booking_vendor` only when **every** `booking_item` belonging to that `booking_vendor` is in `item_status = 'completed'`.
- **FR-R3**: The system MUST enforce exactly one service review per `booking_item_id` (DB UNIQUE) and one vendor review per `booking_vendor_id` (DB UNIQUE), surfaced as a clear 409 conflict error to the customer. A `rejected` review counts toward this uniqueness — **rejection is terminal**: the customer cannot self-service resubmit a new review for the same `booking_item` / `booking_vendor` after a rejection. Support / admin may intervene manually (e.g., soft-delete the rejected row to free the slot) only as an exceptional override.
- **FR-R4**: The system MUST validate `rating` as an integer in the range 1–5 inclusive and reject with 422 otherwise.
- **FR-R5**: The system MUST persist the review's `locale` from `customer_profiles.preferred_locale` at submission time (deferring auto-detection per cut-list); ENUM values are `ar`, `en`, `mixed`.
- **FR-R6**: The system MUST submit every review with `moderation_status='pending'` — no auto-approval in Phase 1.
- **FR-R7**: Admins (and users with the `moderate_service_review` / `moderate_vendor_review` permissions) MUST be able to drive the canonical moderation state machine: `pending → approved`, `pending → rejected`, `approved → hidden`, `hidden → approved`, and `approved → rejected`. The `rejected` state is **terminal** — no transitions out of it via self-service moderation actions.
- **FR-R8**: Every moderation transition MUST append a row to `review_moderation_log` capturing `from_status`, `to_status`, `moderator_id`, optional `reason` (translatable JSON), and `created_at`. This table is append-only — no updates, no deletes.
- **FR-R9**: On `ReviewApproved` (or any transition that changes the visible rating set: approved → hidden, hidden → approved, approved → rejected), the system MUST recompute the parent service's or vendor's `average_rating` (and review count) **after** the moderation transaction commits, via a queued listener.
- **FR-R10**: The system MUST expose a public listing of approved reviews per service and per vendor, ordered `created_at DESC`, with cursor pagination. Pending, rejected, hidden, and soft-deleted reviews MUST NOT appear in public listings. Reviewer identity in the public payload is **first name only** (no surname, no last initial, no avatar) — sourced from the user's profile. If the reviewer's first name is missing or empty, the system MUST fall back to a localized "Verified Customer" label rather than expose any other PII.
- **FR-R11**: All API responses returning translatable content (error messages, moderation reasons) MUST be returned in the requested `Accept-Language` (defaulting to `ar`). The review body itself is returned verbatim in its stored language with the `locale` field exposed.
- **FR-R12**: Customers MUST be able to read a list of reviews they have authored (across services and vendors), regardless of moderation status (so they can see their own pending/rejected submissions).
- **FR-R13**: Reviews submitted by a customer who is later soft-deleted MUST be excluded from public listings and from rating aggregation.
- **FR-R14**: Customers MUST be able to soft-delete their own review (Phase 1 supports this via the soft-delete column); on customer-initiated deletion, the rating aggregation MUST be recomputed to exclude the deleted review.
- **FR-R15**: The Reviews module MUST NOT import Eloquent models from Booking, Catalog, or Identity; cross-module reads MUST go through public Contracts.

### Non-Functional Requirements

- **NFR-R1**: Review submission p95 latency under 300 ms (excluding queued aggregation work).
- **NFR-R2**: Moderation queue page must load up to 100 pending reviews in under 1 s.
- **NFR-R3**: Rating aggregation listener must complete recomputation within 30 s of the `ReviewApproved` event under normal queue depth.
- **NFR-R4**: Architecture test (`tests/Architecture/ReviewsModuleNoCrossImportTest.php`) must pass on every push.

### Key Entities

- **ServiceReview** — One per completed `booking_item`. Carries rating (1–5), body (TEXT), locale, moderation_status, moderation audit pointers (moderated_by, moderated_at). Soft-deletable by the customer.
- **VendorReview** — Same shape as ServiceReview but tied to `booking_vendor_id`.
- **ReviewResponse** — Polymorphic by `review_type` (`service` | `vendor`). Schema only in 5.1; submission deferred to Phase 6.0.
- **ReviewModerationLog** — Append-only audit row per moderation transition. Polymorphic by `review_type`.
- **Domain events** — `ReviewSubmitted`, `ReviewApproved`, `ReviewRejected`, `ReviewHidden`, `ServiceRatingRecomputed`, `VendorRatingRecomputed`.

---

## API Endpoints

| Method | Path | Auth | Roles | Purpose |
|---|---|---|---|---|
| `POST` | `/api/v1/customer/booking-items/{bookingItemPublicId}/review` | sanctum-token | `customer` | Submit a service review for a completed booking item |
| `POST` | `/api/v1/customer/booking-vendors/{bookingVendorPublicId}/review` | sanctum-token | `customer` | Submit a vendor review for a fully completed booking-vendor unit |
| `GET` | `/api/v1/customer/reviews` | sanctum-token | `customer` | List the authenticated customer's own reviews (any moderation status) |
| `DELETE` | `/api/v1/customer/reviews/{reviewPublicId}` | sanctum-token | `customer` | Soft-delete the authenticated customer's own review |
| `GET` | `/api/v1/public/services/{servicePublicId}/reviews` | none | — | Public list of approved service reviews (cursor-paginated) |
| `GET` | `/api/v1/public/vendors/{vendorPublicId}/reviews` | none | — | Public list of approved vendor reviews (cursor-paginated) |
| `GET` | `/api/v1/public/services/{servicePublicId}/rating-summary` | none | — | Aggregated rating + count for a service (read from listener-maintained columns) |
| `GET` | `/api/v1/public/vendors/{vendorPublicId}/rating-summary` | none | — | Aggregated rating + count for a vendor |

> Filament admin moderation is **page-based** (`ReviewModerationPage`) and not exposed as a public REST endpoint in Phase 5.1.

**Standards (per [`api-registry.md`](../../.specify/memory/api-registry.md)):**

- All responses use the `{ data, meta, errors }` envelope.
- All path parameters are CHAR(26) ULID `public_id`.
- All translatable error messages localize via `Accept-Language` (default `ar`).
- All state-changing endpoints (`POST`, `DELETE`) accept an `Idempotency-Key` header. **For review submission this header is optional** (DB UNIQUE handles dedup); when present, standard 24h replay semantics apply.
- All timestamps are ISO 8601 UTC.

---

## Constitution Check

Per [`.specify/memory/constitution.md`](../../.specify/memory/constitution.md) §Core Principles I–XI:

| # | Principle | Status | Reasoning |
|---|---|---|---|
| **I** | Modular monolith — module boundaries, no cross-module model imports | ✅ Pass | New `app/Modules/Reviews/` follows the layer layout. Cross-module reads (booking eligibility, service/vendor existence) go through `Booking\Domain\Contracts\BookingItemReader` and `Reviews\Domain\Contracts\ReviewRepository`. Architecture test is mandated. |
| **II** | Three product types — `match($enum)`, no if/elseif | ✅ Pass | The Reviews submission/moderation flow is **cross-type** (same shape regardless of product). No per-type Form Requests, Actions, or Resources. The product-type context is carried via `booking_item.product_type` for downstream consumers. Eligibility tests cover all three types because the `booking_item` state machine is per-type. |
| **III** | Money discipline — integer minor units, Brick\Money | ✅ Pass | This phase introduces no money columns. (Reviews carry `rating` TINYINT, not currency.) |
| **IV** | Bilingual EN+AR mandatory | ✅ Pass | `review_moderation_log.reason` is translatable JSON. UI labels and validation messages live in `Resources/lang/{en,ar}/reviews.php`. The review **body** is single-language (the customer wrote it once in their preferred locale) — `locale` ENUM is exposed on the response so clients can render direction. Tests must assert both EN and AR error/locale paths. |
| **V** | Append-only tables — no softDeletes | ✅ Pass | `review_moderation_log` is append-only (no `updated_at`, no `deleted_at`, no UPDATE). `service_reviews` and `vendor_reviews` use soft-delete per schema. `review_responses` uses standard timestamps (mutable status only). |
| **VI** | Spec-driven — ADR before code | ✅ Pass | ADR-0011 created and Accepted (2026-05-03) before any migration is written. ADR enumerates ownership, contracts, internal decisions, and cut-list. |
| **VII** | Test-first for critical paths | ✅ Pass | Pest tests planned for: eligibility (per product type), uniqueness constraint, moderation transitions, listener-driven aggregation, RBAC, locale, idempotency. Architecture test asserts no cross-module model imports. Tests written same day as code. |
| **VIII** | Idempotency for state-changing endpoints | ✅ Pass | `POST /booking-items/{id}/review` and `POST /booking-vendors/{id}/review` **honor `Idempotency-Key` when present (optional, not required)**: same key + same body within 24 h replays the cached response; same key + different body returns 409. The DB UNIQUE on `booking_item_id` / `booking_vendor_id` provides the primary dedup safeguard regardless of header presence. (Reviews are not on Constitution VIII's "required" list.) |
| **IX** | Domain events fire `DB::afterCommit` | ✅ Pass | `ReviewSubmitted` and `ReviewApproved` fire after their respective transactions commit. The rating-aggregation listener is queued (`ShouldQueue`) — no synchronous external work inside the transaction. |
| **X** | Vendor approval — two-step gate | ✅ Pass | Not directly applicable to Reviews. (The customer is the actor; vendor approval gates vendor creation, not review reception.) |
| **XI** | Document storage policy | ✅ Pass | Not applicable — reviews carry no file attachments in Phase 1 (image attachments are an explicit Phase 2 deferral). |

**No principle violations.** Phase 1 forbidden features check (per Constitution §"Phase 1 Forbidden Features"): none in scope. ✅

---

## Cut-list

Inherited from [`docs/specs/09_Phasing_Plan.md`](../../docs/specs/09_Phasing_Plan.md) §Phase 5.1, in deferral order:

1. **Vendor responses to reviews** (`RespondToReviewAction` + customer-facing endpoint) — defer to **Phase 6.0**. Schema (`review_responses`) is created in 5.1 so 6.0 needs no migrations.
2. **Review locale auto-detection** — defer to **Phase 6.0**. 5.1 stamps `locale` from `customer_profiles.preferred_locale`. The `mixed` ENUM value remains available for future detection.
3. **Per-product-type review eligibility windows** — defer to **Phase 6.0**. 5.1 uses a single rule: any `completed` booking item is eligible indefinitely.
4. **Review reminder notifications** ("rate your experience" X days post-completion) — defer to **Phase 6.0**.
5. **Image attachments on reviews** — out of Phase 1 entirely.

---

## Exit Criteria

The phase is **done** when all of the following are demonstrably true:

- [ ] **EC-1** — Customer can submit a `service_reviews` row only when the underlying `booking_item.item_status='completed'`; non-completed attempts are rejected with a clear error. (Verified: per-type Pest tests for rental, sale, digital.)
- [ ] **EC-2** — Customer can submit a `vendor_reviews` row only when every `booking_item` of the `booking_vendor` is `completed`; partial-completion attempts are rejected.
- [ ] **EC-3** — Admin moderation page (`ReviewModerationPage`) lists all `pending` reviews across both review types, supports approve / reject / hide actions, and every transition writes to `review_moderation_log`.
- [ ] **EC-4** — On approval (or any transition that changes the visible rating set), the parent service's / vendor's `average_rating` and review count are recomputed by a queued listener within the NFR window.
- [ ] **EC-5** — All Pest test groups pass: `reviews` group covers happy paths, eligibility per product type, uniqueness, moderation transitions, RBAC, locale, idempotency, and the cross-module-import architecture test.

---

## Success Criteria *(measurable, technology-agnostic)*

- **SC-001**: At least 95% of customers with a completed booking item can successfully submit a review on the first attempt (no validation errors caused by unclear rules).
- **SC-002**: Median time from review submission to admin moderation decision is under 24 hours during a moderator's working day (operational target — does not block phase exit).
- **SC-003**: 100% of approved reviews appear in the public service/vendor listing within 1 minute of approval (rating aggregation freshness).
- **SC-004**: Zero duplicate reviews exist in production at any time (enforced by DB UNIQUE on `booking_item_id` and `booking_vendor_id`).
- **SC-005**: 100% of moderation transitions are recoverable from `review_moderation_log` (audit completeness).

---

## Assumptions

- **A-1**: A customer's `preferred_locale` is reliably populated on `customer_profiles` (set during onboarding in Phase 1.1). Reviews stamp this value as the persisted `locale` for the body.
- **A-2**: `booking_items.item_status` and `booking_vendors` follow the lifecycle defined by Booking module (Phase 3), and `completed` is a stable terminal-or-near-terminal state for review eligibility.
- **A-3**: `services.average_rating` and `vendor_profiles.average_rating` columns exist (or will be added in this phase via Catalog / Identity adjacent migrations) for the rating-aggregation listener to write into. If they do not yet exist, the phase plan assumes they are added with this work and reflected in `11_DB_Schema.md`. (Note: schema cheatsheet already lists `vendor_profiles` rating columns; service rating columns will be confirmed in `/speckit.plan`.)
- **A-4**: Admins and moderators are seeded with the relevant Spatie permissions (`moderate_service_review`, `moderate_vendor_review`, `hide_review`) by `php artisan shield:generate --all` after the `ReviewModerationPage` is created.
- **A-5**: No automatic content filtering (profanity, spam) is required for Phase 1 — admin moderation is the sole gate. Automated filters are a Phase 2 candidate.
- **A-6**: Idempotency-Key middleware (introduced earlier in Phase 4) is reusable for the new POST endpoints in this module without modification.
- **A-7**: Customers do **not** receive in-app notification of moderation outcomes in Phase 5.1. Notification (review approved / review rejected with reason) is wired in Phase 6.0 via the Communication module's existing `notification_dispatches` infrastructure.
- **A-8**: Soft-deleting one's own review counts as a customer-initiated removal — the review is excluded from rating aggregation immediately.

---

## Dependencies

- **Identity module** — `users`, `customer_profiles.preferred_locale`, `vendor_profiles`, RBAC permissions.
- **Catalog module** — `services` table; `services.average_rating` column for listener writes (verify in plan).
- **Booking module** — `booking_items` (with `item_status` lifecycle) and `booking_vendors`. Public Contract `BookingItemReader` (or equivalent eligibility reader) required.
- **Communication module (optional)** — for future notification on moderation outcome (deferred to Phase 6.0; not blocking).
- **Shared module** — `Idempotency-Key` middleware, `ApiResponse` envelope, `ULID` casts.

---

## Out of Scope (this phase)

- Vendor responses to reviews (Phase 6.0).
- Locale auto-detection (Phase 6.0).
- "Review your experience" reminder notifications (Phase 6.0).
- Customer notification on moderation decision (Phase 6.0).
- Image attachments on reviews (Phase 2).
- Automated content filtering / profanity detection (Phase 2).
- Review helpfulness votes / report-this-review (Phase 2).
- Per-product-type eligibility windows (Phase 6.0).
- Refund-driven review suppression (Phase 6.0+).

---

## Open Questions

> Forwarded to `/speckit.clarify`:

1. ~~Resubmission after rejection~~ — **Resolved 2026-05-03 in Clarifications:** rejection is terminal; manual support override only.
2. ~~Idempotency-Key behavior~~ — **Resolved 2026-05-03 in Clarifications:** optional but supported (DB UNIQUE is the primary safeguard).
3. **`approved → hidden` aggregation behavior** — does hiding a previously approved review subtract it from the aggregate? Default ("yes — `hidden` excluded from average") is now codified by FR-R9 and the canonical state machine clarification — treat as **resolved by spec body**, no separate clarification needed.
