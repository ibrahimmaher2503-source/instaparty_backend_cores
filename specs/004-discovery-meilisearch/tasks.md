# Tasks: Discovery — Meilisearch Search & Wishlists

**Input**: `specs/004-discovery-meilisearch/` (spec, plan, data-model, research, contracts, quickstart)
**Branch**: `004-discovery-meilisearch`
**Phase**: 3.0 — Week 4
**Generated**: 2026-04-30

> ⚠️ **BEFORE IMPLEMENTING**: Read `CLAUDE.md` (constitution), `docs/adr/0004-catalog-module.md` (Catalog ADR),
> and `docs/adr/ADR-001-geography-module.md`. Pay special attention to:
> - No `service_search_index` MySQL table — Meilisearch via Scout only (locked decision)
> - `search_logs` is append-only: `created_at` only, no `updated_at`, no soft-delete
> - `Searchable` trait goes on `Service` model in the **Catalog** module, not Discovery
> - Cross-module access via events/contracts only — Discovery must NOT import Catalog models directly
> - `SCOUT_QUEUE=true` always — async indexing, never blocking HTTP responses

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story ([US1]–[US4])
- Exact file paths included in every task

---

## Phase 1: Setup (Module Scaffold)

**Purpose**: Create the Discovery module skeleton so all subsequent tasks have a home.

- [x] T001 Create Discovery module directory structure: `app/Modules/Discovery/{Domain,Application,Infrastructure,Http,Filament,Routes,Database,Resources,Providers}` per module layout in CLAUDE.md
- [x] T002 Register `DiscoveryServiceProvider` in `bootstrap/providers.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migrations, Scout configuration, indexed model, module models, and service provider. MUST be complete before any user story.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T003 Create migration `app/Modules/Discovery/Database/Migrations/2026_04_30_000001_create_wishlists_table.php` — fields: `id`, `public_id` CHAR(26) UNIQUE, `user_id` FK→users restrictOnDelete, `name` VARCHAR(120) default 'Default', timestamps; charset utf8mb4
- [x] T004 [P] Create migration `app/Modules/Discovery/Database/Migrations/2026_04_30_000002_create_wishlist_items_table.php` — fields: `id`, `wishlist_id` FK→wishlists cascadeOnDelete, `service_id` FK→services restrictOnDelete, `created_at` TIMESTAMP only (no updated_at), UNIQUE(`wishlist_id`, `service_id`); NO public_id (identified by composite key)
- [x] T005 [P] Create migration `app/Modules/Discovery/Database/Migrations/2026_04_30_000003_create_saved_searches_table.php` — fields: `id`, `user_id` FK→users cascadeOnDelete, `label` VARCHAR(120), `filters` JSON, timestamps; NO public_id (no external API surface in Phase 3.0)
- [x] T006 [P] Create migration `app/Modules/Discovery/Database/Migrations/2026_04_30_000004_create_search_logs_table.php` — append-only: `id`, `user_id` BIGINT NULL FK→users setNull, `query` VARCHAR(255), `locale` ENUM('en','ar'), `filters` JSON, `results_count` UNSIGNED INT, `clicked_service_id` BIGINT NULL FK→services setNull, `created_at` TIMESTAMP useCurrent() ONLY — no `updated_at`, no soft-deletes, no public_id
- [x] T007 Add Scout env vars to `.env.example`: `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST=http://localhost:7700`, `MEILISEARCH_KEY=masterKey`, `SCOUT_QUEUE=true`; update `config/scout.php` to read `MEILISEARCH_HOST` and `MEILISEARCH_KEY`
- [x] T008 Add `Laravel\Scout\Searchable` trait to `app/Modules/Catalog/Domain/Models/Service.php`; implement `shouldBeSearchable(): bool` (returns `$this->status->value === 'published'`); implement `toSearchableArray(): array` per research.md R1 — eager-load `occasions`, `vendorProfile`, `rentalDetail`, `saleDetail`, `digitalDetail`; flatten JSON name/description to `name_en`, `name_ar`, `short_description_en`, `short_description_ar`
- [x] T009 [P] Create `app/Modules/Discovery/Domain/Models/Wishlist.php` — `belongsTo(User)`, `hasMany(WishlistItem)`, `$fillable`, `$casts`; uses `HasPublicId` trait
- [x] T010 [P] Create `app/Modules/Discovery/Domain/Models/WishlistItem.php` — `belongsTo(Wishlist)`, `belongsTo(Service)` (read-only reference to Catalog); `CREATED_AT` only (set `const UPDATED_AT = null`); `$fillable`
- [x] T011 [P] Create `app/Modules/Discovery/Domain/Models/SavedSearch.php` — `belongsTo(User)`, `$casts = ['filters' => 'array']`, `$fillable`; Phase 3.0 schema-only (no API endpoints)
- [x] T012 [P] Create `app/Modules/Discovery/Domain/Models/SearchLog.php` — append-only model; `const UPDATED_AT = null`; `$fillable`; no factory (logs are not test-seeded directly)
- [x] T013 Create `app/Modules/Discovery/Application/Listeners/ServiceIndexListener.php` — handles `Catalog\ServicePublished` event (calls `$event->service->searchable()`) and `Catalog\ServiceArchived` event (calls `$event->service->unsearchable()`)
- [x] T014 Implement `app/Modules/Discovery/Providers/DiscoveryServiceProvider.php` — `loadMigrationsFrom`, `loadTranslationsFrom`, load routes from `Routes/customer.php`, register `ServiceIndexListener` for `ServicePublished` and `ServiceArchived` events; register Meilisearch index settings in `boot()` via `try/catch` (searchable, filterable, sortable attributes per data-model.md); silently skipped if Meilisearch unavailable

