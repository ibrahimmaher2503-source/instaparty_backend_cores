# Research: Reviews + Moderation (Phase 5.1)

**Date**: 2026-05-03
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **ADR**: [ADR-0011](../../docs/adr/0011-reviews-module.md)

This document resolves all `NEEDS CLARIFICATION` items from the plan's Technical Context and records best-practice research for each integration point.

---

## R-1 — Storage strategy for two review tables vs. one polymorphic table

**Decision**: Two physical tables (`service_reviews` + `vendor_reviews`) with near-identical shape.

**Rationale**:

- Locked in `docs/specs/11_DB_Schema.md` §10 — non-negotiable.
- DB-level UNIQUE constraints differ: `(booking_item_id)` for one, `(booking_vendor_id)` for the other. A polymorphic table would force these into the application layer with race-prone "where reviewable_type=… AND reviewable_id=…" SELECT-then-INSERT patterns.
- Aggregation queries (`AVG(rating) WHERE service_id = ?`) hit a single index without a discriminator filter, keeping the hot path simple.
- Polymorphic `review_responses` and `review_moderation_log` reuse the `(review_type, review_id)` pattern only where they genuinely span both review types — that's where polymorphism earns its complexity.

**Alternatives considered**:

- Single `reviews` table with `reviewable_type`/`reviewable_id` polymorphic FK — rejected per locked schema. Application-layer uniqueness loses the DB-level race guarantee.
- Inheritance / table-per-class — rejected; Eloquent's STI would force a discriminator column on top of the polymorphic FK; even more complexity.

---

## R-2 — Eligibility check across three product-type state machines

**Decision**: Encapsulate eligibility behind two Contracts implemented in the Booking module.

- `BookingItemReviewabilityReader::isReviewable(int $bookingItemId, int $userId): bool`
- `BookingVendorReviewabilityReader::isReviewable(int $bookingVendorId, int $userId): bool`

**Rationale**:

- Per `docs/specs/11_DB_Schema.md` line 772, `booking_items.item_status` is `VARCHAR(40)` and "runs 3 different state machines (one per type)". Each type's terminal "completed" value happens to be the literal string `'completed'`, but the surrounding state machine differs (rental: `setup → in_use → return → completed`; sale: `in_preparation → ready → delivered → completed`; digital: `pending_redemption → redeemed → completed`).
- Reviews module **must not** care which state machine the underlying item follows. The Contract returns a boolean; product-type knowledge stays inside the Booking module.
- Contracts also bundle ownership check (`booking.user_id === $userId`) so the consumer doesn't hit two DBs.
- For `BookingVendorReviewabilityReader`, the implementation runs `EXISTS (SELECT 1 FROM booking_items WHERE booking_vendor_id = ? AND item_status != 'completed')` — straightforward SQL, single query.

**Alternatives considered**:

- Reviews module imports `BookingItem` model and calls `$item->isCompleted()` — **forbidden** by Constitution Principle I.
- Reviews module duplicates the "completed" string check — **forbidden** by `match($enum)` rule and creates type-knowledge leakage.
- Use a domain event `BookingItemCompleted` to push eligibility to Reviews — heavier than needed; the customer initiates review submission, so a synchronous pull at submit-time fits better.

---

## R-3 — Rating aggregation: inline vs. queued listener

**Decision**: Queued listener (`RecomputeRatingOnApproval implements ShouldQueue`) firing on `ReviewApproved`, `ReviewRejected` (only when `previous_status='approved'`), `ReviewHidden`, and `ReviewSelfDeleted` (only when `previous_status='approved'`).

**Rationale**:

- Constitution IX requires `DB::afterCommit` for events, and queued listeners for any work beyond a trivial cache bust.
- Aggregation cost: `SELECT AVG(rating), COUNT(*) FROM service_reviews WHERE service_id = ? AND moderation_status = 'approved' AND deleted_at IS NULL`. With the composite index `(service_id, moderation_status)` this is O(matching rows). For a service with 137 approved reviews this is ~5 ms — fast enough to be inline. But popular services may grow to 5,000+ reviews; a queued listener insulates the moderator UX from that growth.
- Idempotency: rerunning the recomputation produces the same value, so queue retries are safe.
- Multi-event subscription: a single listener class subscribes to all four "rating-changing" events. The listener's `handle` method dispatches on event type to determine the (subject_type, subject_id) tuple to recompute.

