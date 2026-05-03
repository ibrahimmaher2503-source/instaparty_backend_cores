# Implementation Plan: Reviews + Moderation (Phase 5.1)

**Branch**: `010-reviews-moderation` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**ADR**: [ADR-0011 — Reviews Module](../../docs/adr/0011-reviews-module.md) — **Accepted 2026-05-03**

---

## Summary

Build the Reviews module — a new `app/Modules/Reviews/` that lets customers submit a 1–5 star review tied to a completed `booking_item` (service review) or fully-completed `booking_vendor` (vendor review), gates publication behind admin moderation through a unified Filament page, and recomputes `services.rating_avg` / `vendor_profiles.rating_avg` via a queued listener after every transition that changes the visible rating set.

Four migrations (`service_reviews`, `vendor_reviews`, `review_responses`, `review_moderation_log`), four Actions (`SubmitServiceReviewAction`, `SubmitVendorReviewAction`, `ModerateReviewAction`, `RespondToReviewAction` — last one scaffold-only per cut-list), one queued listener (`RecomputeRatingOnApproval`), one Filament Page (`ReviewModerationPage`), six API endpoints (4 customer, 2 public), and a cross-type Pest test suite that exercises eligibility against all three product-type state machines.

**Cross-module reads via Contracts only:**

- `Reviews\Domain\Contracts\BookingItemReviewabilityReader` (implemented in Booking) — returns `true` iff `booking_items.item_status === 'completed'` and the booking's customer matches the caller.
- `Reviews\Domain\Contracts\BookingVendorReviewabilityReader` (implemented in Booking) — returns `true` iff every `booking_items` row under the `booking_vendor` has `item_status === 'completed'` and customer ownership matches.
- `Reviews\Domain\Contracts\ServiceRatingWriter` (implemented in Catalog) — sets `services.rating_avg` and `services.rating_count`.
- `Reviews\Domain\Contracts\VendorRatingWriter` (implemented in Identity) — sets `vendor_profiles.rating_avg` and `vendor_profiles.rating_count`.

**Depends on:** Booking (Phase 3.x — `booking_items.item_status='completed'`, `booking_vendors`), Catalog (Phase 2.x — `services.rating_avg/rating_count`), Identity (Phase 1.x — `users`, `vendor_profiles.rating_avg/rating_count`, `customer_profiles.preferred_locale`).

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12

**Primary Dependencies** (all from `docs/specs/10_Package_List.md` — no new packages):

- `spatie/laravel-permission` — `moderate_service_review`, `moderate_vendor_review`, `hide_review` permissions
- `spatie/laravel-translatable` — `review_moderation_log.reason` JSON column
- `spatie/laravel-model-states` — `ReviewModerationState` (pending / approved / rejected / hidden) for both review types
- `bezhansalleh/filament-shield` — Page-level permissions for `ReviewModerationPage`
- `filament/filament` — `ReviewModerationPage` (custom Page, not Resource)
- `pestphp/pest` + `pestphp/pest-plugin-laravel` — feature + unit + architecture tests

**Storage**: MySQL 8 — 4 new tables per `11_DB_Schema.md` §10:

- `service_reviews` — soft-deletable
- `vendor_reviews` — soft-deletable
- `review_responses` — standard timestamps (Phase 6.0 will activate flow; schema only in 5.1)
- `review_moderation_log` — **append-only** (`created_at` only, no `updated_at`, no soft delete)

**Testing**: Pest — feature tests for submission/moderation; eligibility tests covering rental + sale + digital state machines; unit tests for `RatingAggregationService`; architecture tests for no-cross-module-import, append-only enforcement, no-if-elseif-on-product-type-strings.

**Target Platform**: Linux (Docker Compose dev, Hetzner CCX13 staging)

**Project Type**: Modular monolith API + Filament admin

**Performance Goals**:

- Review submission p95 under 300 ms (single insert + UNIQUE check)
- Moderation queue page initial load under 1 s for up to 100 pending rows
- Rating aggregation listener completes within 30 s of `ReviewApproved` event under normal queue depth
- Aggregation cost per recomputation: single `AVG(rating)` + `COUNT(*)` against `service_reviews` filtered by `service_id` + `moderation_status='approved'` + soft-delete-aware → covered by composite index `(service_id, moderation_status)`

**Constraints**:

- All cross-module reads go through Contracts (no `use App\Modules\Booking\Domain\Models\BookingItem`)
- Aggregation listener implements `ShouldQueue` — never inline in moderation transaction
- Domain events fire `DB::afterCommit` only
- `review_moderation_log` append-only: no UPDATE, no DELETE, no `updated_at`
- Both moderation transitions and rating recomputation must be re-runnable (idempotent) so a queue retry produces the same result
- Reviewer identity in public payload is **first name only** — derived as `explode(' ', $user->name)[0]` at the Resource layer (because `users.name` is a single column per current Identity schema); fall back to localized "Verified Customer" label when first name is empty
- `Idempotency-Key` header is **optional** on `POST /review` endpoints (DB UNIQUE on `booking_item_id` / `booking_vendor_id` is the primary safeguard)

---

## 1. ADR Reference

**ADR-0011 — Reviews Module** — Accepted 2026-05-03
Path: `docs/adr/0011-reviews-module.md`

Key decisions from ADR-0011 that drive implementation:

- **§4** — Reviews is **cross-type** (no per-type Form Requests / Actions / Resources). Per-type behavior leaks in only via `booking_item.product_type` carried in the eligibility contract; no `match($enum)` is needed in this module's Actions.
- **§6.1** — Two physical tables (`service_reviews` + `vendor_reviews`) instead of one polymorphic `reviews` table. UNIQUE constraints + audience-specific FKs are cleaner this way.
- **§6.2** — Soft-delete on `service_reviews` + `vendor_reviews`; `review_moderation_log` remains fully append-only for audit completeness.
- **§6.3** — Rating aggregation runs in a queued listener (`RecomputeRatingOnApproval`), never inline in the moderation transaction. Listener fires `ServiceRatingRecomputed` / `VendorRatingRecomputed` to feed Catalog/Discovery search-index updates.
- **§6.4** — Eligibility goes through `BookingItemReviewabilityReader` / `BookingVendorReviewabilityReader` contracts; Reviews never imports Booking models.
- **§6.5** — Admin moderation lives on a custom Filament Page (`ReviewModerationPage`), not on per-type Resources. Approve/reject/hide actions delegate to `ModerateReviewAction::execute()`.
- **§6.6** — Review `locale` stamps from `customer_profiles.preferred_locale` at submission time. Auto-detection deferred to Phase 6.0.