**Checkpoint**: Run `php artisan migrate` — four new tables created. Run `php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"` — services indexed without errors.

---

## Phase 3: User Story 1 — Bilingual Full-Text Search (Priority: P1) 🎯 MVP

**Goal**: Customer searches in Arabic or English and receives paginated, locale-aware results.

**Independent Test**: `GET /api/v1/customer/services?q=نطاطية` returns non-empty results with `meta.total`, `meta.current_page`, `meta.per_page`. Name field is in Arabic when `Accept-Language: ar`.

### Implementation

- [x] T015 [US1] Create `app/Modules/Discovery/Application/DTOs/SearchServicesDTO.php` — typed properties: `query`, `type` (nullable ProductType), `categoryId`, `occasionId`, `vendorId`, `priceMax`, `locale`, `page`, `perPage` (default 20, max 50), `sort`
- [x] T016 [P] [US1] Create `app/Modules/Discovery/Domain/Events/ServiceSearchPerformed.php` — payload: `query`, `locale`, `filtersApplied` array, `resultsCount`, `userId` (nullable)
- [x] T017 [P] [US1] Create `app/Modules/Discovery/Application/Listeners/LogSearchQueryListener.php` — queued listener; handles `ServiceSearchPerformed`; writes one `SearchLog` record via `SearchLog::create([...])` (append-only, no transaction needed)
- [x] T018 [US1] Create `app/Modules/Discovery/Application/Actions/SearchServicesAction.php` — accepts `SearchServicesDTO`; calls `Service::search($dto->query)->where('is_active', true)`; paginates via `->paginate($dto->perPage, 'page', $dto->page)`; fires `ServiceSearchPerformed` event via `DB::afterCommit` (or direct dispatch — no transaction here); returns `LengthAwarePaginator`
- [x] T019 [US1] Create `app/Modules/Discovery/Http/Requests/SearchServicesRequest.php` — validate: `q` optional string max:255, `type` optional in ProductType enum values, `page` integer min:1, `per_page` integer min:1 max:50, `sort` optional in `[price_asc,price_desc,rating,newest]`
- [x] T020 [P] [US1] Create `app/Modules/Discovery/Http/Resources/ServiceSearchResultResource.php` — resolves `name` and `short_description` from `name_en`/`name_ar` based on `app()->getLocale()`; returns `public_id`, `name`, `short_description`, `product_type`, `base_price_minor`, `base_price_currency`, `rating_avg`, `vendor`, `thumbnail_url`, `is_wishlisted` (false for unauthenticated; resolved per user in US3)
- [x] T021 [US1] Create `app/Modules/Discovery/Http/Controllers/Customer/ServiceSearchController.php` — thin: resolve DTO from request + locale from `Accept-Language`, call `SearchServicesAction->execute($dto)`, return `ServiceSearchResultResource::collection($results)` wrapped in `ApiResponse`
- [x] T022 [US1] Create `app/Modules/Discovery/Routes/customer.php` — register `GET /api/v1/customer/services` → `ServiceSearchController`; no auth middleware (public endpoint)
- [x] T023 [P] [US1] Write unit test `tests/Unit/Modules/Discovery/SearchableArrayTest.php` — assert `toSearchableArray()` contains `name_en`, `name_ar`, `short_description_en`, `short_description_ar`, `product_type`, `is_active`, `occasion_ids` (array); assert `shouldBeSearchable()` returns `true` only for published services
- [x] T024 [P] [US1] Write feature test `tests/Feature/Modules/Discovery/SearchServicesTest.php` — cases: published service appears in results, unpublished/draft service excluded, paginated response has `meta.total`/`meta.current_page`/`meta.per_page`/`meta.last_page`, empty query returns results, whitespace-only query returns empty gracefully, `page=9999` returns empty with correct total (no error)
- [x] T025 [P] [US1] Write feature test `tests/Feature/Modules/Discovery/SearchLocaleTest.php` — cases: Arabic query `q=نطاطية` with `Accept-Language: ar` returns services with `name` in Arabic; English query with `Accept-Language: en` returns `name` in English

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Discovery/SearchServicesTest.php` passes. Curl smoke test from quickstart.md step 1 returns results.

---

## Phase 4: User Story 2 — Faceted Filtering by Product Type and Occasion (Priority: P1)

**Goal**: Customer applies `type`, `occasion`, `category`, `vendor`, `price_max` filters; results are the exact intersection of all applied filters.

**Independent Test**: `GET /api/v1/customer/services?type=rental` returns only rental-type services (zero cross-type leakage per SC-002). `GET /api/v1/customer/services?type=sale` returns only sale services. `GET /api/v1/customer/services?type=digital` returns only digital services.

### Implementation

- [x] T026 [US2] Create `app/Modules/Discovery/Domain/Contracts/SearchRepository.php` interface — `resolveOccasionId(string $code): ?int` and `resolveVendorId(string $publicId): ?int`
- [x] T027 [P] [US2] Create `app/Modules/Discovery/Infrastructure/Repositories/EloquentSearchRepository.php` — implements `SearchRepository`; `resolveOccasionId` does `Occasion::where('code', $code)->value('id')`; `resolveVendorId` does `VendorProfile::where('public_id', $publicId)->value('id')`; bind in `DiscoveryServiceProvider::register()`
- [x] T028 [US2] Extend `SearchServicesAction` to apply filters: inject `SearchRepository`; chain `.where('product_type', $dto->type->value)` when type present; resolve occasion slug→id and chain `.where('occasion_ids', $id)` when occasion present; chain `.where('category_id', $id)` when category present; chain `.where('vendor_id', $id)` when vendor present; chain `.where('price_minor', '<=', $dto->priceMax)` when priceMax present; apply sort (`price_asc` → `orderBy('price_minor', 'asc')` etc.)
- [x] T029 [P] [US2] Extend `SearchServicesRequest` validation: add `category` optional string (public_id format), `occasion` optional string (code), `vendor` optional string (public_id format), `price_max` optional integer min:0
- [x] T030 [P] [US2] Extend `tests/Feature/Modules/Discovery/SearchServicesTest.php` — add filter cases: `type=rental` returns only rental, `type=sale` returns only sale, `type=digital` returns only digital, `occasion=birthday` returns only birthday-tagged services, `type=rental&occasion=birthday` is intersection, `price_max=50000` excludes services above price, invalid `type` enum returns 422

**Checkpoint**: All three product-type filter tests pass. SC-002 satisfied (zero cross-type leakage verifiable via test).

---

## Phase 5: User Story 3 — Wishlist Management (Priority: P2)

**Goal**: Authenticated customer can add, remove, and list wishlist items. Unauthenticated requests return 401.

**Independent Test**: Authenticated customer POSTs `service_id` → 201; GETs wishlist → service appears with `is_available=true`; DELETEs item → 204; GET wishlist → service gone. Second POST of same item → 200 (idempotent, one entry only).

### Implementation

- [x] T031 [US3] Create `app/Modules/Discovery/Application/Actions/AddToWishlistAction.php` — inject `WishlistRepository` or use Eloquent directly; DB::transaction: `Wishlist::firstOrCreate(['user_id' => $dto->userId], ['public_id' => Str::ulid(), 'name' => 'Default'])`; verify service exists and is published (404 if not); `WishlistItem::firstOrCreate(['wishlist_id' => ..., 'service_id' => ...])` (idempotent via UNIQUE constraint); return `Wishlist`
- [x] T032 [P] [US3] Create `app/Modules/Discovery/Application/Actions/RemoveFromWishlistAction.php` — find `Wishlist::where('user_id', $userId)->firstOrFail()`; `WishlistItem::where([...])->delete()`; 404 if item not in wishlist; void return
- [x] T033 [P] [US3] Create `app/Modules/Discovery/Http/Requests/AddToWishlistRequest.php` — validate: `service_id` required, exists in `services.public_id` where `status=published`
- [x] T034 [P] [US3] Create `app/Modules/Discovery/Http/Resources/WishlistItemResource.php` — returns `service_id` (public_id), `name` (locale-resolved), `product_type`, `base_price_minor`, `base_price_currency`, `thumbnail_url`, `is_available` (reflects `service.status === published`), `added_at` (formatted UTC+3)
- [x] T035 [US3] Create `app/Modules/Discovery/Http/Controllers/Customer/WishlistController.php` — `add()` (POST), `remove()` (DELETE `{servicePublicId}`), `index()` (GET); each ≤3 lines delegating to Actions; wrap in `ApiResponse`
- [x] T036 [US3] Add wishlist routes to `app/Modules/Discovery/Routes/customer.php` — all under `auth:sanctum` + `role:customer` middleware: `POST /wishlist/items`, `DELETE /wishlist/items/{servicePublicId}`, `GET /wishlist`
- [x] T037 [US3] Update `ServiceSearchResultResource` to resolve `is_wishlisted` for authenticated users: if `auth()->check()`, query `WishlistItem` for the current user and service; false for unauthenticated
- [x] T038 [P] [US3] Write feature test `tests/Feature/Modules/Discovery/WishlistTest.php` — cases: add (201), idempotent add (200, one entry), remove (204), list (200, correct item), unauthenticated add → 401, unauthenticated remove → 401, unauthenticated list → 401, add non-published service → 404, remove service not in wishlist → 404

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Discovery/WishlistTest.php` passes. Curl smoke test from quickstart.md steps 3–4 works end-to-end.

