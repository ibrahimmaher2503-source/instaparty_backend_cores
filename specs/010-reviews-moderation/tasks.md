# Tasks: Phase 5.1 — Reviews + Moderation

**Input**: Design documents from `specs/010-reviews-moderation/`
**Prerequisites**: [spec.md](./spec.md) ✅ | [plan.md](./plan.md) ✅ | [ADR-0011](../../docs/adr/0011-reviews-module.md) ✅ Accepted
**Phase**: 5.1 — 1 day, Week 6
**PRD coverage**: PRD §5.1 + Software Description §3 + Customer Journey step 60 + Admin Journey step 26
**Spec User Stories**: US1 (P1) service review submission · US2 (P1) vendor review submission · US3 (P1) admin moderation · US4 (P2) public listings · US5 (P3 — deferred, schema scaffold only)

**Format**:
```
- [ ] T001 Description
  - File: exact/path/to/file.php
  - Source: spec.md FR-RXX or plan.md §X or ADR-0011 §X
  - [P] if parallel-safe (must not touch same file as other [P] in same batch)
```

---

## Phase 1 — Setup & ADR Verification

- [ ] T001 Create `ReviewsServiceProvider` skeleton and register in `bootstrap/providers.php`; load migrations from `Reviews/Database/Migrations`, translations from `Reviews/Resources/lang`, routes from `Reviews/Routes/`
  - File: `app/Modules/Reviews/Providers/ReviewsServiceProvider.php`, `bootstrap/providers.php`
  - Source: ADR-0011 §5; `.claude/rules/modules.md` §"Module ServiceProvider"

---

## Phase 2 — Foundational (blocking prerequisites for all user stories)

### Migrations (FK dependency order — sequential)

- [ ] T002 Migration: `service_reviews` table — UNIQUE `booking_item_id`, `(service_id, moderation_status)` index, `(user_id, created_at)` index, soft-delete, utf8mb4
  - File: `app/Modules/Reviews/Database/Migrations/2026_05_04_100001_create_service_reviews_table.php`
  - Source: plan.md §3 migration 1; data-model.md §1; `.claude/rules/migrations.md`

- [ ] T003 Migration: `vendor_reviews` table — UNIQUE `booking_vendor_id`, `(vendor_profile_id, moderation_status)` index, `(user_id, created_at)` index, soft-delete, utf8mb4
  - File: `app/Modules/Reviews/Database/Migrations/2026_05_04_100002_create_vendor_reviews_table.php`
  - Source: plan.md §3 migration 2; data-model.md §2; `.claude/rules/migrations.md`

- [ ] T004 Migration: `review_responses` table — polymorphic `(review_type, review_id)` no FK (two-table target), `(vendor_profile_id, moderation_status)` index, standard timestamps, utf8mb4
  - File: `app/Modules/Reviews/Database/Migrations/2026_05_04_100003_create_review_responses_table.php`
  - Source: plan.md §3 migration 3; data-model.md §3; spec.md US5 (schema only in 5.1)

- [ ] T005 Migration: `review_moderation_log` table — APPEND-ONLY: `created_at` only (no `updated_at`, no `deleted_at`, no `softDeletes()`), polymorphic `(review_type, review_id)`, `reason` JSON, `(review_type, review_id, created_at)` index, `(moderator_id, created_at)` index
  - File: `app/Modules/Reviews/Database/Migrations/2026_05_04_100004_create_review_moderation_log_table.php`
  - Source: plan.md §3 migration 4; data-model.md §4; CLAUDE.md §"Append-only tables"; spec.md FR-R8

### Models (parallel-safe after migrations — distinct files)

- [ ] T006 `ServiceReview` model — `HasUlids` for `public_id`, `SoftDeletes`, `moderation_status` cast to `ModerationStatus` enum via `spatie/laravel-model-states`, `rating` cast to int, `locale` cast to `ReviewLocale` enum, `belongsTo` Service via `service_id`, `belongsTo` User via `user_id` and `moderated_by`
  - File: `app/Modules/Reviews/Domain/Models/ServiceReview.php`
  - Source: ADR-0011 §6.1; data-model.md §1; `.claude/rules/modules.md` §"Models hold relationships/casts/scopes ONLY"
  - [P]