---

## 2. Constitution Check

| # | Principle | Status | Notes |
|---|---|---|---|
| **I** | Modular monolith — module boundaries, no cross-module model imports | ✅ PASS | New `app/Modules/Reviews/` follows full layer layout. Cross-module reads via 4 Contracts (`BookingItemReviewabilityReader`, `BookingVendorReviewabilityReader`, `ServiceRatingWriter`, `VendorRatingWriter`). Architecture test `tests/Architecture/ReviewsModuleNoCrossImportTest.php` enforces. |
| **II** | Three product types — `match($enum)`, no if/elseif | ✅ PASS | Reviews is cross-type. Submission/moderation actions identical across product types. No `match($productType)` or if/elseif in Reviews code. Eligibility tests do cover all three product-type state machines because `booking_items.item_status` runs three different state machines (the contract abstracts this away — Reviews only sees the boolean answer). |
| **III** | Money discipline | ✅ N/A | No money columns in Reviews module. |
| **IV** | Bilingual EN+AR mandatory | ✅ PASS | `review_moderation_log.reason` is JSON translatable. UI labels and validation messages live in `Resources/lang/{en,ar}/reviews.php`. The review **body** itself is single-language by design (the customer wrote it once); `locale` ENUM is exposed on responses. Both EN and AR error/locale paths covered by Pest. |
| **V** | Append-only tables — no softDeletes | ✅ PASS | `review_moderation_log` has `created_at` only — no `updated_at`, no `deleted_at`, no `softDeletes()`. `service_reviews` and `vendor_reviews` use soft-delete per locked schema (whitelisted in Constitution V's "soft-deletable" list — see CLAUDE.md §15 / `schema-cheatsheet.md`). `review_responses` uses standard timestamps (mutable status only). |
| **VI** | Spec-driven — ADR before code | ✅ PASS | ADR-0011 created and Accepted 2026-05-03 before any migration is written. All 6 internal decisions enumerated. Cross-references to PRD §6.3, schema §10, customer journey §step 60, admin journey §step 26. |
| **VII** | Test-first for critical paths | ✅ PASS | Pest tests written same day as code. Coverage: eligibility (all 3 product types via fixtures), uniqueness 409 path, all 5 moderation transitions, aggregation listener correctness, RBAC (customer / vendor / admin / non-owner), locale (EN+AR), idempotency replay. Architecture tests for no-cross-module-import, append-only, no-if-elseif. |
| **VIII** | Idempotency for state-changing endpoints | ✅ PASS | `POST /booking-items/{id}/review` and `POST /booking-vendors/{id}/review` accept `Idempotency-Key` **as an optional header** (per Clarifications §Q4 — DB UNIQUE is primary safeguard). When present, standard 24h replay middleware honors it. `DELETE /reviews/{id}` (soft-delete own review) — also optional Idempotency-Key. Constitution VIII's required-list (booking submit, payment, refund, withdrawal) does not include reviews. |
| **IX** | Domain events fire `DB::afterCommit` | ✅ PASS | `ReviewSubmitted`, `ReviewApproved`, `ReviewRejected`, `ReviewHidden`, `ServiceRatingRecomputed`, `VendorRatingRecomputed` — all dispatched via `DB::afterCommit(fn () => event(...))`. Aggregation listener implements `ShouldQueue`. Architecture test asserts no raw `event()` or `Event::dispatch` outside `DB::afterCommit` in this module's Actions. |
| **X** | Vendor approval — two-step gate | ✅ N/A | Customer is the actor; vendor approval gates vendor creation, not review reception. |
| **XI** | Document storage policy | ✅ N/A | No file attachments on reviews in Phase 1 (image attachments deferred to Phase 2). |

**GATE RESULT: ALL PASS — proceed to implementation.**

No Phase 2 forbidden features in scope (vendor responses + locale auto-detection + per-type windows are deferred to Phase 6.0 per cut-list, NOT to Phase 2).

---

## 3. Schema

Matches `docs/specs/11_DB_Schema.md` §10 (Reviews module). Migrations run in FK dependency order:

| # | Migration filename | Tables / Changes |
|---|---|---|
| 1 | `2026_05_04_100001_create_service_reviews_table.php` | `service_reviews` — UNIQUE `booking_item_id`, soft-delete |
| 2 | `2026_05_04_100002_create_vendor_reviews_table.php` | `vendor_reviews` — UNIQUE `booking_vendor_id`, soft-delete |
| 3 | `2026_05_04_100003_create_review_responses_table.php` | `review_responses` — polymorphic `(review_type, review_id)` |
| 4 | `2026_05_04_100004_create_review_moderation_log_table.php` | `review_moderation_log` — append-only, polymorphic `(review_type, review_id)` |

### `service_reviews` (migration 1)

```
id                    BIGINT UNSIGNED PK AUTO_INCREMENT
public_id             CHAR(26) UNIQUE NOT NULL                ULID
service_id            BIGINT UNSIGNED FK→services.id RESTRICT
booking_item_id       BIGINT UNSIGNED FK→booking_items.id RESTRICT UNIQUE
user_id               BIGINT UNSIGNED FK→users.id RESTRICT
rating                TINYINT UNSIGNED NOT NULL              CHECK 1..5
body                  TEXT NULL
locale                ENUM('ar','en','mixed') NOT NULL
moderation_status     ENUM('pending','approved','rejected','hidden') NOT NULL DEFAULT 'pending'
moderated_by          BIGINT UNSIGNED FK→users.id RESTRICT NULL
moderated_at          TIMESTAMP NULL
created_at            TIMESTAMP
updated_at            TIMESTAMP
deleted_at            TIMESTAMP NULL                          soft delete

UNIQUE KEY (booking_item_id)
INDEX (service_id, moderation_status)                          public listing + aggregation
INDEX (user_id, created_at)                                    customer's "my reviews" list
```

### `vendor_reviews` (migration 2)

```
id                    BIGINT UNSIGNED PK AUTO_INCREMENT
public_id             CHAR(26) UNIQUE NOT NULL                ULID
vendor_profile_id     BIGINT UNSIGNED FK→vendor_profiles.id RESTRICT
booking_vendor_id     BIGINT UNSIGNED FK→booking_vendors.id RESTRICT UNIQUE
user_id               BIGINT UNSIGNED FK→users.id RESTRICT
rating                TINYINT UNSIGNED NOT NULL              CHECK 1..5
body                  TEXT NULL
locale                ENUM('ar','en','mixed') NOT NULL
moderation_status     ENUM('pending','approved','rejected','hidden') NOT NULL DEFAULT 'pending'
moderated_by          BIGINT UNSIGNED FK→users.id RESTRICT NULL
moderated_at          TIMESTAMP NULL
created_at            TIMESTAMP
updated_at            TIMESTAMP
deleted_at            TIMESTAMP NULL                          soft delete

UNIQUE KEY (booking_vendor_id)
INDEX (vendor_profile_id, moderation_status)
INDEX (user_id, created_at)
```

### `review_responses` (migration 3) — schema only in 5.1; flow activated in Phase 6.0

```
id                    BIGINT UNSIGNED PK AUTO_INCREMENT
review_type           ENUM('service','vendor') NOT NULL
review_id             BIGINT UNSIGNED NOT NULL                polymorphic — no FK because it points to two tables
vendor_profile_id     BIGINT UNSIGNED FK→vendor_profiles.id RESTRICT
body                  TEXT NOT NULL
locale                ENUM('ar','en','mixed') NOT NULL
moderation_status     ENUM('pending','approved','rejected','hidden') NOT NULL DEFAULT 'pending'
created_at            TIMESTAMP
updated_at            TIMESTAMP

INDEX (review_type, review_id)
INDEX (vendor_profile_id, moderation_status)
```

### `review_moderation_log` (migration 4, APPEND-ONLY)

```
id                    BIGINT UNSIGNED PK AUTO_INCREMENT
review_type           VARCHAR(80) NOT NULL                    'service' | 'vendor' | 'response'
review_id             BIGINT UNSIGNED NOT NULL                polymorphic
from_status           VARCHAR(40) NULL                        nullable for initial pending row (we don't log creation)
to_status             VARCHAR(40) NOT NULL
moderator_id          BIGINT UNSIGNED FK→users.id RESTRICT
reason                JSON NULL                               translatable {"en": "...", "ar": "..."}
created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP   (no updated_at — append-only)

INDEX (review_type, review_id, created_at)                    audit trail per review
INDEX (moderator_id, created_at)                              admin activity report
```

> **Note:** `review_moderation_log` has no `updated_at`, no `deleted_at`, no soft-delete trait, no `softDeletes()` migration call. Architecture test enforces this.

**FK dependency order:** `users` (Identity) + `vendor_profiles` (Identity) + `services` (Catalog) + `booking_items` (Booking) + `booking_vendors` (Booking) → `service_reviews` → `vendor_reviews` → `review_responses` → `review_moderation_log`.

**Critical indexes:**

- `service_reviews`: UNIQUE `booking_item_id` (one review per item), `(service_id, moderation_status)` (public listing + AVG aggregation)
- `vendor_reviews`: UNIQUE `booking_vendor_id`, `(vendor_profile_id, moderation_status)`
- `review_moderation_log`: `(review_type, review_id, created_at)` for audit trail per review

---

## 4. Per-type Coverage

Reviews module is **cross-type infrastructure** per ADR-0011 §4 — submission and moderation flows are identical for rental, sale, and digital. No per-type Form Requests, Actions, Resources, or Filament pages are created.

**Where the product type DOES matter — and how it's handled:**

| Concern | How the product type leaks in | Handled by |
|---|---|---|
| Eligibility — "is `booking_item` completed?" | Each product type has its own `item_status` state machine (rental, sale, digital all converge on `'completed'` as the terminal value per `11_DB_Schema.md` §5) | `BookingItemReviewabilityReader` (Booking-side implementation) — Reviews module sees only the boolean answer |
| Vendor review eligibility | Vendor-level review needs every `booking_item` under the `booking_vendor` to be `completed` regardless of product mix | `BookingVendorReviewabilityReader` — encapsulates "all items completed" check |
| Public review payload | Service detail page (per-type Filament/API resources in Catalog) embeds rating summary | Reviews emits `ServiceRatingRecomputed` event with the new `rating_avg` + `rating_count`; Catalog updates the indexed fields and Meilisearch facet |

**Test groups:** primarily `reviews`. Per-type test groups (`rental`, `sale`, `digital`) are added to **eligibility tests only**, because the underlying `booking_items.item_status` state machine differs per product type. The Reviews Action code itself has no per-type branching.

```php
it('allows review submission when rental booking_item is completed', function () {
    // ...
})->group('reviews', 'rental');

it('allows review submission when sale booking_item is completed', function () {
    // ...
})->group('reviews', 'sale');

it('allows review submission when digital booking_item is completed', function () {
    // ...
})->group('reviews', 'digital');

it('rejects review submission for any product type when item is not completed', function () {
    // pest data provider over [rental, sale, digital] non-completed states
})->group('reviews');
```

**Rule:** No `match($productType)` or if/elseif on `product_type` strings inside `app/Modules/Reviews/`. Architecture test enforces.

---

## 5. Locale Coverage

**Translatable fields in this phase:**

| Table | Column | Type | Required locales | Validation |
|---|---|---|---|---|
| `review_moderation_log` | `reason` | JSON `{"en": "...", "ar": "..."}` | EN + AR both required when reason present (admin can submit a single-locale rejection only with explicit override) | Form Request rule asserts both keys present when reason is non-null |

**Non-translatable but locale-aware:**

| Table | Column | Behavior |
|---|---|---|
| `service_reviews.body` / `vendor_reviews.body` | TEXT NULL | Single-language. Stamped with `locale` from `customer_profiles.preferred_locale` at submission. Returned verbatim in API; client uses `locale` field to render `dir="rtl|ltr"`. |
| `service_reviews.locale` / `vendor_reviews.locale` | ENUM('ar','en','mixed') | Locale stamp. Auto-detection deferred to Phase 6.0 per cut-list. |

**Locale resolution at submission:**

1. `SubmitServiceReviewAction` / `SubmitVendorReviewAction` receives the authenticated `User`
2. Reads `$user->customerProfile->preferred_locale` (ENUM `'ar'|'en'`, never null per Identity schema)
3. Stamps the review row's `locale` column with that value
4. Resource layer returns the value in `data.locale` for client rendering hints

**API Resource locale behavior:**

- `ServiceReviewResource` and `VendorReviewResource` localize **error messages** and **status labels** via `Accept-Language` (default `ar`)
- The review **body** is returned verbatim regardless of the request's `Accept-Language` (it's the customer's voice, not platform copy)
- The reviewer's first name is returned as-is from `users.name` (single-column extraction); fallback "Verified Customer" / "عميل موثق" is localized

**Filament locale coverage:**

- `ReviewModerationPage` uses Filament's locale switcher; UI labels translated; review body shown verbatim with a small `[ar]` / `[en]` badge
- Admin must validate the page in both locales before Phase 5.1 exit

**Translations files (Day 1):**

- `app/Modules/Reviews/Resources/lang/en/reviews.php` — labels, validation messages, fallback "Verified Customer"
- `app/Modules/Reviews/Resources/lang/ar/reviews.php` — same keys, AR values

---

## 6. Idempotency

| Endpoint | Idempotency-Key? | Reason |
|---|---|---|
| `POST /api/v1/customer/booking-items/{id}/review` | **Optional** | DB UNIQUE on `booking_item_id` is the primary dedup safeguard; header honored if present (24h replay) per Clarifications §Q4 |
| `POST /api/v1/customer/booking-vendors/{id}/review` | **Optional** | DB UNIQUE on `booking_vendor_id`; same as above |
| `DELETE /api/v1/customer/reviews/{publicId}` | **Optional** | Soft-delete is naturally idempotent (second call is a no-op once `deleted_at` is set); header honored if present |
| `GET /api/v1/customer/reviews` | No — read-only | |
| `GET /api/v1/public/services/{publicId}/reviews` | No — read-only | |
| `GET /api/v1/public/vendors/{publicId}/reviews` | No — read-only | |
| `GET /api/v1/public/services/{publicId}/rating-summary` | No — read-only | |
| `GET /api/v1/public/vendors/{publicId}/rating-summary` | No — read-only | |
| Filament moderation actions (no public REST) | N/A | Page-level user action; not API-driven |

**Idempotency middleware integration:**

- Reuse existing `Shared\Http\Middleware\IdempotencyKey` (introduced in Phase 4 Payments)
- For the 3 review-write endpoints, register middleware as `idempotency:optional` (don't reject if header absent)
- When key+body matches an existing entry within 24h, replay the cached `ApiResponse` envelope as-is

---

## 7. Domain Events

### Events Reviews module publishes

| Event | When | Fired via | Payload |
|---|---|---|---|
| `Reviews\Domain\Events\ReviewSubmitted` | After `service_reviews` or `vendor_reviews` row created (status=pending) | `DB::afterCommit(fn () => event(...))` inside `SubmitServiceReviewAction` / `SubmitVendorReviewAction` | `review_id` (internal), `review_public_id`, `review_type`, `subject_id` (service_id or vendor_profile_id), `user_id`, `rating`, `submitted_at` |
| `Reviews\Domain\Events\ReviewApproved` | After moderation transition `pending → approved` or `hidden → approved` | `DB::afterCommit` inside `ModerateReviewAction` | `review_id`, `review_public_id`, `review_type`, `subject_id`, `rating`, `approved_at`, `moderator_id`, `previous_status` |
| `Reviews\Domain\Events\ReviewRejected` | After `pending → rejected` or `approved → rejected` | `DB::afterCommit` inside `ModerateReviewAction` | `review_id`, `review_public_id`, `review_type`, `subject_id`, `reason` (translatable), `moderator_id`, `rejected_at`, `previous_status` |
| `Reviews\Domain\Events\ReviewHidden` | After `approved → hidden` | `DB::afterCommit` inside `ModerateReviewAction` | `review_id`, `review_public_id`, `review_type`, `subject_id`, `moderator_id`, `hidden_at` |
| `Reviews\Domain\Events\ReviewSelfDeleted` | After customer soft-deletes own review | `DB::afterCommit` inside `DeleteOwnReviewAction` | `review_id`, `review_type`, `subject_id`, `previous_moderation_status` |
| `Reviews\Domain\Events\ServiceRatingRecomputed` | After listener recomputes `services.rating_avg` and writes the new value | `DB::afterCommit` inside listener | `service_id`, `service_public_id`, `new_average`, `count` |
| `Reviews\Domain\Events\VendorRatingRecomputed` | After listener recomputes `vendor_profiles.rating_avg` | `DB::afterCommit` inside listener | `vendor_profile_id`, `vendor_public_id`, `new_average`, `count` |

### Events Reviews module consumes

| Event | Source module | Listener | Action triggered |
|---|---|---|---|
| `Reviews\Domain\Events\ReviewApproved` | self | `RecomputeRatingOnApproval` (queued, ShouldQueue) | Recomputes `rating_avg` + `rating_count` on the service or vendor; calls `ServiceRatingWriter` / `VendorRatingWriter` contract; fires `ServiceRatingRecomputed` / `VendorRatingRecomputed` |
| `Reviews\Domain\Events\ReviewRejected` | self | `RecomputeRatingOnApproval` (same listener, multi-event subscription) | Recomputes only when `previous_status === 'approved'` (i.e. takedown of a published review); no-op for `pending → rejected` |
| `Reviews\Domain\Events\ReviewHidden` | self | `RecomputeRatingOnApproval` (multi-event) | Always recomputes (was approved) |
| `Reviews\Domain\Events\ReviewSelfDeleted` | self | `RecomputeRatingOnApproval` (multi-event) | Recomputes only when `previous_moderation_status === 'approved'` |

```php
// Pattern in ModerateReviewAction::execute()
return DB::transaction(function () use ($review, $decision) {
    $previous = $review->moderation_status;
    $this->repository->transition($review, $decision);
    $this->logRepository->append($review, $previous, $decision->toStatus(), $decision->moderatorId, $decision->reason);

    DB::afterCommit(function () use ($review, $decision, $previous) {
        $event = match ($decision->toStatus()) {
            ModerationStatus::Approved => new ReviewApproved($review, $previous),
            ModerationStatus::Rejected => new ReviewRejected($review, $previous, $decision->reason),
            ModerationStatus::Hidden   => new ReviewHidden($review, $previous),
        };
        event($event);
    });

    return $review->fresh();
});
```

> **Listeners downstream of Reviews (consumed by other modules):**
>
> - **Catalog** — listens to `ServiceRatingRecomputed` to refresh Meilisearch facet for the affected service.
> - **Discovery** — same listener; updates search index ranking signals.
> - **Communication** (Phase 6.0 wire-up) — will listen to `ReviewApproved` / `ReviewRejected` to send "your review was published" / "your review was rejected" notifications. Not in 5.1 scope.

---

## 8. API Documentation Plan

### Endpoints in Phase 5.1

| Method | Path | Auth | Roles | Controller | Form Request | Resource |
|---|---|---|---|---|---|---|
| `POST` | `/api/v1/customer/booking-items/{bookingItemPublicId}/review` | sanctum-token | customer | `Customer\SubmitServiceReviewController` | `SubmitServiceReviewRequest` | `ServiceReviewResource` |
| `POST` | `/api/v1/customer/booking-vendors/{bookingVendorPublicId}/review` | sanctum-token | customer | `Customer\SubmitVendorReviewController` | `SubmitVendorReviewRequest` | `VendorReviewResource` |
| `GET` | `/api/v1/customer/reviews` | sanctum-token | customer | `Customer\ListMyReviewsController` | — | `MyReviewResource[]` (cursor) |
| `DELETE` | `/api/v1/customer/reviews/{reviewPublicId}` | sanctum-token | customer | `Customer\DeleteOwnReviewController` | — | 204 No Content |
| `GET` | `/api/v1/public/services/{servicePublicId}/reviews` | none | — | `Public\ListServiceReviewsController` | — | `PublicServiceReviewResource[]` (cursor) |
| `GET` | `/api/v1/public/vendors/{vendorPublicId}/reviews` | none | — | `Public\ListVendorReviewsController` | — | `PublicVendorReviewResource[]` (cursor) |
| `GET` | `/api/v1/public/services/{servicePublicId}/rating-summary` | none | — | `Public\GetServiceRatingSummaryController` | — | `RatingSummaryResource` |
| `GET` | `/api/v1/public/vendors/{vendorPublicId}/rating-summary` | none | — | `Public\GetVendorRatingSummaryController` | — | `RatingSummaryResource` |

> Filament admin moderation is **page-based** (`ReviewModerationPage`), not exposed as REST in Phase 5.1.

### @bodyParam PHPDoc on Form Requests

`SubmitServiceReviewRequest`:
```php
/**
 * @bodyParam rating integer required Rating from 1 to 5 inclusive. Example: 5
 * @bodyParam body string optional Free-text review body. Plain text only; HTML/markdown is escaped on render. Max 2000 chars. Example: "خدمة ممتازة، الفنانين كانوا في الموعد بالظبط."
 */
```

`SubmitVendorReviewRequest`:
```php
/**
 * @bodyParam rating integer required Rating from 1 to 5 inclusive. Example: 4
 * @bodyParam body string optional Free-text review body. Max 2000 chars. Example: "Communication was great, but pricing felt a bit high."
 */
```

**Validation rules (both):**

- `rating` — required, integer, between:1,5
- `body` — nullable, string, max:2000
- Path param `{bookingItemPublicId}` / `{bookingVendorPublicId}` — exists in DB and owned by authenticated user (resolved through `BookingItemReviewabilityReader` / `BookingVendorReviewabilityReader`)
- Eligibility check inside Action (not Form Request — requires DB access through the contract): item completed → 422 with `booking_item_not_completed` if not
- Uniqueness check: relies on DB UNIQUE; surfaces 409 `review_already_exists` with the existing review's `public_id` echoed in the error payload

### @response PHPDoc on Resources

`ServiceReviewResource` — example response (AR locale, post-submission, status pending):

```json
{
  "data": {
    "public_id": "01J9XR2K8HZJVNKQR1WBM7P3CD",
    "service_public_id": "01J9KM3Z1A8BCDR7ER77M2X8YD",
    "booking_item_public_id": "01J9KM3Z1A8BCDR7ER77M2X8YE",
    "rating": 5,
    "body": "خدمة ممتازة، الفنانين كانوا في الموعد بالظبط.",
    "locale": "ar",
    "moderation_status": "pending",
    "submitted_at": "2026-05-04T08:32:11Z"
  },
  "meta": {},
  "errors": []
}
```

`PublicServiceReviewResource` — public listing example (EN locale):

```json
{
  "data": [
    {
      "public_id": "01J9XR2K8HZJVNKQR1WBM7P3CD",
      "rating": 5,
      "body": "Great service, performers were on time.",
      "locale": "en",
      "reviewer_first_name": "Ahmed",
      "submitted_at": "2026-05-04T08:32:11Z"
    },
    {
      "public_id": "01J9XR3M0AVZ4M8KQR5XBM7P3CD",
      "rating": 4,
      "body": "خدمة جيدة لكن السعر مرتفع شوية",
      "locale": "ar",
      "reviewer_first_name": "Verified Customer",
      "submitted_at": "2026-05-03T14:22:09Z"
    }
  ],
  "meta": {
    "next_cursor": "eyJpZCI6MTIzNDU2fQ==",
    "prev_cursor": null
  },
  "errors": []
}
```

`RatingSummaryResource` — example:

```json
{
  "data": {
    "rating_avg": 4.62,
    "rating_count": 137
  },
  "meta": {},
  "errors": []
}
```

### Error response examples

`409 Conflict` — duplicate review:

```json
{
  "data": null,
  "meta": {},
  "errors": [
    {
      "code": "review_already_exists",
      "message": "You have already submitted a review for this booking item.",
      "existing_review_public_id": "01J9XR2K8HZJVNKQR1WBM7P3CD"
    }
  ]
}
```

`422 Unprocessable Entity` — booking item not completed:

```json
{
  "data": null,
  "meta": {},
  "errors": [
    {
      "code": "booking_item_not_completed",
      "message": "Reviews can only be submitted after the booking item is completed."
    }
  ]
}
```

### api-registry.md Update Plan

After implementation, append to `.specify/memory/api-registry.md`:

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response | Documented |
|---|---|---|---|---|---|---|---|---|
| POST | /api/v1/customer/booking-items/{bookingItemPublicId}/review | Reviews | 5.1 | sanctum-token | customer | SubmitServiceReviewRequest | ApiResponse{data: ServiceReviewResource, meta, errors} | ✅ postman |
| POST | /api/v1/customer/booking-vendors/{bookingVendorPublicId}/review | Reviews | 5.1 | sanctum-token | customer | SubmitVendorReviewRequest | ApiResponse{data: VendorReviewResource, meta, errors} | ✅ postman |
| GET | /api/v1/customer/reviews | Reviews | 5.1 | sanctum-token | customer | — | ApiResponse{data: MyReviewResource[], meta(cursor), errors} | ✅ postman |
| DELETE | /api/v1/customer/reviews/{reviewPublicId} | Reviews | 5.1 | sanctum-token | customer | — | 204 No Content | ✅ postman |
| GET | /api/v1/public/services/{servicePublicId}/reviews | Reviews | 5.1 | none | — | — | ApiResponse{data: PublicServiceReviewResource[], meta(cursor), errors} | ✅ postman |
| GET | /api/v1/public/vendors/{vendorPublicId}/reviews | Reviews | 5.1 | none | — | — | ApiResponse{data: PublicVendorReviewResource[], meta(cursor), errors} | ✅ postman |
| GET | /api/v1/public/services/{servicePublicId}/rating-summary | Reviews | 5.1 | none | — | — | ApiResponse{data: RatingSummaryResource, meta, errors} | ✅ postman |
| GET | /api/v1/public/vendors/{vendorPublicId}/rating-summary | Reviews | 5.1 | none | — | — | ApiResponse{data: RatingSummaryResource, meta, errors} | ✅ postman |

### Bruno + Postman Collections

Create:

- `docs/api/collections/reviews.bru` — bundles all 8 endpoints with auth setup
- `docs/api/collections/reviews.postman_collection.json` — same coverage, Postman v2.1 schema
- `docs/api/collections/reviews/` — per-endpoint `.bru` files for granular runs

Minimum Postman entries:

- `POST Submit Service Review (Customer)` — bearer auth, `{{base_url}}/api/v1/customer/booking-items/{{booking_item_id}}/review`, body `{"rating": 5, "body": "Great service"}`
- `POST Submit Vendor Review (Customer)` — same shape on `/booking-vendors/{{booking_vendor_id}}/review`
- `GET My Reviews (Customer)` — bearer auth
- `DELETE My Review (Customer)` — bearer auth
- `GET Public Service Reviews` — no auth, with `Accept-Language: ar` header
- `GET Public Vendor Reviews` — no auth
- `GET Service Rating Summary` — no auth
- `GET Vendor Rating Summary` — no auth

---

## 9. Packages Used

All packages are in `docs/specs/10_Package_List.md`. **No new `composer require` needed.**

| Package | `10_Package_List.md` §ref | Usage in Phase 5.1 |
|---|---|---|
| `spatie/laravel-permission:^6.10` | §2 Identity & Authorization | `moderate_service_review`, `moderate_vendor_review`, `hide_review` permissions; gate on `ReviewModerationPage` and `ModerateReviewAction` |
| `spatie/laravel-translatable:^6.8` | §2 Catalog & Translatable Content | `review_moderation_log.reason` JSON column |
| `spatie/laravel-model-states:^2.7` | §2 Domain Modeling | `ReviewModerationState` machine on `ServiceReview` and `VendorReview` |
| `bezhansalleh/filament-shield` | §3 Filament Plugins | Permissions for `ReviewModerationPage` |
| `filament/filament:^3.x` | §3 Filament Core | `ReviewModerationPage` (Filament Page, not Resource) |
| `pestphp/pest:^3.x` + `pestphp/pest-plugin-laravel` | §4 Dev / Quality | Feature, unit, architecture tests |
| `laravel/sanctum:^4.0` | §1 Foundation | Token auth on customer endpoints |

---

## 10. Architecture Tests

Add to `tests/Architecture/`:

### New test: `ReviewsModuleNoCrossImportTest.php`

```php
test('Reviews module does not import Eloquent models from other modules')
    ->expect('App\Modules\Reviews')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Identity\Domain\Models',
        'App\Modules\Payments\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
        'App\Modules\Communication\Domain\Models',
    ]);
```

### Extended test: `AppendOnlyTablesHaveNoSoftDeletesTest.php`

```php
test('ReviewModerationLog model is append-only')
    ->expect('App\Modules\Reviews\Domain\Models\ReviewModerationLog')
    ->not->toUse('Illuminate\Database\Eloquent\SoftDeletes');

test('review_moderation_log table has no updated_at column')
    // assertion via Schema::getColumnListing('review_moderation_log')
    ;
```

### Extended test: `NoIfElseOnProductTypeStringTest.php`

```php
test('Reviews module does not branch on product_type strings')
    ->expect('App\Modules\Reviews')
    ->not->toHaveCode("if.*product_type.*===")
    ->not->toHaveCode("elseif.*product_type.*===")
    ->not->toHaveCode("match.*product_type"); // Reviews is cross-type — no match either
```

### Extended test: `EventAfterCommitTest.php` — Reviews section

```php
test('Reviews module Actions fire events only after DB::afterCommit')
    ->expect('App\Modules\Reviews\Application\Actions')
    ->toUse('Illuminate\Support\Facades\DB')
    // Manual review check: every `event(...)` or `Event::dispatch(...)` call inside an Action
    // must be wrapped in DB::afterCommit() — enforce by code review + targeted Pest tests
    // that assert event NOT fired when transaction rolled back.
    ;
```

### New test: `ReviewsModuleUsesContractsForCrossModuleReadsTest.php`

```php
test('Reviews actions depend on Booking/Catalog/Identity contracts only, not their concrete classes')
    ->expect('App\Modules\Reviews\Application\Actions')
    ->not->toUse([
        'App\Modules\Booking\Infrastructure',
        'App\Modules\Catalog\Infrastructure',
        'App\Modules\Identity\Infrastructure',
    ]);
```

---

## 11. Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §Phase 5.1)

| Feature | Decision | Target |
|---|---|---|
| Vendor responses to reviews | Schema `review_responses` created, `RespondToReviewAction` scaffold only (throws `NotImplementedYet`); no customer endpoint, no Filament UI | Phase 6.0 |
| Review locale auto-detection | `locale` stamped from `customer_profiles.preferred_locale`; `mixed` ENUM value reserved for future detection | Phase 6.0 |
| Per-product-type review eligibility windows | Single rule: any `completed` `booking_item` is eligible indefinitely | Phase 6.0 |
| Review reminder notifications ("rate your experience") | Not wired in 5.1; Phase 6.0 will add Communication template + listener on `BookingItemCompleted` | Phase 6.0 |
| Customer notification on moderation outcome | Not wired in 5.1; Phase 6.0 will add Communication templates for `ReviewApproved` / `ReviewRejected` | Phase 6.0 |
| Image attachments on reviews | Out of Phase 1 entirely | Phase 2 |
| Automated profanity / spam filtering | Admin moderation is sole gate in Phase 1 | Phase 2 |
| Review helpfulness votes / report-this-review | Not in scope | Phase 2 |
| Refund-driven review suppression | Out of scope | Phase 6.0+ |

---

## Implementation Order (Day 1 — Single Day)

### Morning — Schema + Domain + Contracts (T001–T012)

1. [T001] `ReviewsServiceProvider` skeleton — register in `bootstrap/providers.php`; load migrations from `Reviews/Database/Migrations`; load translations from `Reviews/Resources/lang`; load routes from `Reviews/Routes/`.
2. [T002] Migration: `service_reviews` (with UNIQUE on `booking_item_id`, soft-delete).
3. [T003] Migration: `vendor_reviews` (with UNIQUE on `booking_vendor_id`, soft-delete).
4. [T004] Migration: `review_responses` (polymorphic `(review_type, review_id)`).
5. [T005] Migration: `review_moderation_log` (append-only, polymorphic; no `updated_at`, no soft-delete).
6. [T006] `ServiceReview` model — `moderation_status` cast, soft-delete, `belongsTo` to Service via FK column only (no model import — relationship loaded via Catalog ContractWriter when needed; Eloquent FK column is fine).
7. [T007] `VendorReview` model — same pattern.
8. [T008] `ReviewResponse` model — polymorphic `belongsTo(reviewable)` resolved by `review_type` ENUM.
9. [T009] `ReviewModerationLog` model — `reason` translatable JSON cast; `created_at` only; explicit `public $timestamps = false;` then manual `created_at` set.
10. [T010] Enums: `ModerationStatus.php`, `ReviewLocale.php`, `ReviewType.php`.
11. [T011] Domain Events: `ReviewSubmitted`, `ReviewApproved`, `ReviewRejected`, `ReviewHidden`, `ReviewSelfDeleted`, `ServiceRatingRecomputed`, `VendorRatingRecomputed`.
12. [T012] Contracts:
    - `Reviews\Domain\Contracts\BookingItemReviewabilityReader`
    - `Reviews\Domain\Contracts\BookingVendorReviewabilityReader`
    - `Reviews\Domain\Contracts\ServiceRatingWriter`
    - `Reviews\Domain\Contracts\VendorRatingWriter`
    - `Reviews\Domain\Contracts\ServiceReviewRepository`
    - `Reviews\Domain\Contracts\VendorReviewRepository`
    - `Reviews\Domain\Contracts\ReviewModerationLogRepository`

### Midday — Application + Listener + Cross-module Implementations (T013–T021)

13. [T013] DTOs: `SubmitReviewData`, `ModerateReviewData` (records the from→to transition + reason).
14. [T014] `SubmitServiceReviewAction::execute(SubmitReviewData)` — eligibility via contract → uniqueness check → insert pending row → `DB::afterCommit` fires `ReviewSubmitted`.
15. [T015] `SubmitVendorReviewAction::execute(SubmitReviewData)` — same pattern using `BookingVendorReviewabilityReader`.
16. [T016] `ModerateReviewAction::execute(Review $review, ModerateReviewData)` — single Action handles both review types via the `review_type` discriminator on the model; validates allowed transition per state machine; appends to `review_moderation_log`; dispatches typed event after commit.
17. [T017] `RespondToReviewAction` — **scaffold only**: `public function execute(): never { throw new NotImplementedYet('Phase 6.0'); }`. Existence required so Pest "scaffolded" architecture test passes.
18. [T018] `DeleteOwnReviewAction::execute(int $reviewId, int $userId)` — soft-deletes and dispatches `ReviewSelfDeleted` after commit.
19. [T019] `RatingAggregationService` — recomputes `AVG(rating)` and `COUNT(*)` for a (subject_type, subject_id) pair filtered by `moderation_status='approved'` and `deleted_at IS NULL`.
20. [T020] `RecomputeRatingOnApproval` listener (`implements ShouldQueue`) — subscribes to all 4 events that change visible rating set; calls `RatingAggregationService` then writes via `ServiceRatingWriter` / `VendorRatingWriter` contract; dispatches `ServiceRatingRecomputed` / `VendorRatingRecomputed` after commit.
21. [T021] Cross-module contract implementations (in their respective modules):
    - `Booking\Infrastructure\Repositories\EloquentBookingItemReviewabilityReader` (implements Reviews contract)
    - `Booking\Infrastructure\Repositories\EloquentBookingVendorReviewabilityReader`
    - `Catalog\Infrastructure\Repositories\EloquentServiceRatingWriter`
    - `Identity\Infrastructure\Repositories\EloquentVendorRatingWriter`
    - Bind in respective ServiceProviders.

### Afternoon — HTTP + Filament + Translations (T022–T032)

22. [T022] Form Requests: `SubmitServiceReviewRequest`, `SubmitVendorReviewRequest` — `rating` (1..5), `body` (nullable, max 2000).
23. [T023] API Resources:
    - `ServiceReviewResource` (customer-owned view — full payload incl. moderation_status)
    - `VendorReviewResource` (same shape)
    - `MyReviewResource` (customer's "my reviews" list — both review types unified)
    - `PublicServiceReviewResource` (public — no moderation_status; reviewer_first_name only)
    - `PublicVendorReviewResource` (public)
    - `RatingSummaryResource` (`rating_avg`, `rating_count`)
24. [T024] Controllers (3-line action body MAX, all delegate to Actions):
    - `Customer\SubmitServiceReviewController@store`
    - `Customer\SubmitVendorReviewController@store`
    - `Customer\ListMyReviewsController@index`
    - `Customer\DeleteOwnReviewController@destroy`
    - `Public\ListServiceReviewsController@index`
    - `Public\ListVendorReviewsController@index`
    - `Public\GetServiceRatingSummaryController@show`
    - `Public\GetVendorRatingSummaryController@show`
25. [T025] Routes:
    - `Reviews/Routes/customer.php` — sanctum-token, role:customer, idempotency:optional on POST/DELETE
    - `Reviews/Routes/public.php` — no auth, public listings + rating summary
26. [T026] `ReviewModerationPage` (Filament Page) — unified queue with filters (review type, rating, vendor, locale, date range); approve/reject/hide actions delegate to `ModerateReviewAction`; bulk approve action.
27. [T027] Run `php artisan shield:generate --all` — generates `page_ReviewModerationPage`, `moderate_service_review`, `moderate_vendor_review`, `hide_review` permissions; assign to `admin` and `moderator` roles via seeder.
28. [T028] Translations: `Reviews/Resources/lang/en/reviews.php` and `ar/reviews.php` — labels, validation messages, "Verified Customer" fallback, error codes.
29. [T029] Pest: `SubmitServiceReviewTest` — happy path × 3 product types (rental/sale/digital) × locale × eligibility (completed vs not) × uniqueness 409 × auth 401 × non-owner 403.
30. [T030] Pest: `SubmitVendorReviewTest` — happy path × all-items-completed gate × partial-completion 422 × uniqueness × auth.
31. [T031] Pest: `ModerateReviewActionTest` — all 5 transitions (pending→approved, pending→rejected, approved→hidden, hidden→approved, approved→rejected); each writes `review_moderation_log`; rejection from approved triggers aggregation; rejection is terminal.
32. [T032] Pest: `RatingAggregationListenerTest` (queued) — fires on all 4 events; correctly excludes hidden + rejected + soft-deleted; idempotent re-run.

### End of Day — Wiring + Verification (T033–T038)

33. [T033] Architecture tests:
    - `ReviewsModuleNoCrossImportTest`
    - Extend `AppendOnlyTablesHaveNoSoftDeletesTest` for `review_moderation_log`
    - Extend `NoIfElseOnProductTypeStringTest` for `App\Modules\Reviews`
    - `ReviewsModuleUsesContractsForCrossModuleReadsTest`
34. [T034] Update `.specify/memory/api-registry.md` with 8 new endpoint rows.
35. [T035] Create `docs/api/collections/reviews.bru` + `docs/api/collections/reviews.postman_collection.json` + `docs/api/collections/reviews/` per-endpoint files.
36. [T036] Run `php artisan migrate` against staging DB; verify all 4 tables created with correct indexes.
37. [T037] Run full test suite: `./vendor/bin/pest --group=reviews` then full `./vendor/bin/pest --bail`.
38. [T038] `php artisan pint` + `./vendor/bin/phpstan analyse` clean.

---

## 12. Open items deferred to plan-phase confirmation

These low-impact items were left open by `/speckit.clarify` and resolved here:

| # | Item | Resolution in this plan |
|---|---|---|
| 1 | Reviewer first-name source column | `users.name` (single column) — derive first name as `explode(' ', trim($user->name))[0]`. No Identity-side schema change needed. Fallback: localized "Verified Customer" / "عميل موثق". |
| 2 | Listener retry / dead-letter behavior | Standard Laravel queue retry (3 attempts, exponential backoff). Failed jobs land in `failed_jobs` table. Listener is idempotent (recomputation produces same value), so retries are safe. No bespoke dead-letter handling needed. |
| 3 | Rating columns on `services` and `vendor_profiles` | Already present in locked schema (`11_DB_Schema.md` lines 150 and 405): `rating_avg DECIMAL(3,2)` + `rating_count INT UNSIGNED` on both tables. No new migration needed; Reviews listener writes via Contract. |