---

## Phase 6: User Story 4 — Admin Re-index on Demand (Priority: P3)

**Goal**: Admin triggers full re-index from Filament panel without search downtime.

**Independent Test**: After manually removing a service from the index, admin triggers re-index; service reappears in search results.

### Implementation

- [x] T039 [US4] Create `app/Modules/Discovery/Filament/Actions/ReindexServicesAction.php` — Filament `Action` class; `execute()` calls `Service::published()->searchable()` (queues Scout import for all published services); wraps in try/catch; sends Filament `Notification::make()->success()` on completion; uses `manage_search_index` permission gate
- [x] T040 [P] [US4] Register `ReindexServicesAction` as header action on `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php` — `->headerActions([Action::make('reindex')...->visible(fn () => auth()->user()->can('manage_search_index'))])` per research.md R9
- [x] T041 [P] [US4] Register `ReindexServicesAction` as header action on `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`
- [x] T042 [P] [US4] Register `ReindexServicesAction` as header action on `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`
- [x] T043 [US4] Run `php artisan shield:generate --all` to create `manage_search_index` permission; assign to Super Admin role in seeder

**Checkpoint**: Admin navigates to `/admin` → Services (Rental) → clicks "Re-index Services" → confirmation modal appears → triggers without error.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T044 [P] Create `app/Modules/Discovery/Resources/lang/en/discovery.php` — keys: `reindex_services`, `reindex_success`, `reindex_confirm`, `wishlist_added`, `wishlist_removed`, `search_placeholder`
- [x] T045 [P] Create `app/Modules/Discovery/Resources/lang/ar/discovery.php` — Arabic translations for same keys
- [x] T046 [P] Write architecture test `tests/Unit/Modules/Discovery/ArchTest.php` — assert Discovery does not import Booking, Payments, or Settlement models; assert `SearchLog` has no `updated_at`
- [x] T047 Run `php artisan migrate` to confirm all 4 Discovery migrations apply cleanly
- [x] T048 Run `php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"` to populate initial index; verify with curl smoke test from `specs/004-discovery-meilisearch/quickstart.md`
- [x] T049 Run `./vendor/bin/pest --group=discovery` — all tests green

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — **BLOCKS all user stories**
- **Phase 3 (US1)**: Depends on Phase 2 completion
- **Phase 4 (US2)**: Depends on Phase 3 (extends same action/endpoint)
- **Phase 5 (US3)**: Depends on Phase 2 (uses Wishlist/WishlistItem models); can start after Phase 2 in parallel with Phase 3
- **Phase 6 (US4)**: Depends on Phase 2 (needs Service Scout integration); can start after Phase 2
- **Phase 7 (Polish)**: Depends on all user story phases