- [ ] T007 `VendorReview` model — identical shape to `ServiceReview` but with `belongsTo` VendorProfile via `vendor_profile_id`; same casts and soft-delete
  - File: `app/Modules/Reviews/Domain/Models/VendorReview.php`
  - Source: ADR-0011 §6.1; data-model.md §2
  - [P]

- [ ] T008 `ReviewResponse` model — `review_type` cast to `ReviewType` enum, polymorphic `morphTo('reviewable')` resolved by `review_type`, `belongsTo` VendorProfile, `moderation_status` cast, standard timestamps
  - File: `app/Modules/Reviews/Domain/Models/ReviewResponse.php`
  - Source: data-model.md §3; spec.md US5 (scaffold only — no business logic)
  - [P]

- [ ] T009 `ReviewModerationLog` model — `public $timestamps = false;`, manual `created_at` (set on `create()`), `reason` JSON cast via `spatie/laravel-translatable` (`$translatable = ['reason']`), NO `SoftDeletes`, NO `update()` or `save()` calls anywhere
  - File: `app/Modules/Reviews/Domain/Models/ReviewModerationLog.php`
  - Source: data-model.md §4; spec.md FR-R8; CLAUDE.md §"Append-only tables"; plan.md §10 architecture tests

### Enums (parallel-safe — distinct files)

- [ ] T010 Create `ModerationStatus`, `ReviewLocale`, and `ReviewType` PHP backed enums
  - File: `app/Modules/Reviews/Domain/Enums/ModerationStatus.php`, `app/Modules/Reviews/Domain/Enums/ReviewLocale.php`, `app/Modules/Reviews/Domain/Enums/ReviewType.php`
  - Source: data-model.md §1 (ENUM columns); spec.md §"Key Entities"
  - [P]

### Domain Events (parallel-safe — distinct files)

- [ ] T011 Create all 7 domain events: `ReviewSubmitted`, `ReviewApproved`, `ReviewRejected`, `ReviewHidden`, `ReviewSelfDeleted`, `ServiceRatingRecomputed`, `VendorRatingRecomputed` — each as a simple readonly class with named constructor arguments matching plan.md §7 payload tables
  - File: `app/Modules/Reviews/Domain/Events/ReviewSubmitted.php`, `ReviewApproved.php`, `ReviewRejected.php`, `ReviewHidden.php`, `ReviewSelfDeleted.php`, `ServiceRatingRecomputed.php`, `VendorRatingRecomputed.php`
  - Source: plan.md §7 "Events Reviews module publishes"; spec.md FR-R9; CLAUDE.md §7 (events fire after commit)
  - [P]

### Contracts — 7 interfaces (parallel-safe — distinct files)