**Alternatives considered**:

- Inline recomputation inside `ModerateReviewAction::execute()` — rejected; coupled the moderator's HTTP latency to an O(N) aggregation that grows with marketplace popularity.
- Periodic batch job recomputing all rating averages — rejected; introduces lag between approval and visible rating change, breaking SC-003 (1-minute freshness).
- Trigger-based aggregation in MySQL — rejected; cross-module concern (Catalog/Identity own the columns), and DB triggers are opaque vs. observable Laravel listeners.

---

## R-4 — Reviewer identity in public listings

**Decision**: Show first name only, derived as `explode(' ', trim($user->name))[0]`. Fall back to localized "Verified Customer" when first name is empty.

**Rationale**:

- Spec Clarifications §Q3 confirmed first-name-only.
- Identity module's `users` table has a single `name` column (verified by `grep` on `App\Modules\Identity\Domain\Models\User`); there is no `first_name` / `last_name` split. Splitting at the API Resource layer is a 1-line operation and avoids an Identity migration.
- Edge cases:
  - `name = ""` → fallback to "Verified Customer" / "عميل موثق"
  - `name = "Ahmed"` (single word) → "Ahmed"
  - `name = "Ahmed Mohamed Ali"` → "Ahmed"
  - `name = "  Ahmed  "` (whitespace) → trimmed → "Ahmed"
  - Multibyte / Arabic names → `explode` is byte-safe for space characters; tested with Arabic name like "أحمد محمد".

**Alternatives considered**:

- Add `first_name` / `last_name` columns to `users` — rejected; requires Identity migration and out of scope for Phase 5.1.
- Show full `name` — rejected per Clarifications §Q3 (privacy concerns in MENA market).
- Customer's chosen `display_name` — no such column exists; would require schema change.

---

## R-5 — Idempotency-Key middleware reuse

**Decision**: Reuse existing `Shared\Http\Middleware\IdempotencyKey` (introduced in Phase 4 Payments) with the `optional` mode flag.

**Rationale**:

- Constitution VIII does not require Idempotency-Key on review submission, but the platform's middleware is already present and inexpensive to honor.
- `optional` mode: middleware checks for the header; if absent, request proceeds normally; if present, standard 24h replay logic engages.
- Configuration: register middleware on review POST/DELETE routes with `idempotency:optional` parameter. (Existing middleware accepts a mode parameter from Phase 4's design.)

**Alternatives considered**:

- Strict mode (require header) — rejected per Clarifications §Q4. Would burden mobile clients without much value.
- Don't honor the header at all — rejected; loses consistency with the rest of the platform's POST endpoints. A client mid-retry would see a 409 instead of a 200-replay, leaking implementation detail.

---

## R-6 — Filament Page vs. two Resources for moderation

**Decision**: One custom Filament Page (`ReviewModerationPage`) under "Moderation" navigation group.

**Rationale**:

- Per ADR-0011 §6.5: moderation workflow is identical for both review types (read body, decide). A single queue with type filter is more efficient for moderators than tab-switching between two Resources.
- Shield generates one `page_ReviewModerationPage` permission instead of doubled `view_any_service_review` / `view_any_vendor_review` etc.
- The Page renders a unified Filament Table component pulled from a UNION query across both review tables.
- Bulk-approve action is enabled on the Page (single action over heterogeneous selection).

**Alternatives considered**:

- Two Filament Resources (`ServiceReviewResource`, `VendorReviewResource`) — rejected; doubles permissions, doubles UI surface, doubles QA.
- Polymorphic `Review` model with one Resource — rejected per R-1 (we have two physical tables).

---

## R-7 — `users.name` parsing for first-name fallback

**Decision**: Implement first-name extraction as a small private method on the relevant Resource(s):

```php
private function extractFirstName(?string $fullName, string $fallbackKey = 'reviews.verified_customer'): string
{
    $trimmed = trim((string) $fullName);
    if ($trimmed === '') {
        return __($fallbackKey);
    }
    $parts = preg_split('/\s+/', $trimmed, 2);
    return $parts[0] ?? __($fallbackKey);
}
```

**Rationale**:

- `preg_split('/\s+/', ...)` handles multiple-whitespace edge cases that `explode(' ', ...)` doesn't.
- `__($fallbackKey)` resolves through `Resources/lang/{en,ar}/reviews.php` for localized fallback ("Verified Customer" / "عميل موثق").
- Lives on the public Resource only — never on the customer-owned Resource (which exposes the customer's own data and should show the full name as-is).

**Alternatives considered**:

- A shared helper in `Shared\Domain\Helpers\NameFormatter` — rejected as premature abstraction; only one consumer in 5.1. Promote to shared if a second consumer appears.
- Cache the extracted first name on the review row — rejected; denormalization for a 5-line operation is over-engineering.

---

## R-8 — `review_moderation_log.reason` translatable on a fundamentally append-only table

**Decision**: `reason` is `JSON NULL`; uses `spatie/laravel-translatable` cast on the model. Append-only invariant is preserved because the column is set at INSERT time and never updated.

**Rationale**:

- Append-only does not preclude translatable JSON — the constraint is "no UPDATE", not "no JSON". The translatable cast just hydrates the JSON into per-locale getters; it doesn't write.
- Filament admin's reject-reason form accepts the EN+AR pair and writes both keys at INSERT.
- Reading from any locale uses `$log->getTranslation('reason', $locale)` — pure read.

**Alternatives considered**:

- Two columns (`reason_en`, `reason_ar`) — rejected; violates the project-wide JSON translatable convention from `04_Bilingual_Spec.md`.
- Single non-translatable string in admin's locale — rejected; customer-facing notification (Phase 6.0) needs the customer's locale, so storing both is required.

---

## R-9 — Test fixtures: simulating completed booking_items per product type

**Decision**: Use module-local Pest factories in `app/Modules/Reviews/Database/Factories/` plus existing Booking factories. For each product type, create a factory state `withCompletedBookingItem(ProductType $type)` that:

1. Creates a `Vendor` + `Service` of the requested type via Catalog factories
2. Creates a `Booking` with the test customer
3. Creates a `BookingVendor` for the vendor
4. Creates a `BookingItem` of the requested type with `item_status='completed'`
5. Returns the `BookingItem` for the test to use as the eligibility target

**Rationale**:

- Eligibility tests must cover all three product-type state machines per spec FR-R1.
- Hand-rolling fixtures per test is brittle; factories isolate the per-type assembly.
- Factories live in Reviews module's directory because they are test-only fixtures used by Reviews tests; they consume Catalog and Booking factories through public factory classes (which are public artifacts, unlike models).

**Alternatives considered**:

- Use Booking-side factories directly — fine but Reviews still needs a small wrapper to assert the `completed` state on each created item.
- Mock the eligibility contract entirely — used for unit tests; integration tests still need real DB rows to exercise the UNIQUE constraint and aggregation listener.

---

## R-10 — Cursor pagination for review listings

**Decision**: Use Laravel's built-in `cursorPaginate()` on `created_at DESC, id DESC` for both customer-owned (`/customer/reviews`) and public (`/public/services/{id}/reviews`) listings.

**Rationale**:

- Standard for the project per `api-registry.md` §Conventions.
- Composite cursor key `(created_at, id)` handles same-second ties.
- `next_cursor` and `prev_cursor` exposed in the `meta` envelope.
- Existing `Shared\Http\Resources\CursorPaginationMeta` helper is reused.

**Alternatives considered**:

- Offset pagination — rejected for the project; performance degrades on deep pages and breaks under concurrent inserts.

---

## Summary of resolved unknowns

| Item | Resolution |
|---|---|
| Two-table vs polymorphic storage | Two physical tables (locked schema) |
| Eligibility across product types | Two Contracts implemented in Booking |
| Aggregation strategy | Queued listener with multi-event subscription |
| Reviewer identity disclosure | First name from `users.name`; "Verified Customer" fallback |
| Idempotency middleware | Reuse existing with `optional` mode |
| Moderation UI shape | Single Filament Page, no Resources |
| First-name parsing | `preg_split('/\s+/', trim($name), 2)[0]` on Resource |
| Translatable on append-only log | OK — JSON cast, set at INSERT only |
| Per-type test fixtures | Reviews-local factories using Booking/Catalog factories |
| Pagination | `cursorPaginate()` on `(created_at, id)` |

**No outstanding `NEEDS CLARIFICATION` items.** Plan is ready for Phase 1 (data-model + contracts + quickstart).