### User Story Dependencies

- **US1 (Search)**: Phase 2 complete → can start
- **US2 (Filters)**: US1 complete (extends same action) → can start
- **US3 (Wishlist)**: Phase 2 complete → can start independently of US1/US2
- **US4 (Re-index)**: Phase 2 complete (Scout on Service model required) → can start

### Within Each Phase

- Tasks marked [P] can run in parallel (different files, no incomplete dependencies)
- Models before actions; actions before controllers; controllers before routes
- Foundational phase (T003–T014) must be fully complete before any story work

### Parallel Opportunities

```
Phase 2 parallel batch 1 (all independent files):
  T003, T004, T005, T006  — four migrations
  T009, T010, T011, T012  — four models

Phase 2 serial:
  T007 (Scout config) → T008 (Searchable on Service model) → T013 (listener) → T014 (ServiceProvider)

Phase 3 parallel batch (once T018 is done):
  T020 (ServiceSearchResultResource)
  T023 (unit test: SearchableArrayTest)
  T024 (feature test: SearchServicesTest)
  T025 (feature test: SearchLocaleTest)

Phase 5 parallel batch (once T031, T032 are done):
  T033, T034 — request + resource
  T038 — WishlistTest

Phase 6 parallel batch (once T039 is done):
  T040, T041, T042 — register on all 3 resources
```