- [ ] T012 Create all 7 contract interfaces in `Reviews\Domain\Contracts\`: `BookingItemReviewabilityReader` (with `isReviewable()` + `resolveReviewableContext()`), `BookingVendorReviewabilityReader` (same shape), `ServiceRatingWriter` (`update(int $serviceId, float $newAverage, int $newCount): void`), `VendorRatingWriter` (same), `ServiceReviewRepository`, `VendorReviewRepository`, `ReviewModerationLogRepository`
  - File: `app/Modules/Reviews/Domain/Contracts/BookingItemReviewabilityReader.php`, `BookingVendorReviewabilityReader.php`, `ServiceRatingWriter.php`, `VendorRatingWriter.php`, `ServiceReviewRepository.php`, `VendorReviewRepository.php`, `ReviewModerationLogRepository.php`
  - Source: contracts/internal-contracts.md §1–§7; plan.md §"Cross-module reads via Contracts"; spec.md FR-R15
  - [P]

### DTOs

- [ ] T013 Create `SubmitReviewData` DTO (fields: `bookingSubjectPublicId`, `userId`, `rating`, `body`, `locale`) and `ModerateReviewData` DTO (fields: `reviewType`, `toStatus`, `moderatorId`, `reason`)
  - File: `app/Modules/Reviews/Application/DTOs/SubmitReviewData.php`, `app/Modules/Reviews/Application/DTOs/ModerateReviewData.php`
  - Source: plan.md §"Midday T013"; `.claude/rules/actions.md` §"Accept either typed primitives, a DTO, or a Form Request"
  - [P]

### Cross-module Contract Implementations (parallel-safe — different modules)

- [ ] T021 Implement the 4 cross-module Eloquent repository implementations and bind them in the respective ServiceProviders:
  - `EloquentBookingItemReviewabilityReader` — checks `booking_items.item_status = 'completed'` + `booking.user_id = $userId` + not soft-deleted
  - `EloquentBookingVendorReviewabilityReader` — checks `NOT EXISTS(SELECT 1 FROM booking_items WHERE booking_vendor_id=? AND item_status != 'completed')` + ownership
  - `EloquentServiceRatingWriter` — `UPDATE services SET rating_avg = round($avg, 2), rating_count = $count WHERE id = $serviceId`
  - `EloquentVendorRatingWriter` — same pattern on `vendor_profiles`
  - Files: `app/Modules/Booking/Infrastructure/Repositories/EloquentBookingItemReviewabilityReader.php`, `EloquentBookingVendorReviewabilityReader.php`, `app/Modules/Catalog/Infrastructure/Repositories/EloquentServiceRatingWriter.php`, `app/Modules/Identity/Infrastructure/Repositories/EloquentVendorRatingWriter.php`
  - Bind: `BookingServiceProvider::register()` × 2, `CatalogServiceProvider::register()`, `IdentityServiceProvider::register()`
  - Source: contracts/internal-contracts.md §1–§4 + binding summary; plan.md §"Midday T021"; spec.md FR-R9, FR-R15
  - [P]

### Eloquent Repository Implementations (Reviews module internal)

- [ ] T021b Implement `EloquentServiceReviewRepository`, `EloquentVendorReviewRepository`, `EloquentReviewModerationLogRepository`; bind all three as singletons in `ReviewsServiceProvider::register()`
  - Files: `app/Modules/Reviews/Infrastructure/Repositories/EloquentServiceReviewRepository.php`, `EloquentVendorReviewRepository.php`, `EloquentReviewModerationLogRepository.php`
  - Source: contracts/internal-contracts.md §5–§7; `.claude/rules/modules.md` §"Where things go"
  - [P]

---

## Phase 3 — US1 + US2 (P1): Customer submits a review

**Story Goal (US1):** Customer can submit a service review for a completed `booking_item` — creates a `service_reviews` row with `moderation_status='pending'`, enforces uniqueness, and fires `ReviewSubmitted`.

**Story Goal (US2):** Customer can submit a vendor review for a fully-completed `booking_vendor` — same shape, `vendor_reviews` table.

**Independent Test:** Seed customer + vendor + service + `booking_item` with `item_status='completed'`. POST to `/api/v1/customer/booking-items/{id}/review` with `{"rating": 5}`. Assert 201 with `moderation_status=pending`. Re-POST same endpoint → assert 409 with `review_already_exists`. Repeat with a `booking_vendor` whose all items are completed for US2.

- [ ] T014 [US1] `SubmitServiceReviewAction::execute(SubmitReviewData)` — (1) call `BookingItemReviewabilityReader::resolveReviewableContext()` → 422 `booking_item_not_completed` if null; (2) check `ServiceReviewRepository::findByBookingItemId()` → 409 `review_already_exists` if present; (3) `DB::transaction` wraps `ServiceReviewRepository::create()`; (4) `DB::afterCommit(fn () => event(new ReviewSubmitted(...)))` — never inside the transaction
  - File: `app/Modules/Reviews/Application/Actions/SubmitServiceReviewAction.php`
  - Source: plan.md §"Midday T014"; spec.md FR-R1, FR-R3, FR-R6; `.claude/rules/actions.md` §"Transactions"; CLAUDE.md §7

- [ ] T015 [US2] `SubmitVendorReviewAction::execute(SubmitReviewData)` — identical pattern using `BookingVendorReviewabilityReader` + `VendorReviewRepository`; 422 error code is `booking_vendor_items_not_all_completed`
  - File: `app/Modules/Reviews/Application/Actions/SubmitVendorReviewAction.php`
  - Source: plan.md §"Midday T015"; spec.md FR-R2, FR-R3; contracts/reviews-api.yaml §/customer/booking-vendors/{id}/review

- [ ] T018 [US1] `DeleteOwnReviewAction::execute(int $reviewId, string $reviewType, int $userId)` — finds the review row; 403 if `user_id !== $userId`; soft-deletes via repository; `DB::afterCommit` fires `ReviewSelfDeleted` with `previous_moderation_status`
  - File: `app/Modules/Reviews/Application/Actions/DeleteOwnReviewAction.php`
  - Source: plan.md §"Midday T018"; spec.md FR-R14; CLAUDE.md §7

- [ ] T022 [US1] [US2] Form Requests: `SubmitServiceReviewRequest` and `SubmitVendorReviewRequest` — `rating` required int between:1,5; `body` nullable string max:2000; `@bodyParam` PHPDoc per plan.md §8
  - File: `app/Modules/Reviews/Http/Requests/SubmitServiceReviewRequest.php`, `app/Modules/Reviews/Http/Requests/SubmitVendorReviewRequest.php`
  - Source: plan.md §8 "@bodyParam PHPDoc"; spec.md FR-R4; contracts/reviews-api.yaml §SubmitReviewRequest
  - [P]

- [ ] T023 [US1] [US2] [US4] API Resources (all 6):
  - `ServiceReviewResource` — full customer-owned payload incl. `moderation_status`, `service_public_id`, `booking_item_public_id`
  - `VendorReviewResource` — same shape for vendor reviews
  - `MyReviewResource` — unified list item (discriminates by `review_type` to delegate to Service/VendorReviewResource)
  - `PublicServiceReviewResource` — no `moderation_status`; `reviewer_first_name` extracted via `preg_split('/\s+/', trim($user->name), 2)[0]` with "Verified Customer"/"عميل موثق" fallback
  - `PublicVendorReviewResource` — same as public service resource
  - `RatingSummaryResource` — `rating_avg` (float), `rating_count` (int)
  - File: `app/Modules/Reviews/Http/Resources/ServiceReviewResource.php`, `VendorReviewResource.php`, `MyReviewResource.php`, `PublicServiceReviewResource.php`, `PublicVendorReviewResource.php`, `RatingSummaryResource.php`
  - Source: plan.md §8 "@response PHPDoc"; contracts/reviews-api.yaml §schemas; research.md R-4, R-7; spec.md FR-R10, FR-R11

- [ ] T024 [US1] [US2] [US4] Controllers — 3-line action body MAX, all delegate to Actions via constructor injection (8 controllers total):
  - `Customer\SubmitServiceReviewController@store` → `SubmitServiceReviewAction`
  - `Customer\SubmitVendorReviewController@store` → `SubmitVendorReviewAction`
  - `Customer\ListMyReviewsController@index` → queries repos directly (cursor paginate, `created_at DESC, id DESC`); returns `MyReviewResource` collection
  - `Customer\DeleteOwnReviewController@destroy` → `DeleteOwnReviewAction`
  - `Public\ListServiceReviewsController@index` → `ServiceReviewRepository::listApprovedForService()` with cursor; returns `PublicServiceReviewResource`
  - `Public\ListVendorReviewsController@index` → same for vendor
  - `Public\GetServiceRatingSummaryController@show` → `ServiceReviewRepository::aggregateApprovedForService()`; returns `RatingSummaryResource`
  - `Public\GetVendorRatingSummaryController@show` → same for vendor
  - File: `app/Modules/Reviews/Http/Controllers/Customer/SubmitServiceReviewController.php`, `SubmitVendorReviewController.php`, `ListMyReviewsController.php`, `DeleteOwnReviewController.php`, `app/Modules/Reviews/Http/Controllers/Public/ListServiceReviewsController.php`, `ListVendorReviewsController.php`, `GetServiceRatingSummaryController.php`, `GetVendorRatingSummaryController.php`
  - Source: plan.md §8 endpoints table; CLAUDE.md §1 "Thin controllers, max 3 lines"; contracts/reviews-api.yaml §paths

- [ ] T025 [US1] [US2] [US4] Routes — `customer.php` (sanctum-token, role:customer, `idempotency:optional` on POST/DELETE); `public.php` (no auth)
  - File: `app/Modules/Reviews/Routes/customer.php`, `app/Modules/Reviews/Routes/public.php`
  - Source: plan.md §"Afternoon T025"; spec.md §"API Endpoints"; plan.md §6 "Idempotency"

- [ ] T028 [US1] [US2] [US3] [US4] Translations — create EN + AR reviews.php files with all labels, validation messages, error codes, and `reviews.verified_customer` fallback key
  - File: `app/Modules/Reviews/Resources/lang/en/reviews.php`, `app/Modules/Reviews/Resources/lang/ar/reviews.php`
  - Source: plan.md §"Afternoon T028"; research.md R-7; spec.md FR-R11; plan.md §5 "Locale Coverage"

- [ ] T029 [US1] Pest: `SubmitServiceReviewTest` — happy path × 3 product types (rental/sale/digital) with `->group('reviews', 'rental|sale|digital')`; locale stamp from `customer_profiles.preferred_locale`; uniqueness 409 (`review_already_exists`); `booking_item_not_completed` 422; auth 401; non-owner 403; body-null (still valid); HTML in body (stored verbatim, not parsed)
  - File: `tests/Feature/Modules/Reviews/SubmitServiceReviewTest.php`
  - Source: plan.md §"Afternoon T029"; spec.md US1 acceptance scenarios 1–7; spec.md §"Edge Cases"

- [ ] T030 [US2] Pest: `SubmitVendorReviewTest` — all-items-completed gate (booking_vendor with 2 items both completed → 201); partial-completion 422 (`booking_vendor_items_not_all_completed`); uniqueness 409; non-owner 403; auth 401
  - File: `tests/Feature/Modules/Reviews/SubmitVendorReviewTest.php`
  - Source: plan.md §"Afternoon T030"; spec.md US2 acceptance scenarios 1–4

---

## Phase 4 — US3 (P1): Admin moderates a pending review

**Story Goal:** Admin approves / rejects / hides reviews through `ReviewModerationPage`; every transition appends to `review_moderation_log`; approved reviews trigger queued rating recomputation.

**Independent Test:** Seed 3 pending reviews (mix service+vendor). As admin, call `ModerateReviewAction` for approve / reject / hide. Assert: moderation_status updated, `review_moderation_log` row appended, `ReviewApproved` event fired after commit, listener dispatched `RecomputeRatingOnApproval`.

- [ ] T016 [US3] `ModerateReviewAction::execute(ServiceReview|VendorReview $review, ModerateReviewData $data)` — validate allowed transition (reject if forbidden per state machine — `rejected` is terminal, `pending→hidden` forbidden); `DB::transaction` wraps `repository->transition()` + `logRepository->append()`; `DB::afterCommit` fires typed event (`ReviewApproved`, `ReviewRejected`, or `ReviewHidden`) using `match($data->toStatus)` per plan.md §7 code snippet; updates `moderated_by` + `moderated_at`
  - File: `app/Modules/Reviews/Application/Actions/ModerateReviewAction.php`
  - Source: plan.md §7 "Pattern in ModerateReviewAction::execute()"; spec.md FR-R7, FR-R8; data-model.md §"State transitions"; `.claude/rules/actions.md`

- [ ] T017 [US5] `RespondToReviewAction` — **scaffold only**: `public function execute(): never { throw new \App\Modules\Shared\Exceptions\NotImplementedYet('Phase 6.0'); }`
  - File: `app/Modules/Reviews/Application/Actions/RespondToReviewAction.php`
  - Source: plan.md §"Midday T017"; spec.md US5 (deferred); plan.md §11 cut-list

- [ ] T019 [US3] `RatingAggregationService` — `recompute(string $subjectType, int $subjectId): array` — runs `SELECT AVG(rating) as average, COUNT(*) as count FROM {service_reviews|vendor_reviews} WHERE {service_id|vendor_profile_id} = ? AND moderation_status = 'approved' AND deleted_at IS NULL`; returns `['average' => float, 'count' => int]`
  - File: `app/Modules/Reviews/Application/Services/RatingAggregationService.php`
  - Source: plan.md §"Midday T019"; research.md R-3; spec.md FR-R9; data-model.md §"Soft-delete + aggregation interaction"

- [ ] T020 [US3] `RecomputeRatingOnApproval` listener (`implements ShouldQueue`) — subscribes to `ReviewApproved`, `ReviewRejected`, `ReviewHidden`, `ReviewSelfDeleted`; on `ReviewRejected` only processes when `$event->previousStatus === 'approved'`; on `ReviewSelfDeleted` only when `$event->previousModerationStatus === 'approved'`; calls `RatingAggregationService::recompute()`; writes via `ServiceRatingWriter`/`VendorRatingWriter` contract; `DB::afterCommit` fires `ServiceRatingRecomputed`/`VendorRatingRecomputed`; register in `ReviewsServiceProvider::boot()` event map
  - File: `app/Modules/Reviews/Application/Listeners/RecomputeRatingOnApproval.php`
  - Source: plan.md §"Midday T020" + §7 "Events Reviews module consumes"; research.md R-3; spec.md FR-R9; CLAUDE.md §7

- [ ] T026 [US3] `ReviewModerationPage` (Filament Page, NOT a Resource) — under "Moderation" navigation group; unified Filament Table query with UNION across `service_reviews` + `vendor_reviews` scoped to `moderation_status = 'pending'`; filters for review type, rating, vendor, locale, date range; per-row `Approve` / `Reject` (with reason form in EN+AR) / `Hide` actions delegating to `ModerateReviewAction`; bulk approve action; displays review body verbatim with `[ar]`/`[en]` badge
  - File: `app/Modules/Reviews/Filament/Pages/ReviewModerationPage.php`
  - Source: plan.md §"Afternoon T026"; ADR-0011 §6.5; research.md R-6; spec.md US3 acceptance scenarios; `.claude/rules/filament.md`; `.claude/rules/filament-components.md`

- [ ] T027 [US3] Run `php artisan shield:generate --all`; verify `page_ReviewModerationPage`, `moderate_service_review`, `moderate_vendor_review`, `hide_review` permissions are generated; assign to `admin` and `moderator` roles via seeder or Shield panel
  - File: (permissions in Shield DB / seeder; no source file)
  - Source: plan.md §"Afternoon T027"; CLAUDE.md §"Run shield:generate --all after every new Resource"; `.claude/rules/filament.md` §"Permissions (Shield)"

- [ ] T031 [US3] Pest: `ModerateReviewActionTest` — all 5 valid transitions (pending→approved, pending→rejected, approved→hidden, hidden→approved, approved→rejected); each asserts `review_moderation_log` row appended with correct from/to; rejection from `approved` triggers aggregation (`ReviewRejected` with `previousStatus=approved`); forbidden transitions (rejected→anything, pending→hidden) throw; non-moderator 403; reason required for reject (EN+AR); `moderated_by` + `moderated_at` set after first transition
  - File: `tests/Feature/Modules/Reviews/ModerateReviewActionTest.php`
  - Source: plan.md §"Afternoon T031"; spec.md US3 acceptance scenarios 1–5; data-model.md §"Allowed transitions"

- [ ] T032 [US3] Pest: `RatingAggregationListenerTest` — fires on all 4 trigger events; excludes `pending`, `rejected`, `hidden` from avg; excludes soft-deleted reviews; excludes reviews from soft-deleted users (FR-R13); correctly no-ops `ReviewRejected` when `previousStatus !== 'approved'`; idempotent re-run produces same result; asserts `services.rating_avg` + `rating_count` updated after listener via `ServiceRatingWriter` contract
  - File: `tests/Feature/Modules/Reviews/RatingAggregationListenerTest.php`
  - Source: plan.md §"Afternoon T032"; spec.md FR-R9, FR-R13; data-model.md §"Soft-delete + aggregation interaction"; research.md R-3

---

## Phase 5 — US4 (P2): Public listings

**Story Goal:** Any visitor can read cursor-paginated approved reviews for a service or vendor, see `reviewer_first_name` (never surname), and fetch the aggregate rating summary. Pending / rejected / hidden / soft-deleted reviews never appear.

**Independent Test:** Seed 5 approved + 2 pending + 1 rejected + 1 soft-deleted service reviews. GET `/api/v1/public/services/{publicId}/reviews`. Assert only 5 approved rows returned. Assert `reviewer_first_name` is first-name-only. Assert cursor pagination meta present. GET `.../rating-summary` → `rating_avg` matches `AVG(approved, non-deleted)`.

*(Controllers, resources, and routes were implemented in Phase 3 T023–T025 to keep all HTTP-layer tasks together. The test below is US4-specific.)*

- [ ] T029b [US4] Pest: `PublicServiceReviewListTest` + `PublicVendorReviewListTest` — only approved non-deleted rows in response; cursor pagination (next_cursor + prev_cursor); `reviewer_first_name` is first word of `users.name`; empty-name → "Verified Customer"/"عميل موثق" (locale-aware); `Accept-Language: ar` → AR fallback label; 404 for non-existent or non-published service/vendor; soft-deleted review never appears even if previously approved
  - File: `tests/Feature/Modules/Reviews/PublicServiceReviewListTest.php`, `tests/Feature/Modules/Reviews/PublicVendorReviewListTest.php`
  - Source: spec.md US4 acceptance scenarios 1–3; spec.md FR-R10, FR-R11, FR-R13; research.md R-4, R-7, R-10

---

## Final Phase — Wiring + Verification

- [ ] T033 Architecture tests:
  - `ReviewsModuleNoCrossImportTest` — asserts `App\Modules\Reviews` does not `use` any of `App\Modules\Booking\Domain\Models`, `App\Modules\Catalog\Domain\Models`, `App\Modules\Identity\Domain\Models`
  - Extend `AppendOnlyTablesHaveNoSoftDeletesTest` — assert `ReviewModerationLog` model does not `use SoftDeletes` and `review_moderation_log` table has no `updated_at` column (via `Schema::getColumnListing`)
  - Extend `NoIfElseOnProductTypeStringTest` — scope to `App\Modules\Reviews` (cross-type: no `if.*product_type`, no `elseif.*product_type`, no `match.*product_type`)
  - `ReviewsModuleUsesContractsForCrossModuleReadsTest` — asserts `App\Modules\Reviews\Application\Actions` does not `use` `App\Modules\Booking\Infrastructure` or `App\Modules\Catalog\Infrastructure` or `App\Modules\Identity\Infrastructure`
  - File: `tests/Architecture/ReviewsModuleNoCrossImportTest.php`, `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` (extend), `tests/Architecture/NoIfElseOnProductTypeStringTest.php` (extend), `tests/Architecture/ReviewsModuleUsesContractsForCrossModuleReadsTest.php`
  - Source: plan.md §10; spec.md NFR-R4; spec.md FR-R15; ADR-0011 §4

- [ ] T034 Update `.specify/memory/api-registry.md` with all 8 new endpoint rows per the table in plan.md §8 "api-registry.md Update Plan"
  - File: `.specify/memory/api-registry.md`
  - Source: plan.md §8 "api-registry.md Update Plan"

- [ ] T035 Create Bruno + Postman collections for all 8 reviews endpoints (auth config, sample bodies, AR + EN Accept-Language headers)
  - File: `docs/api/collections/reviews.bru`, `docs/api/collections/reviews.postman_collection.json`, `docs/api/collections/reviews/` (per-endpoint .bru files)
  - Source: plan.md §8 "Bruno + Postman Collections"

- [ ] T036 Run `php artisan migrate` against local and staging DBs; verify 4 tables created with correct indexes via `SHOW INDEX FROM service_reviews`, `vendor_reviews`, `review_moderation_log`
  - File: (DB only — no source file)
  - Source: quickstart.md §2; plan.md §"End of Day T036"

- [ ] T037 Run `./vendor/bin/pest --group=reviews` (all review-tagged tests green); then `./vendor/bin/pest --group=reviews,rental`, `--group=reviews,sale`, `--group=reviews,digital` (eligibility tests per product type); then `./vendor/bin/pest --bail` (full suite)
  - File: (test runner — no source file)
  - Source: quickstart.md §7; plan.md §"End of Day T037"; spec.md §"Exit Criteria" EC-3

- [ ] T038 Run `./vendor/bin/pint` (zero diff) + `./vendor/bin/phpstan analyse` (zero errors); fix any issues before committing
  - File: (code quality tools — no source file)
  - Source: quickstart.md §8; plan.md §"End of Day T038"

---

## Dependencies

```
Phase 1 → Phase 2 (T001 must exist before migrations autoload)
T002–T005 sequential (FK order: service_reviews → vendor_reviews → review_responses → review_moderation_log)
T002–T005 → T006–T009 (models need tables)
T010–T012 → T013 (DTOs depend on Enum types + Contract interfaces)
T012 → T021 (implementations depend on interfaces being defined first)
T012, T021 → T014, T015, T018 (Actions type-hint Contracts)
T014, T015, T018 → T016 (Moderate depends on repos; Delete depends on repos)
T016 → T019 → T020 (aggregation service and listener build on moderation action pattern)
T013, T022 → T014, T015 (Actions accept DTOs + Form Requests indirectly via controllers)
T023 → T024 (controllers return Resource instances)
T024 → T025 (routes point to controllers)
T025 → T029, T029b, T030 (tests exercise routes)
T016 → T031 (test covers moderation transitions)
T019, T020 → T032 (test covers listener + aggregation)
T026 → T027 (shield:generate needs the Page class to exist)
T014–T032 → T033 (architecture tests verify completed code)
T037 → T038 (pint/phpstan after tests pass)
```

## Parallel Execution Summary

Tasks marked `[P]` within the same phase can be run concurrently (they write to distinct files):

- **Phase 2 parallel batch (after T002–T005 complete)**: T006, T007, T008, T009, T010, T011, T012, T013, T021, T021b
- **Phase 3 parallel batch (after Phase 2 complete)**: T022, T017 (scaffold)

## User Story → Task Map

| User Story | Tasks |
|---|---|
| US1 (P1) — Service review submission | T014, T018, T022, T023, T024, T025, T028, T029 |
| US2 (P1) — Vendor review submission | T015, T022, T023, T024, T025, T030 |
| US3 (P1) — Admin moderation | T016, T019, T020, T026, T027, T028, T031, T032 |
| US4 (P2) — Public listings | T023, T024, T025, T029b |
| US5 (P3 — deferred) — Vendor response scaffold | T017 |
| Foundational (all stories) | T001–T013, T021, T021b |
| Verification | T033–T038 |

## Exit Criteria Checklist

Per spec.md §"Exit Criteria":

- [ ] EC-1: Customer can submit a review only after `booking_item.item_status = 'completed'` — verified by T029 (SubmitServiceReviewTest) covering all 3 product types
- [ ] EC-2: Admin moderates via `ReviewModerationPage` → rating updates — verified by T031 (ModerateReviewActionTest) + T032 (RatingAggregationListenerTest)
- [ ] EC-3: All Pest tests pass (`./vendor/bin/pest --bail`) — verified by T037
- [ ] EC-4: `review_moderation_log` append-only invariant passes architecture test — verified by T033
- [ ] EC-5: Reviews module does not import Booking/Catalog/Identity models — verified by T033 (ReviewsModuleNoCrossImportTest)