---

## Implementation Strategy

### MVP First (US1 only — get search working)

1. Complete Phase 1: Setup (T001–T002)
2. Complete Phase 2: Foundational (T003–T014) — **critical path**
3. Complete Phase 3: US1 Bilingual Search (T015–T025)
4. **STOP and VALIDATE**: Run `./vendor/bin/pest tests/Feature/Modules/Discovery/` + curl smoke tests
5. Search is live — customers can find services

### Incremental Delivery

1. Setup + Foundational → Module skeleton + Scout integration
2. US1 → Bilingual search live (MVP)
3. US2 → Filtering live (search is now fully useful)
4. US3 → Wishlists live (conversion feature)
5. US4 → Admin re-index live (ops feature)

---

## Summary

| Metric | Count |
|---|---|
| Total tasks | 49 |
| Phase 1 (Setup) | 2 |
| Phase 2 (Foundational) | 12 |
| Phase 3 (US1 — Bilingual Search) | 11 |
| Phase 4 (US2 — Faceted Filtering) | 5 |
| Phase 5 (US3 — Wishlist) | 8 |
| Phase 6 (US4 — Admin Re-index) | 5 |
| Phase 7 (Polish) | 6 |
| Parallel opportunities | 22 tasks marked [P] |

**Suggested MVP scope**: Phase 1 + Phase 2 + Phase 3 (US1) — bilingual search working end-to-end.
