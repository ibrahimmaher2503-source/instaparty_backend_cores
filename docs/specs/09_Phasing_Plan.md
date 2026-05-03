# InstaParty — Phasing Plan v2 (Phase 1, 16 micro-phases)

> **Why v2 exists:** Phase 2 (7 days) and Phases 3/4 (5 days each) في الخطة الأصلية كبار جداً للـ AI. Claude Code بيفقد context في النص. الحل: كل phase = ≤3 أيام = chunk قابل للـ scope-audit + ADR + tests + commit.
>
> **Owner:** Ibrahim (solo full-stack)
> **Scope:** Backend (Laravel API) + Admin (Filament v3) only
> **Total:** 8 weeks (40 working days) split into 16 micro-phases
> **Hours:** ~6 productive hours/day, no major distractions

---

## What Changed vs v1

| v1 (old) | v2 (new) |
|---|---|
| 8 phases, 5-7 days each | 16 phases, ≤3 days each |
| Mixed concerns per phase | One concern per phase |
| Phase = "Catalog" (everything) | Phases = "Catalog: Foundation" + "Catalog: Rental Type" + "Catalog: Sale Type" + "Catalog: Digital Type" + "Catalog: Excel" |
| Cut-list general | Cut-list per micro-phase |
| One ADR per phase (sometimes none) | One ADR per phase (mandatory) |
| One demo per week | One demo per phase (3 days) |

---

## Source-of-Truth Rule (unchanged from v1)

Every phase must respect: `01_PRD.md`, `CLAUDE.md`, `03_Three_Product_Types.md`, `02_Tech_Decisions.md`, `10_Package_List.md`, `11_DB_Schema.md`, `.claude/rules/*.md`.

Phase 2 features (subscription tiers, platform packages, dispute engine, card templates, page slider, QR catalog, advanced tax) are **forbidden** in Phase 1.

---

## Phase Structure (every phase follows this template)

Each phase has:

```
PHASE X.Y — {Name} ({N} days)

GOAL: One sentence. What's the demo at the end?

PRD COVERAGE: FR-XX, FR-YY (cite specific FRs)

ADR REQUIRED: ADR-NNNN-{slug}.md (created on Day 1)

TABLES TOUCHED: list (only the ones THIS phase creates/modifies)

DELIVERABLE: One concrete thing demonstrable in /admin or via API

DAYS:
  Day 1: {what}
  Day 2: {what}
  Day 3: {what} (if applicable)

CUT-LIST: What defers to Phase 1.5 if this micro-phase slips

EXIT CRITERIA: 3-5 checkboxes that prove "done"

BLOCKS: Which next phases depend on this one
```

---

## Risk Register (read once)

Same risks as v1, but now mitigated by smaller scope per phase:

| Risk | Mitigation in v2 |
|---|---|
| Three product types triple effort | Each type gets its own phase (2.2, 2.3, 2.4). Pattern locked in 2.2, replicated in 2.3, 2.4. |
| Paymob webhook signing | Phase 4.1 is JUST the gateway adapter. Phase 4.2 is JUST refunds. |
| Bilingual Filament forms slower | Geography (1.0) sets the EN/AR pattern. Every later Filament resource follows same pattern. |
| Meilisearch tokenizer | Phase 3.1 (Discovery) is isolated. If it breaks, it doesn't block Booking. |
| Excel imports complexity | Phase 2.5 (rental Excel only). Phase 6.1 (sale + digital Excel) — separate. |
| Test coverage falling behind | Each phase has Pest tests as Day-3 task — non-negotiable. |

---

## Phase Index (16 micro-phases)

| Phase | Name | Days | Week | Module |
|---|---|---|---|---|
| **0.0** | Foundation Setup | 2 | W1 | — |
| **0.1** | Geography Module | 2 | W1 | Geography |
| **0.2** | Identity Migrations + MoneyCast | 1 | W1 | Identity (DB only) |
| **1.0** | Identity Core (Models + Auth) | 3 | W2 | Identity |
| **1.1** | Vendor Onboarding + Approval | 2 | W2 | Identity |
| **2.0** | Catalog Foundation (base + occasions + categories) | 2 | W2-3 | Catalog |
| **2.1** | Catalog: Rental Type | 2 | W3 | Catalog |
| **2.2** | Catalog: Sale Type | 2 | W3 | Catalog |
| **2.3** | Catalog: Digital Type + Inventory | 2 | W3 | Catalog |
| **2.4** | Catalog: Rental Excel Import | 1 | W3 | Catalog |
| **3.0** | Discovery (Meilisearch) | 2 | W4 | Discovery |
| **3.1** | Booking: Draft + Items | 2 | W4 | Booking |
| **3.2** | Booking: Negotiation Loop | 2 | W4-5 | Booking |
| **4.0** | Payments: Paymob Gateway | 2 | W5 | Payments |
| **4.1** | Payments: Refunds (per-type) | 1 | W5 | Payments |
| **4.2** | Settlement: Wallets + Commissions + Withdrawals | 3 | W5-6 | Settlement |
| **5.0** | Communication: Notifications | 2 | W6 | Communication |
| **5.1** | Reviews | 1 | W6 | Reviews |
| **5.2** | Loyalty (per-vendor) | 2 | W6 | Loyalty |
| **5.3** | Marketing Campaigns | 1 | W7 | Communication |
| **6.0** | Reports + Audit Log | 2 | W7 | Reporting |
| **6.1** | Sale + Digital Excel Imports | 1 | W7 | Catalog |
| **6.2** | CMS Pages + Settings | 1 | W7 | Cross-cutting |
| **7.0** | Hardening: Performance + Security | 2 | W8 | All |
| **7.1** | Staging Deploy + Smoke Tests | 2 | W8 | DevOps |
| **7.2** | Documentation + Retrospective | 1 | W8 | All |

**Total: 26 phases × ~1.5 days avg = 40 days = 8 weeks.** Some phases are 1 day, some are 3 — average works out.


---

## PHASE 0.0 — Foundation Setup (2 days, Week 1)

**GOAL:** Bootable Laravel 12 + Filament v3 app with Docker Compose stack running locally.

**PRD COVERAGE:** §3 (Technology Baseline), NFR (architecture)

**ADR REQUIRED:** None (using locked Tech Decisions §1)

**TABLES TOUCHED:** Framework tables only (users, sessions, jobs, cache, personal_access_tokens, media via Spatie)

**DELIVERABLE:** `docker compose up` brings the stack online; `php artisan migrate` works; `/admin` returns 200.

**DAYS:**

### Day 1 — Project + Packages
- [ ] `composer create-project laravel/laravel instaparty`
- [ ] Drop in setup bundle (CLAUDE.md, docs/specs/, .claude/)
- [ ] `chmod +x .claude/hooks/*.sh`
- [ ] `composer require` Day-1 packages from `10_Package_List.md` §1
- [ ] `git init` + first commit + push to GitHub
- [ ] `.env.example` with all keys documented

### Day 2 — Docker + Auth Scaffold + Filament
- [ ] Docker Compose: app, mysql, redis, meilisearch, mailpit, minio
- [ ] Laravel Sanctum scaffold (SPA mode)
- [ ] Spatie Permission install + roles seeder
- [ ] Filament v3 install at `/admin` with custom resource discovery for `app/Modules/*/Filament/Resources/`

**CUT-LIST:** Defer Caddy/staging (move to Phase 7.1)

**EXIT CRITERIA:**
- ✅ `docker compose up` works
- ✅ `php artisan migrate` runs without error
- ✅ `/admin` shows Filament login (even with no admin user yet)
- ✅ `git status` clean after commit

**BLOCKS:** Phase 0.1, all other phases

---

## PHASE 0.1 — Geography Module (2 days, Week 1)

**GOAL:** Egypt geography seeded and browsable in Filament with EN+AR.

**PRD COVERAGE:** Cross-cutting infrastructure (no specific FR — used by all later modules)

**ADR REQUIRED:** `ADR-0004-geography-module.md`

**TABLES TOUCHED:** governorates, regions, cities

**DELIVERABLE:** Admin can browse Egypt's governorates/regions/cities in `/admin` with locale switcher working.

**DAYS:**

### Day 1 — Migrations + Models + Factories
- [ ] Write ADR-0004 first (use `/new-module-adr Geography`)
- [ ] Migrations for governorates, regions, cities (with denormalized `governorate_id` on cities)
- [ ] Models: Governorate, Region, City — translatable `name` JSON column, public_id ULID
- [ ] Factories for all three
- [ ] EgyptGeographySeeder (all 27 governorates + major cities)

### Day 2 — Filament Resources + Tests
- [ ] GovernorateResource, RegionResource, CityResource with translatable EN/AR tabs
- [ ] Filter by parent (cities by region, regions by governorate)
- [ ] `php artisan shield:generate --all`
- [ ] Pest tests: factory smoke, translatable read/write, FK protection on delete

**CUT-LIST:** Defer non-Egypt seeds (KSA, UAE) to Phase 2 multi-country

**EXIT CRITERIA:**
- ✅ ADR-0004 accepted
- ✅ All Egypt geography seeded
- ✅ Filament browse works in EN AND AR
- ✅ All Pest tests pass
- ✅ Architecture test: Geography doesn't import other module models

**BLOCKS:** Phase 0.2 (Identity FKs depend on cities), Phase 2.x (vendor coverage areas)

---

## PHASE 0.2 — Identity Migrations + MoneyCast (1 day, Week 1)

**GOAL:** All Identity tables exist (no models yet) + `MoneyCast` ready for use.

**PRD COVERAGE:** Foundation for FR-21, FR-28

**ADR REQUIRED:** `ADR-0003-identity-module.md` (already exists in template — finalize Phase 0 sections)

**TABLES TOUCHED:** users (extends), vendor_profiles, vendor_documents, vendor_approved_product_types, vendor_business_hours, vendor_coverage_areas, customer_profiles, user_devices, two_factor_secrets, customer_addresses

**DELIVERABLE:** All Identity migrations applied. `MoneyCast` working with one demo column.

**DAYS:**

### Day 1 — Migrations + MoneyCast
- [ ] Finalize ADR-0003 (Phase 0 portion)
- [ ] All 10 Identity migrations in dependency order (users → vendor_profiles → ... → customer_addresses)
- [ ] `MoneyCast` in `app/Modules/Shared/Domain/Casts/`
- [ ] Pest test: migrations apply cleanly, FK to cities works (restrictOnDelete)
- [ ] Pest test: MoneyCast converts `_minor` + `_currency` ↔ `Brick\Money\Money`

**CUT-LIST:** None — these migrations are required for everything

**EXIT CRITERIA:**
- ✅ `php artisan migrate:fresh` succeeds
- ✅ FK constraints enforced (try delete city with vendor coverage → fails)
- ✅ MoneyCast unit test passes

**BLOCKS:** Phase 1.0, all phases involving money

---

## PHASE 1.0 — Identity Core (Models + Auth) (3 days, Week 2)

**GOAL:** Customers and vendors can register and log in via API.

**PRD COVERAGE:** FR-19 (auth foundation), partial FR-1 (sign-up flow)

**ADR REQUIRED:** ADR-0003 (Identity) — already started

**TABLES TOUCHED:** All Identity tables (data layer only — schema already done)

**DELIVERABLE:** `POST /api/v1/customer/register` and `POST /api/v1/vendor/register` work end-to-end. Login returns Sanctum token.

**DAYS:**

### Day 1 — Models + Sanctum + Roles
- [ ] All Identity Models with relationships, casts, scopes (NO business logic)
- [ ] Sanctum SPA + token guards configured
- [ ] Spatie roles seeder: customer, vendor, admin
- [ ] Per-product-type vendor permissions: `service.{action}.{type}.own` × 4 actions × 3 types = 12 permissions
- [ ] First admin user seeder

### Day 2 — Auth Actions + API
- [ ] `RegisterCustomerAction`, `RegisterVendorAction`, `LoginAction`, `LogoutAction`
- [ ] Form Requests with bilingual validation messages
- [ ] API Resources: `UserResource`, `VendorProfileResource`, `CustomerProfileResource`
- [ ] Routes in `app/Modules/Identity/Routes/{customer,vendor,admin}.php`
- [ ] Phone verification stub (real OTP in Phase 5.0)

### Day 3 — Tests
- [ ] Pest: register customer happy path
- [ ] Pest: register vendor happy path (creates vendor_profile)
- [ ] Pest: login returns valid Sanctum token
- [ ] Pest: validation rules (duplicate phone → 422, invalid email → 422)
- [ ] Pest: locale (EN response, AR response)
- [ ] Pest: rate limiting on register (max 5/min)

**CUT-LIST:**
- Defer 2FA setup → Phase 7.0 (Hardening)
- Defer phone OTP delivery → Phase 5.0 (stub responses for now)
- Defer customer address book API → Phase 1.1 if tight

**EXIT CRITERIA:**
- ✅ Customer can register, log in, log out via API
- ✅ Vendor can register (gets `vendor_profile` with `approval_status=pending`)
- ✅ Tokens work for authenticated routes
- ✅ All Pest tests pass

**BLOCKS:** Phase 1.1, Phase 2.x (vendor must exist to create services)

---

## PHASE 1.1 — Vendor Onboarding + Approval (2 days, Week 2)

**GOAL:** Admin approves vendors per product type via Filament. Vendor sees approval status.

**PRD COVERAGE:** FR-29 (admin approves vendors), Tech Decisions §2.4 (per-type approval)

**ADR REQUIRED:** Already covered in ADR-0003 §6.2

**TABLES TOUCHED:** vendor_profiles (status updates), vendor_approved_product_types, vendor_documents

**DELIVERABLE:** Admin uses Filament "Vendor Approval Queue" to approve a vendor for `rental` only. Vendor's API shows `approval_status=approved` for rental, no rights for sale/digital.

**DAYS:**

### Day 1 — Approval Actions + Filament Queue
- [ ] Actions: `ApproveVendorAction`, `ApproveVendorForTypeAction`, `RejectVendorAction`, `RevokeVendorTypeAction`, `SuspendVendorAction`
- [ ] Filament: `VendorProfileResource`, `VendorApprovalQueueResource` with per-type approve/revoke buttons
- [ ] Document upload (Spatie Media Library) for CR, tax card, IBAN proof
- [ ] Domain events: `VendorApprovedForType`, `VendorTypeRevoked`

### Day 2 — Tests + Notification Stubs
- [ ] Pest: approve vendor for rental → can create rental services (Phase 2.x), can't create sale
- [ ] Pest: revoke type → service creation gated
- [ ] Pest: full audit trail in `vendor_approved_product_types`
- [ ] Notification stubs (real dispatch in Phase 5.0): "vendor approved", "vendor rejected"

**CUT-LIST:**
- Defer document review workflow (admin marks each doc approved/rejected) — Phase 7.0
- Defer vendor business hours UI — Phase 2.0

**EXIT CRITERIA:**
- ✅ Admin Filament queue shows pending vendors
- ✅ Per-type approval buttons work
- ✅ Authorization gates fire on `service.create.{type}.own` per type
- ✅ Audit log captures every approval/revocation

**BLOCKS:** Phase 2.x (vendor must be approved per type to create services)

---

## PHASE 2.0 — Catalog Foundation (2 days, Week 2-3)

**GOAL:** Occasions, categories (hierarchical), and field schemas in place. NO services yet.

**PRD COVERAGE:** FR-1, FR-19, FR-20, BR-5

**ADR REQUIRED:** `ADR-0005-catalog-module.md`

**TABLES TOUCHED:** occasions, categories, occasion_category, category_field_schemas, service_themes

**DELIVERABLE:** Admin sets up "Birthday → Inflatables (rental category)" with custom field schema. Vendor's API can list occasions and categories (filtered by allowed product types).

**DAYS:**

### Day 1 — Migrations + Models
- [ ] Write ADR-0005 first
- [ ] Migrations: occasions, categories (with parent_id self-ref + `allowed_product_types` JSON), occasion_category pivot, category_field_schemas, service_themes
- [ ] Models with translatable JSON columns
- [ ] Factories + seeders (Birthday, Wedding, Engagement + sample categories)

### Day 2 — Filament + API + Tests
- [ ] Filament: OccasionResource, CategoryResource (tree view), ServiceThemeResource, CategoryFieldSchemaResource
- [ ] API: `GET /api/v1/customer/occasions`, `GET /api/v1/customer/categories?occasion_id=X&product_type=Y`
- [ ] Pest: tree relationships, filterable by allowed_product_types

**CUT-LIST:**
- Defer service themes pivot/UI — use simple text tag (Phase 6.x)
- Defer dynamic field schema rendering on vendor side — hardcoded fields per type for now

**EXIT CRITERIA:**
- ✅ Admin builds occasion → categories tree
- ✅ Customer API lists categories filtered by product type
- ✅ ADR-0005 accepted

**BLOCKS:** Phase 2.1, 2.2, 2.3

---

## PHASE 2.1 — Catalog: Rental Type (2 days, Week 3)

**GOAL:** Vendors create rental services via API. Admin sees them in `RentalServiceResource`.

**PRD COVERAGE:** FR-19, FR-20, FR-21

**ADR REQUIRED:** Already in ADR-0005

**TABLES TOUCHED:** services (base), service_rental_details

**DELIVERABLE:** `POST /api/v1/vendor/services/rental` creates an inflatable with `requires_electricity`, `setup_time_minutes`, `security_deposit_minor`. Filament shows it.

**DAYS:**

### Day 1 — Migrations + Action + API
- [ ] Migration: services (base, polymorphic, `product_type` discriminator) + service_rental_details
- [ ] `ProductType` enum in `app/Modules/Catalog/Domain/Enums/`
- [ ] `CreateRentalServiceRequest`, `UpdateRentalServiceRequest`
- [ ] `CreateRentalServiceAction`, `UpdateRentalServiceAction`
- [ ] API: `POST/PATCH /api/v1/vendor/services/rental`
- [ ] `RentalServiceResource` (API Resource extending base)

### Day 2 — Filament + Tests
- [ ] Filament `RentalServiceResource` (under "Services" navigation group)
- [ ] EN/AR tabs for translatable fields
- [ ] Spatie Media Library for images (max 11)
- [ ] Pest: rental creation (happy + auth + authz + validation + locale)

**CUT-LIST:**
- Defer pricing tiers UI — single base_price for now (Phase 6.x)
- Defer availability_blocks — assume "always available" for now

**EXIT CRITERIA:**
- ✅ Vendor creates rental service via API
- ✅ Admin sees it in Filament RentalServiceResource
- ✅ Form validates `requires_electricity`, `default_rental_duration_hours`
- ✅ Pest tests pass

**BLOCKS:** Phase 2.2 (uses same pattern), Phase 3.1 (booking needs services)

---

## PHASE 2.2 — Catalog: Sale Type (2 days, Week 3)

**GOAL:** Vendors create sale services. Pattern from 2.1 replicated for sale.

**PRD COVERAGE:** Same as 2.1

**TABLES TOUCHED:** service_sale_details (services already exists)

**DELIVERABLE:** `POST /api/v1/vendor/services/sale` creates a cake with `is_perishable`, `is_made_to_order`, `lead_time_hours`, `customization_fields`.

**DAYS:**

### Day 1 — Migration + Action + API
- [ ] Migration: service_sale_details
- [ ] `CreateSaleServiceRequest`, `CreateSaleServiceAction`
- [ ] Conditional validation: `lead_time_hours` required when `is_made_to_order=true`
- [ ] API: `POST/PATCH /api/v1/vendor/services/sale`
- [ ] `SaleServiceResource` (API)

### Day 2 — Filament + Tests
- [ ] Filament `SaleServiceResource`
- [ ] Pest: all standard tests + `is_made_to_order` conditional validation
- [ ] Pest: `customization_fields` JSON validation

**CUT-LIST:** None (sale type is core)

**EXIT CRITERIA:**
- ✅ Vendor creates cake with customization fields
- ✅ `lead_time_hours` enforced when made-to-order
- ✅ Pest tests pass

**BLOCKS:** Phase 2.3

---

## PHASE 2.3 — Catalog: Digital Type + Inventory Reservations (2 days, Week 3)

**GOAL:** Digital services + inventory reservation system (cart hold + payment hold).

**PRD COVERAGE:** Same as 2.1 + Tech Decisions inventory locked decision

**TABLES TOUCHED:** service_digital_details, service_inventory_reservations

**DELIVERABLE:** Vendor creates an e-invitation digital service. `HoldServiceInventoryAction` reserves with 15-min TTL (cart) or 24h TTL (after submit). Cleanup job releases expired holds.

**DAYS:**

### Day 1 — Digital + Inventory Schema
- [ ] Migration: service_digital_details
- [ ] Migration: service_inventory_reservations
- [ ] `CreateDigitalServiceRequest`, `CreateDigitalServiceAction`
- [ ] API: `POST/PATCH /api/v1/vendor/services/digital`
- [ ] `DigitalServiceResource` (API + Filament)

### Day 2 — Inventory + Cleanup
- [ ] `HoldServiceInventoryAction` with `match($enum)` per-type logic
- [ ] `ReleaseExpiredReservations` artisan command + Schedule (every minute)
- [ ] Pest: cart hold expires after 15 min
- [ ] Pest: payment hold expires after 24h
- [ ] Pest: rental overlap detection
- [ ] Pest: digital "always available" (no overlap)

**CUT-LIST:**
- Defer code_pool_id (digital code pools) — Phase 1.5

**EXIT CRITERIA:**
- ✅ All 3 product types creatable
- ✅ Inventory reservation works for rental (overlap check) + sale (stock decrement) + digital (no constraint)
- ✅ Cleanup job releases expired holds in test
- ✅ Pest covers all 3 types

**BLOCKS:** Phase 3.1 (booking creates reservations)

---

## PHASE 2.4 — Catalog: Rental Excel Import (1 day, Week 3)

**GOAL:** Vendor uploads Excel of rental services. No partial commits.

**PRD COVERAGE:** FR-22

**TABLES TOUCHED:** excel_imports, excel_import_errors

**DELIVERABLE:** Vendor uploads `rentals.xlsx` via Filament. 10 rows valid → all created. 10 rows with 1 invalid → none created, error report shown per row in vendor's locale.

**DAYS:**

### Day 1 — Importer + UI + Tests
- [ ] `RentalServicesImport` (Maatwebsite Excel)
- [ ] `ImportRentalServicesFromExcelAction` (transactional, no partial commits)
- [ ] Bilingual columns: `name_en`, `name_ar`, `short_description_en`, `short_description_ar`, etc.
- [ ] Filament page: bulk-upload form with progress + per-row errors
- [ ] Pest: 100% valid → all imported
- [ ] Pest: 1 invalid → 0 imported, errors logged

**CUT-LIST:**
- Defer image folder upload (just filename references for now) — Phase 6.x

**EXIT CRITERIA:**
- ✅ Excel template downloadable
- ✅ Valid rows → bulk create works
- ✅ Invalid rows → nothing imported, errors visible

**BLOCKS:** Phase 6.1 (sale + digital Excel use same pattern)

---

## PHASE 3.0 — Discovery (Meilisearch) (2 days, Week 4)

**GOAL:** Customer searches services with `product_type` facet, EN/AR tokenizers.

**PRD COVERAGE:** FR-3, FR-4

**ADR REQUIRED:** `ADR-0006-discovery-module.md`

**TABLES TOUCHED:** wishlists, wishlist_items, saved_searches, search_logs

**DELIVERABLE:** `GET /api/v1/customer/services?occasion=birthday&type=rental&q=نطاطية` returns hits in Arabic. Filament admin can re-index on demand.

**DAYS:**

### Day 1 — Meilisearch + Indexer
- [ ] Write ADR-0006
- [ ] Scout config + Meilisearch driver
- [ ] Service indexer with per-locale fields
- [ ] Searchable attributes: `name_en`, `name_ar`, `short_description_en`, `short_description_ar`
- [ ] Filterable: `product_type`, `category_id`, `occasion_ids`, `vendor_id`, `price_minor`
- [ ] Migration: wishlists, wishlist_items, saved_searches, search_logs

### Day 2 — API + Tests
- [ ] `SearchServicesAction` with type filter
- [ ] API: `GET /api/v1/customer/services?...`
- [ ] Wishlists API (add/remove)
- [ ] Pest: Arabic tokenization works
- [ ] Pest: facet filters apply correctly per product type
- [ ] Pest: pagination

**CUT-LIST:**
- Defer saved searches UI — Phase 1.5
- Defer search logs analytics — Phase 6.0

**EXIT CRITERIA:**
- ✅ Customer searches and gets results in their locale
- ✅ Type filter works
- ✅ Wishlist add/remove works

**BLOCKS:** Phase 3.1 (booking uses search results)

---

## PHASE 3.1 — Booking: Draft + Items (2 days, Week 4)

**GOAL:** Customer creates draft booking, adds items from multiple vendors, sees split per vendor.

**PRD COVERAGE:** FR-1 through FR-9

**ADR REQUIRED:** `ADR-0007-booking-module.md`

**TABLES TOUCHED:** bookings, booking_addresses, booking_vendors, booking_items, booking_locks, booking_snapshots, booking_customer_notes

**DELIVERABLE:** Customer creates draft → adds 2 rentals from vendor A + 1 cake from vendor B. Sees split. Reservation held 15 min.

**DAYS:**

### Day 1 — Schema + Models
- [ ] Write ADR-0007
- [ ] Migrations: bookings (3 status columns), booking_addresses (snapshot), booking_vendors, booking_items, booking_locks, booking_snapshots, booking_customer_notes
- [ ] Models with relationships
- [ ] Per-type fulfillment state machines on booking_items.item_status (3 distinct graphs via spatie/laravel-model-states)

### Day 2 — Actions + API + Tests
- [ ] `CreateBookingDraftAction`
- [ ] `AddItemToBookingAction` with `match($enum)` for per-type reservation logic
- [ ] `RemoveItemFromBookingAction`
- [ ] API endpoints
- [ ] Pest: draft creation, item add (rental + sale + digital), per-vendor split, total calculation

**CUT-LIST:**
- Defer booking_snapshots versioning — use latest snapshot only
- Defer pricing tiers calculation — base_price × quantity for now

**EXIT CRITERIA:**
- ✅ Customer adds items from 2 vendors → 2 booking_vendors rows
- ✅ Reservations held with 15-min TTL
- ✅ Total computed correctly (subtotal + delivery_fee per vendor)
- ✅ Pest covers all 3 product types

**BLOCKS:** Phase 3.2

---

## PHASE 3.2 — Booking: Negotiation Loop (2 days, Week 4-5)

**GOAL:** Vendor accepts/modifies/rejects. Customer reviews modifications. Loop until alignment.

**PRD COVERAGE:** FR-10 through FR-15, FR-16 through FR-18

**TABLES TOUCHED:** booking_modifications, booking_modification_items, booking_state_transitions

**DELIVERABLE:** Vendor modifies booking with new line item. Customer sees `diff_snapshot` highlighting changes. Customer accepts → booking confirmed.

**DAYS:**

### Day 1 — Submit + Vendor Actions
- [ ] `SubmitBookingAction` — splits per vendor, fires `BookingSubmittedToVendor` event
- [ ] `VendorAcceptBookingAction`, `VendorModifyBookingAction`, `VendorRejectBookingAction`
- [ ] `CustomerConfirmModifiedBookingAction`
- [ ] Idempotency keys for submit and confirm
- [ ] Migration: booking_modifications, booking_modification_items, booking_state_transitions

### Day 2 — Filament + Admin Intervention + Tests
- [ ] Filament: BookingsMonitor (with per-type filter), BookingItemFulfillment
- [ ] Admin intervention: monitor stalled bookings (NEVER auto-replace per FR-17)
- [ ] Pest: full negotiation loop covering all three types
- [ ] Pest: modification with diff_snapshot
- [ ] Pest: idempotency on submit (same key → same response)

**CUT-LIST:**
- Defer admin intervention workflow → Phase 7.0
- Defer modification expiration timers → Phase 5.0

**EXIT CRITERIA:**
- ✅ Customer → submit → vendor accepts/modifies/rejects → customer re-approves → confirmed
- ✅ All state transitions logged
- ✅ Loop works (multiple modifications allowed)

**BLOCKS:** Phase 4.0 (payment requires confirmed booking)

---

## PHASE 4.0 — Payments: Paymob Gateway (2 days, Week 5)

**GOAL:** Customer pays for confirmed booking. Webhook captures payment.

**PRD COVERAGE:** FR-30 (partial — payment side)

**ADR REQUIRED:** `ADR-0008-payments-module.md`

**TABLES TOUCHED:** payments, payment_attempts, idempotency_keys, gateway_webhook_logs

**DELIVERABLE:** Customer pays → Paymob redirects → webhook fires → `payments.status = captured`. Booking `payment_status = paid`.

**DAYS:**

### Day 1 — Gateway Adapter + Webhook
- [ ] Write ADR-0008
- [ ] `PaymentGateway` interface + `PaymobGateway` implementation
- [ ] Webhook endpoint `/webhooks/paymob` with HMAC signature verification
- [ ] `idempotency_keys` table + middleware
- [ ] Migration: payments, payment_attempts, gateway_webhook_logs

### Day 2 — Actions + Tests
- [ ] `InitiatePaymentAction`, `CapturePaymentAction`, `ProcessPaymobWebhookAction`
- [ ] Booking `payment_status` listener: `PaymentCaptured` → update booking
- [ ] Pest: webhook signature verification (valid + invalid)
- [ ] Pest: idempotency under concurrent requests
- [ ] Pest: full payment flow (initiate → webhook → captured)

**CUT-LIST:**
- Defer split payments — single payment per booking for now
- Defer GCC adapters (Tabby, Tamara) — Phase 2

**EXIT CRITERIA:**
- ✅ Test card succeeds in Paymob sandbox
- ✅ Webhook updates payment + booking
- ✅ Bad signature webhook rejected
- ✅ Idempotency works

**BLOCKS:** Phase 4.1, 4.2

---

## PHASE 4.1 — Payments: Refunds (per-type) (1 day, Week 5)

**GOAL:** Refund flow works with per-type policies.

**PRD COVERAGE:** Tech Decisions §11 (per-type refund policies)

**TABLES TOUCHED:** refunds

**DELIVERABLE:** Admin refunds rental booking → 24h-before-event check → refund processed via gateway.

**DAYS:**

### Day 1 — Refund Service + Actions + Tests
- [ ] Migration: refunds
- [ ] `RefundPolicyService::policyFor(ProductType)`:
  - Rental: 24h before `event_starts_at` (configurable)
  - Sale: until item enters `in_preparation` state
  - Digital: per `is_refundable_after_delivery` flag
- [ ] `InitiateRefundAction`, `ProcessRefundAction`
- [ ] Filament: refund button on Booking with per-type validation
- [ ] Pest: rental refund window enforcement (3 cases: >24h, exactly 24h, <24h)
- [ ] Pest: sale refund blocked after `in_preparation`
- [ ] Pest: digital refund per service flag

**CUT-LIST:**
- Defer partial refunds — full or none for now

**EXIT CRITERIA:**
- ✅ Each product type's refund policy enforced correctly
- ✅ Refund updates wallet ledger (negative entry)
- ✅ Pest covers all 3 types

**BLOCKS:** Phase 4.2 (settlement uses refunds)

---

## PHASE 4.2 — Settlement: Wallets + Commissions + Withdrawals (3 days, Week 5-6)

**GOAL:** Vendor sees wallet balance, requests withdrawal, admin approves with bank transfer proof.

**PRD COVERAGE:** FR-28, FR-29, FR-30

**ADR REQUIRED:** `ADR-0009-settlement-module.md`

**TABLES TOUCHED:** wallets, wallet_ledger, commissions, commission_rates, withdrawals, settlement_runs

**DELIVERABLE:** Booking captured → commission calculated (per category × type rate) → vendor wallet credited → vendor requests withdrawal → admin approves with proof upload.

**DAYS:**

### Day 1 — Schema + Commission Logic
- [ ] Write ADR-0009
- [ ] Migrations: wallets, wallet_ledger, commissions, commission_rates, withdrawals, settlement_runs
- [ ] `CalculateCommissionAction` with most-specific match: `(category × type) → (category × NULL) → (NULL × type) → (NULL × NULL)`
- [ ] Listener on `PaymentCaptured`: calculate commission, credit vendor wallet

### Day 2 — Withdrawal Flow
- [ ] `RequestWithdrawalAction`, `ApproveWithdrawalAction`, `RejectWithdrawalAction`
- [ ] Filament: WithdrawalsQueue, WalletLedgerViewer, CommissionRules
- [ ] Bank proof upload (Spatie Media Library, private bucket)

### Day 3 — Tests
- [ ] Pest: commission rate resolution (all 4 cases of specificity)
- [ ] Pest: per-type commission different rates work
- [ ] Pest: wallet ledger append-only (no updates)
- [ ] Pest: withdrawal flow (request → approve → paid)
- [ ] Pest: refund reverses wallet credit

**CUT-LIST:**
- Defer settlement_runs (manual reconciliation OK for soft launch)
- Defer auto-approval rules

**EXIT CRITERIA:**
- ✅ Commission per (category × type) working with all 4 fallback levels
- ✅ Wallet credit on payment, debit on refund
- ✅ Admin approves withdrawal with proof
- ✅ Pest covers all 3 types' commission flows

**BLOCKS:** Phase 7.1 (smoke test needs full money cycle)

---

## PHASE 5.0 — Communication: Notifications (2 days, Week 6)

**GOAL:** Notifications fire on key events via push/email/SMS/WhatsApp in user's locale.

**PRD COVERAGE:** FR-23 to FR-26

**ADR REQUIRED:** `ADR-0010-communication-module.md`

**TABLES TOUCHED:** notification_templates, notification_dispatches, notification_preferences

**DELIVERABLE:** Customer pays → push + email + SMS fire in customer's locale. Per-type events (`rental.delivery_scheduled`, etc.) work.

**DAYS:**

### Day 1 — Schema + Channels
- [ ] Write ADR-0010
- [ ] Migrations: notification_templates, notification_dispatches, notification_preferences
- [ ] `DispatchNotificationAction` selects template by `(event_key × channel × audience × locale)`
- [ ] Channel adapters: FCM (push), Mailgun/Mailchimp (email), Vonage (SMS), WhatsApp Cloud API (WA)
- [ ] Templates seeded with EN+AR for: booking.submitted, booking.modified, booking.confirmed, payment.captured

### Day 2 — Per-type Events + Filament
- [ ] Per-type events: rental.delivery_scheduled, sale.preparation_started, digital.delivered, digital.expiring_soon
- [ ] Filament: NotificationTemplates with translatable plugin
- [ ] Pest: dispatch with correct locale
- [ ] Pest: per-type event templates resolve correctly
- [ ] Pest: notification_preferences (user disabled marketing → not sent)

**CUT-LIST:**
- Defer WhatsApp templates (use stub) — Phase 1.5
- Defer scheduled/recurring notifications

**EXIT CRITERIA:**
- ✅ Booking events trigger notifications
- ✅ User locale respected
- ✅ All 4 channels can dispatch
- ✅ Pest covers per-type events

**BLOCKS:** Phase 5.1, 5.2 (use notifications)

---

## PHASE 5.1 — Reviews (1 day, Week 6)

**GOAL:** Customer rates service + vendor after booking complete. Admin moderates.

**PRD COVERAGE:** Software Description §3 (reviews at item + vendor level)

**TABLES TOUCHED:** service_reviews, vendor_reviews, review_responses, review_moderation_log

**DELIVERABLE:** Customer submits service review → admin approves → service rating updates.

**DAYS:**

### Day 1 — Reviews + Moderation
- [ ] Migrations: service_reviews, vendor_reviews, review_responses, review_moderation_log
- [ ] `SubmitReviewAction`, `RespondToReviewAction`, `ModerateReviewAction`
- [ ] Listener: on review approved → update vendor/service rating averages
- [ ] Filament: ReviewModerationPage
- [ ] Pest: only completed bookings reviewable
- [ ] Pest: one review per booking_item / booking_vendor

**CUT-LIST:**
- Defer vendor responses to reviews — Phase 6.0
- Defer review locale auto-detection — assume customer's preferred_locale

**EXIT CRITERIA:**
- ✅ Customer submits review only after booking_item completed
- ✅ Admin moderates → rating updates
- ✅ Pest tests pass

**BLOCKS:** None (independent of booking flow)

---

## PHASE 5.2 — Loyalty (per-vendor) (2 days, Week 6)

**GOAL:** Each vendor configures their own loyalty rules. Customer earns points per vendor.

**PRD COVERAGE:** Software Description (loyalty per-vendor locked)

**ADR REQUIRED:** Already in ADR-0005 (Catalog) or new mini-ADR

**TABLES TOUCHED:** loyalty_programs, loyalty_rules, loyalty_ledger, loyalty_redemptions

**DELIVERABLE:** Vendor sets up loyalty program (1 point per EGP, 100 points = 10 EGP). Customer earns on completion. Customer redeems on next booking.

**DAYS:**

### Day 1 — Schema + Earning
- [ ] Migrations: loyalty_programs, loyalty_rules, loyalty_ledger (append-only), loyalty_redemptions
- [ ] `CalculateLoyaltyPointsAction` (after booking_item completed)
- [ ] Listener on `BookingCompleted` → credit loyalty
- [ ] Filament: LoyaltyPrograms (per-vendor config)

### Day 2 — Redemption + Tests
- [ ] `RedeemLoyaltyPointsAction` (during booking creation)
- [ ] Validation: max_redeem_pct, min_points_to_redeem
- [ ] Pest: earn rules (per-vendor isolated)
- [ ] Pest: redemption math
- [ ] Pest: expiration (if configured)

**CUT-LIST:**
- Defer referral rules — Phase 1.5
- Defer expiration cleanup job — Phase 7.0

**EXIT CRITERIA:**
- ✅ Vendor configures program independently
- ✅ Customer balance per-vendor (not global)
- ✅ Redemption affects booking total

**BLOCKS:** None

---

## PHASE 5.3 — Marketing Campaigns (1 day, Week 7)

**GOAL:** Admin creates push/SMS/email/WhatsApp campaign in EN+AR, dispatches to segment.

**PRD COVERAGE:** FR-23 to FR-27

**TABLES TOUCHED:** campaigns, campaign_runs, campaign_recipients

**DELIVERABLE:** Admin creates "10% off Rentals this week" campaign → dispatches to all customers who booked rentals in last 30 days → segments by locale → sends correct template.

**DAYS:**

### Day 1 — Builder + Dispatch
- [ ] Migrations: campaigns, campaign_runs, campaign_recipients
- [ ] Filament Campaign Builder (segment, channel, template)
- [ ] Queue dispatch job
- [ ] Pest: segment resolution (e.g., "rented in last 30 days")
- [ ] Pest: per-locale template selection
- [ ] Pest: respects notification_preferences (opt-out)

**CUT-LIST:**
- Defer A/B testing — Phase 2
- Defer scheduling (send now only) — Phase 1.5

**EXIT CRITERIA:**
- ✅ Admin builds campaign for specific segment
- ✅ All 4 channels dispatch
- ✅ Locale respected per recipient

**BLOCKS:** None

---

## PHASE 6.0 — Reports + Audit Log (2 days, Week 7)

**GOAL:** Admin sees per-type breakdowns + queryable audit log.

**PRD COVERAGE:** Software Description §6 (reporting per-type)

**TABLES TOUCHED:** audit_logs (already exists), event_outbox

**DELIVERABLE:** Filament dashboard with "Bookings by type", "Revenue by period", "Inventory utilization (rental)", "Redemption rate (digital)". Audit log searchable.

**DAYS:**

### Day 1 — Reports
- [ ] Read models / aggregate queries
- [ ] `BookingsByTypeReport`, `RevenueByPeriodReport`, `TopVendorsReport`, `InventoryUtilizationReport` (rental), `RedemptionRateReport` (digital)
- [ ] Filament Dashboard widgets per-type

### Day 2 — Audit + Outbox
- [ ] spatie/laravel-activitylog wired into all state transitions
- [ ] Filament AuditLogViewer with filtering
- [ ] event_outbox processor command
- [ ] Pest: report numbers correct per type
- [ ] Pest: audit captures every state transition

**CUT-LIST:**
- Defer InventoryUtilizationReport — Phase 1.5

**EXIT CRITERIA:**
- ✅ Admin sees per-type metrics
- ✅ Audit log queryable by entity, user, date
- ✅ event_outbox processes pending events

**BLOCKS:** Phase 7.2 (docs reference reports)

---

## PHASE 6.1 — Sale + Digital Excel Imports (1 day, Week 7)

**GOAL:** Replicate Phase 2.4 pattern for sale + digital types.

**PRD COVERAGE:** FR-22

**DELIVERABLE:** Vendor uploads `sales.xlsx` and `digital.xlsx` separately. Per-type validation enforced.

**DAYS:**

### Day 1 — Two Importers + Tests
- [ ] `SaleServicesImport` + `ImportSaleServicesFromExcelAction`
- [ ] `DigitalServicesImport` + `ImportDigitalServicesFromExcelAction`
- [ ] Conditional validation per type
- [ ] Filament UI for each
- [ ] Pest: sale Excel happy path + invalid → no commit
- [ ] Pest: digital Excel happy path + invalid → no commit

**CUT-LIST:** None (this IS the cut-from-Phase-2.4 work)

**EXIT CRITERIA:**
- ✅ All 3 product types importable from Excel
- ✅ No partial commits
- ✅ Per-type validation rules enforced

**BLOCKS:** None

---

## PHASE 6.2 — CMS Pages + Settings (1 day, Week 7)

**GOAL:** Admin edits Terms/Privacy/About/Contact pages in Filament. App settings configurable.

**PRD COVERAGE:** Software Description §5 (CMS pages)

**TABLES TOUCHED:** cms_pages, app_settings, feature_flags

**DELIVERABLE:** Admin edits Terms in EN+AR via TipTap editor. Customer API returns published version.

**DAYS:**

### Day 1 — CMS + Settings
- [ ] Migrations: cms_pages, app_settings, feature_flags
- [ ] Filament CMSPagesResource with TipTap editor
- [ ] Filament Settings page (using filament/spatie-laravel-settings-plugin)
- [ ] API: `GET /api/v1/cms/pages/{slug}`
- [ ] Pest: published-only filter, locale resolution

**CUT-LIST:** None

**EXIT CRITERIA:**
- ✅ Admin edits Terms in EN+AR
- ✅ Customer API serves correct locale
- ✅ Settings panel works

**BLOCKS:** None

---

Phase 6.3 — Admin Customer Management (1 day, Week 7)
GOAL: Admin can view, edit, suspend, and manage customers + see their full history.
TABLES TOUCHED: users, customer_profiles (no schema changes — UI only)
DELIVERABLE: Admin Filament resource for Customers with:

List view with search by phone/email/name
View profile + addresses + booking history + reviews + loyalty balance per vendor
Edit profile fields
Suspend/unsuspend customer
Force logout (revoke all tokens)
View activity timeline (audit log filtered by user)

DAYS:
Day 1 — CustomerResource + Actions

 Filament CustomerResource (under "Users" navigation group)
 Tabs: Overview, Bookings, Reviews, Wallet (loyalty), Addresses, Activity
 Actions: SuspendCustomerAction, ForceLogoutCustomerAction, EditCustomerProfileAction
 Authorization: super-admin + customer-manager role only
 Pest: suspend customer → can't login
 Pest: force logout → tokens revoked

EXIT CRITERIA:

✅ Admin finds any customer in <5 seconds via search
✅ Admin sees customer's full footprint
✅ Suspend works end-to-end

BLOCKS: None

Phase 6.4 — Admin Vendor Management (full CRUD) (1 day, Week 7)
GOAL: Admin can fully manage vendors beyond just approval — edit details, manage docs, view stats.
TABLES TOUCHED: vendor_profiles, vendor_documents, vendor_business_hours, vendor_coverage_areas
DELIVERABLE: Filament VendorResource (separate from approval queue) with:

List + filter by status, type approvals, governorate
Edit business info, hours, coverage areas
Re-upload/replace documents
Force-revoke type approvals with reason
View vendor's services, bookings, payments, wallet, reviews
Impersonate vendor (audit-logged) for debugging

DAYS:
Day 1 — VendorResource + Actions

 Filament VendorResource (different from VendorApprovalQueueResource)
 Tabs: Overview, Documents, Services, Bookings, Wallet, Withdrawals, Reviews, Activity
 Actions: EditVendorProfileAction, UpdateVendorBusinessHoursAction, UpdateVendorCoverageAction, ImpersonateVendorAction (audit-logged)
 Pest: edit doesn't break approval state
 Pest: impersonation creates audit entry

EXIT CRITERIA:

✅ Admin edits vendor without losing approval history
✅ Impersonation always audit-logged
✅ Per-type revocation with reason captured

BLOCKS: None

Phase 6.5 — Admin Booking Override (2 days, Week 7)
GOAL: Admin intervenes in stuck bookings — force state transitions, cancel, re-assign (with audit + customer consent).
TABLES TOUCHED: bookings (status updates), booking_state_transitions (audit), booking_admin_interventions (NEW)
ADR REQUIRED: ADR-0011-admin-booking-override.md
DELIVERABLE: Admin can:

Force-cancel a stuck booking (with refund logic)
Force vendor response timeout
Propose alternative vendor to customer (per FR-17 — propose only)
Add admin note to booking

DAYS:
Day 1 — Schema + Actions

 Write ADR-0011
 Migration: booking_admin_interventions (booking_id, admin_id, intervention_type, reason, before_state, after_state, customer_consent_status)
 Actions: ForceCancelBookingAction, TimeoutVendorResponseAction, ProposeAlternativeVendorAction, AddAdminNoteAction
 Filament: Booking detail page with intervention buttons (gated by permission)
 Customer consent flow: admin proposes → customer accepts/rejects via API/notification

Day 2 — Tests

 Pest: force-cancel triggers refund per type policy
 Pest: vendor response timeout transitions state
 Pest: alternative vendor proposal doesn't auto-replace (FR-17 enforced)
 Pest: every intervention captured in booking_admin_interventions + booking_state_transitions + audit_logs

EXIT CRITERIA:

✅ Admin can resolve stuck booking
✅ FR-17 enforced (no auto-replacement)
✅ Every intervention triple-logged

BLOCKS: None

Phase 6.6 — Admin Financial Operations (2 days, Week 7-8)
GOAL: Admin can manually adjust wallets, issue refunds, manage commission rates beyond automated flows.
TABLES TOUCHED: wallet_ledger (append-only entries), refunds (manual), commission_rates (CRUD), wallet_adjustments (NEW)
ADR REQUIRED: ADR-0012-admin-financial-overrides.md
DELIVERABLE: Filament panels for:

Manual wallet credit/debit (with reason, audit, dual-approval option)
Manual refund issuance (outside automated policy)
Commission rate CRUD (per category × type with effective date ranges)
Reconciliation report (compare gateway captured vs platform recorded)

DAYS:
Day 1 — Schema + Wallet Adjustments

 Write ADR-0012
 Migration: wallet_adjustments (wallet_id, admin_id, amount_minor, reason_code, reason_text, approved_by_admin_id, status)
 Action: AdjustWalletAction — creates wallet_ledger entry + wallet_adjustments row
 Filament: WalletAdjustmentsResource with dual-approval toggle (per app_settings)
 Filament: CommissionRatesResource (full CRUD with effective_from/effective_to)

Day 2 — Manual Refunds + Reconciliation

 Action: IssueManualRefundAction (override per-type policy with explicit reason + audit)
 Filament: ReconciliationReport (gateway vs ledger diff per period)
 Action: ExportReconciliationCsvAction
 Pest: wallet adjustment creates ledger entry, doesn't update existing rows (append-only)
 Pest: dual-approval blocks single-admin adjustments above threshold
 Pest: manual refund logs override reason

EXIT CRITERIA:

✅ Admin adjusts wallet with full audit trail
✅ Dual-approval works above configurable threshold
✅ Manual refund requires explicit reason
✅ Reconciliation report identifies discrepancies

BLOCKS: None

Phase 6.7 — Admin Roles & Permissions UI (1 day, Week 8)
GOAL: Admin can manage roles and permissions visually — assign permissions to roles, users to roles.
TABLES TOUCHED: roles, permissions, model_has_roles (Spatie tables — no schema changes)
DELIVERABLE: Filament panels for:

Role CRUD (create custom roles)
Permission assignment matrix (role × permission grid)
User-role assignment with effective date
Permission preview ("what can this user do?")

DAYS:
Day 1 — Filament + Tests

 Filament RoleResource with permissions matrix
 Filament UserRolesResource for assignment
 Filament page: "Permission Preview" (select user → see all effective permissions)
 Authorization: super-admin only
 Pest: role creation doesn't break Shield-generated permissions
 Pest: permission preview matches $user->getAllPermissions()

EXIT CRITERIA:

✅ Admin creates custom role visually
✅ Permission preview accurate
✅ Shield-generated permissions preserved

BLOCKS: None

Phase 6.8 — Admin Bulk Operations & Exports (1 day, Week 8)
GOAL: Admin can do bulk actions across resources + export data to CSV/Excel.
TABLES TOUCHED: None (UI on existing data)
DELIVERABLE:

Bulk approve/reject vendors
Bulk publish/unpublish services
Bulk export bookings/payments/refunds/commissions to CSV/Excel
Bulk send notifications to selected users

DAYS:
Day 1 — Bulk Actions + Exports

 Filament bulk actions on every resource where applicable
 Export jobs (queued) for: BookingsExport, PaymentsExport, RefundsExport, CommissionsExport, VendorsExport, CustomersExport
 Export delivered via signed URL email or in-app download
 Pest: bulk approve 10 vendors in one action
 Pest: export job runs successfully and produces valid file

EXIT CRITERIA:

✅ Bulk actions work on all major resources
✅ Exports queue correctly and deliver via email
✅ CSV/Excel valid and openable

## PHASE 7.0 — Hardening: Performance + Security (2 days, Week 8)

**GOAL:** App performant + secure for soft launch.

**PRD COVERAGE:** NFR (security, performance)

**TABLES TOUCHED:** None (perf indexes only)

**DELIVERABLE:** Rate limits in place, no N+1s, security audit clean.

**DAYS:**

### Day 1 — Security
- [ ] Rate limiting: auth (5/min), payments (10/min), search (60/min)
- [ ] CORS lockdown (only known frontend domains)
- [ ] CSRF on stateful routes
- [ ] SQL injection check (audit `whereRaw` and `DB::raw`)
- [ ] Mass assignment audit (`$fillable`/`$guarded` review)
- [ ] 2FA for admin (TOTP)

### Day 2 — Performance
- [ ] N+1 audit (Laravel Debugbar)
- [ ] Add missing indexes per slow query log
- [ ] Eager-load on heavy queries
- [ ] Redis caching (sessions, model caching where useful)
- [ ] spatie/laravel-backup configured (daily DB to S3)

**CUT-LIST:**
- Defer 80% test coverage to Phase 1.5

**EXIT CRITERIA:**
- ✅ Rate limits work
- ✅ Zero high-severity findings in security audit
- ✅ N+1 queries eliminated in critical paths
- ✅ Backup runs successfully

**BLOCKS:** Phase 7.1

---

## PHASE 7.1 — Staging Deploy + Smoke Tests (2 days, Week 8)

**GOAL:** Production-equivalent staging working with full E2E test.

**PRD COVERAGE:** Production readiness

**DELIVERABLE:** `https://staging.instaparty.com/admin` works. Smoke test passes for all 3 types in EN+AR.

**DAYS:**

### Day 1 — Deploy Pipeline
- [ ] GitHub Actions: build Docker, push to ghcr.io
- [ ] SSH deploy to Hetzner CCX13
- [ ] Caddy + automatic HTTPS
- [ ] Migration in deploy script
- [ ] Health check `/up`

### Day 2 — Smoke Tests
- [ ] E2E manual test: register vendor → admin approves rental → vendor creates rental → customer books → vendor accepts → customer pays → commission credited → vendor withdraws → admin approves
- [ ] Repeat for sale type
- [ ] Repeat for digital type
- [ ] All flows in EN
- [ ] All flows in AR (RTL UI in Filament)

**CUT-LIST:** None (this is launch readiness)

**EXIT CRITERIA:**
- ✅ Staging URL accessible over HTTPS
- ✅ All 3 product types pass full lifecycle
- ✅ EN + AR both work end-to-end
- ✅ Deploy is repeatable (run pipeline twice, same result)

**BLOCKS:** Phase 7.2

---

## PHASE 7.2 — Documentation + Retrospective (1 day, Week 8)


PHASE 8.0 — Admin Service Moderation & Publish Workflow (2 days)

GOAL: Admin can moderate new services and material edits for all three product types before they become customer-visible.

PRD COVERAGE: FR-19, FR-22, FR-29; Admin Journey step 6–8 (receive new services/material edits, review, approve/publish or reject/request edit).

ADR REQUIRED: ADR-0013-admin-service-moderation.md

DEPENDS ON:

Phase 2.1 / 2.2 / 2.3 service creation flows
Locked cross-type actions and per-type service resources
Existing services.status lifecycle and admin permissions like service.moderate / per-type publish permissions.

TABLES TOUCHED:
services only. No new moderation tables in Phase 1. Use existing status, moderation_notes, moderated_at, moderated_by.

DELIVERABLE:
Admin sees pending rental/sale/digital services in Filament, reviews them, then approves and publishes or rejects with notes. Material edits on published services send them back to moderation.

DAYS:

Day 1 — Moderation state flow + actions
 Write ADR-0013-admin-service-moderation.md
 Reuse locked services.status flow: draft → pending_review → published → rejected → archived
 Define “material edit” set: price, category, title, short/long description, core media
 Implement SubmitServiceForReviewAction
 Implement ModerateServiceAction
 Implement PublishServiceAction
 Implement ArchiveServiceAction
 Material edits on published service trigger status back to pending_review
 “Request changes” is represented as rejected + bilingual moderation_notes
Day 2 — Filament queues + tests
 Add moderation queue views to RentalServiceResource, SaleServiceResource, DigitalServiceResource
 Add filters: pending_review, rejected, published, archived
 Add admin bulk actions: approve selected / reject selected / archive selected
 Pest: approve service
 Pest: reject service with moderation notes
 Pest: material edit returns service to pending_review
 Pest: non-material edit does not bypass moderation rules
 Pest: all three types covered

CUT-LIST:
Defer richer moderation history timeline to Phase 1.5; current source of truth remains services fields + audit_logs.

EXIT CRITERIA:

✅ Admin can review pending services for all 3 product types
✅ Approve/publish works from Filament
✅ Reject-with-notes works in EN/AR
✅ Material edits always re-enter moderation
✅ Pest covers rental, sale, digital

BLOCKS: 8.1, 8.5, 8.9

PHASE 8.1 — Admin Active Ops Dashboard & Queues (1 day)

GOAL: Admin has a single daily operations dashboard for critical states and queue entry points.

PRD COVERAGE: FR-29 and Admin Journey dashboard/start loop: alerts, overdue bookings, pending approvals, services awaiting moderation, general KPIs.

ADR REQUIRED: None. Fits existing Reporting + Filament dashboard pattern.

DEPENDS ON:

Phase 1.1 vendor approval queue
Phase 8.0 service moderation queue
Phase 4.0 / 4.2 payment + withdrawals
Existing reporting/dashboard patterns from 5.3 and 6.0.

TABLES TOUCHED:
No schema change. Read-only aggregates from vendor_profiles, vendor_approved_product_types, services, booking_vendors, payments, withdrawals, chat_moderation_flags, audit_logs.

DELIVERABLE:
Filament ops dashboard with widgets for pending vendor approvals, pending service moderation, overdue vendor responses, payment failures, pending withdrawals, and suspicious chat/compliance flags.

DAYS:

Day 1 — Widgets + routing + tests
 Build AdminOpsDashboard
 Widget: pending vendor approvals
 Widget: pending service moderation
 Widget: overdue vendor responses using booking_vendors.response_deadline
 Widget: failed/at-risk payments
 Widget: pending withdrawals
 Widget: chat/compliance flags
 Add severity sort and “stale for X hours”
 Add click-through links into filtered resources
 Pest: widget counts correct
 Pest: overdue logic correct
 Pest: queue links route to correct filters

CUT-LIST:
Defer personalized admin dashboard layouts to Phase 1.5.

EXIT CRITERIA:

✅ One /admin dashboard shows all core operational queues
✅ Overdue vendor-response widget respects 24h SLA
✅ Every widget links to a real queue view
✅ Counts match underlying data

BLOCKS: 8.8, 8.9

PHASE 8.2 — Restricted Chat, Compliance & Off-Platform Prevention (2 days)

GOAL: Admin can monitor risky conversations, freeze threads, and review off-platform contact violations without editing user chat content.

PRD COVERAGE: NFR auditability + Admin Journey steps 10–15: monitor restricted chat, keep logs, send escalations, prevent off-platform communication.

ADR REQUIRED: ADR-0014-chat-compliance-admin-oversight.md

DEPENDS ON:

Locked chat architecture: actual chat in Firestore, metadata/audit in MySQL
Phase 5.0 notifications for escalations
Phase 3.2 / 6.5 intervention flows for stuck bookings.

TABLES TOUCHED:
chat_threads, chat_message_log, chat_moderation_flags, optionally notification_dispatches for escalations. No chat_messages table.

DELIVERABLE:
Admin can review flagged messages, freeze/unfreeze a thread, inspect the append-only audit mirror, and escalate a case, while never editing message content.

DAYS:

Day 1 — Rules + moderation actions
 Write ADR-0014-chat-compliance-admin-oversight.md
 Confirm Firestore listener writes to chat_message_log
 Add regex/heuristics for phone numbers, emails, social handles, external links
 Implement FlagSuspiciousMessageAction
 Implement FreezeChatThreadAction
 Implement UnfreezeChatThreadAction
 Implement EscalateChatCaseAction
 Use chat_threads.status = open|locked|closed
 Add bilingual escalation templates if needed
Day 2 — Filament queue + tests
 Build ChatModerationQueue
 Filter by flag type: phone, email, external_link, profanity, other
 Thread detail page shows audit mirror only
 Add booking/vendor/customer context side panel
 Pest: phone pattern flags message
 Pest: email pattern flags message
 Pest: locked thread blocks further send flow
 Pest: admin can inspect audit history
 Pest: admin cannot edit message content
 Pest: flagged thread appears in moderation queue

CUT-LIST:
Defer ML-based abuse detection; keep regex/heuristic rules only in Phase 1.

EXIT CRITERIA:

✅ Suspicious messages are flagged automatically
✅ Admin can freeze/unfreeze threads
✅ Audit mirror is visible and append-only
✅ No admin path exists to edit chat content
✅ Escalation can trigger notifications

BLOCKS: 8.8, 8.9

PHASE 8.3 — Offer Governance (campaign-backed in Phase 1) (1 day)

GOAL: Admin can govern platform/vendor promotional offers in a Phase 1-safe way using campaigns and settings, without adding an unlocked pricing engine.

PRD COVERAGE: FR-23 to FR-27; Admin Journey “manage discounts & promos,” but constrained by Phase 1 locked schema and existing campaign infrastructure.

ADR REQUIRED: ADR-0015-offer-governance-phase1.md

DEPENDS ON:

Phase 5.3 Marketing Campaigns
campaigns, campaign_runs, campaign_recipients, notification_templates, notification_preferences, app_settings, feature_flags
No new promo_codes / discount_rules engine in Phase 1.

TABLES TOUCHED:
campaigns, campaign_runs, campaign_recipients, notification_templates, notification_preferences, app_settings, feature_flags.

DELIVERABLE:
Admin can create and govern promotional offers as campaign-based messages and homepage/app exposure, targeted by vendor, category, product type, and locale.

DAYS:

Day 1 — Governance layer + tests
 Write ADR-0015-offer-governance-phase1.md
 Add “Offer Governance” admin page over existing campaigns
 Add campaign presets: platform-wide, vendor-specific, category-specific, type-specific
 Add optional homepage/banner exposure toggle via feature_flags / app_settings
 Add locale-aware preview
 Add approval note / internal offer policy notes
 Pest: vendor segment selection works
 Pest: category/type targeting works
 Pest: locale-specific template resolution works
 Pest: opt-out respected through notification_preferences

CUT-LIST:
Full promo codes, basket pricing rules, usage caps, and booking-time discount math defer to Phase 1.5 or later, after Tech Decisions + DB Schema are updated.

EXIT CRITERIA:

✅ Admin can run a platform-wide or vendor-targeted offer campaign
✅ Offer messaging respects locale
✅ Offer visibility can be feature-flagged
✅ No unlocked discount schema is introduced

BLOCKS: 8.9

PHASE 8.4 — Tax Invoice Request Oversight & Settlement Linking (1 day)

GOAL: Admin can track invoice-requested bookings and link them to finance and settlement review in a Phase 1-safe way.

PRD COVERAGE: Admin Journey “track tax invoices & related settlements,” with the explicit constraint that tax handling is foundational in Phase 1 and advanced invoicing is Phase 2.

ADR REQUIRED: None. This is an oversight layer over existing finance data.

DEPENDS ON:

Booking fields tax_invoice_required, tax_invoice_name, tax_invoice_id
Payments, refunds, withdrawals, settlement runs
Existing reporting/filtering patterns.

TABLES TOUCHED:
bookings, payments, refunds, withdrawals, settlement_runs, optionally app_settings for filter defaults. No tax_invoices table in Phase 1.

DELIVERABLE:
Admin page listing invoice-requested bookings with filters into payment/refund/withdrawal/settlement records and a foundational export/report.

DAYS:

Day 1 — Oversight page + filters + tests
 Build TaxInvoiceRequestsPage
 Show booking reference, customer, vendor split, tax invoice name, tax invoice id, payment status
 Add finance filters: paid / refunded / withdrawn / settled
 Allow admin to update or confirm booking-level tax_invoice_id
 Add export for invoice-requested booking finance summary
 Pest: booking with tax_invoice_required=true appears correctly
 Pest: invoice identifier persists on booking
 Pest: settlement/refund views filter correctly for invoice-requested bookings

CUT-LIST:
Real invoice line items, fiscal adjustments, PDF issuance, and accounting-grade reconciliation defer to Phase 2.

EXIT CRITERIA:

✅ Admin can find every booking that requested a tax invoice
✅ Admin can link invoice identifiers at booking level
✅ Related payment/refund/settlement records are discoverable
✅ Export/report works for Phase 1 oversight

BLOCKS: 8.9

PHASE 8.5 — Content Safety & Policy-Violating Content Removal (1 day)

GOAL: Admin can hide or remove fake, misleading, or policy-violating content after publication.

PRD COVERAGE: Admin Journey “delete / hide fake or policy-violating content,” plus content moderation for reviews and CMS-managed content.

ADR REQUIRED: ADR-0016-content-safety-enforcement.md

DEPENDS ON:

Phase 8.0 moderation flow
Phase 5.1 reviews
Phase 6.2 CMS pages
audit_logs for enforcement traceability.

TABLES TOUCHED:
services, service_reviews, vendor_reviews, review_moderation_log, cms_pages, media, audit_logs. No new content_reports or content_enforcement_actions tables in Phase 1.

DELIVERABLE:
Admin can archive a service, moderate/hide a review, unpublish a CMS page, and remove problematic vendor media, with every action audited.

DAYS:

Day 1 — Enforcement actions + tests
 Write ADR-0016-content-safety-enforcement.md
 Implement HideServiceAction using services.status = archived
 Implement RestoreServiceAction back to moderation or published state by policy
 Extend review moderation tools for hide/remove
 Implement HideCmsPageAction via cms_pages.is_published = false
 Implement RemoveVendorMediaAction using Spatie Media Library + audit log
 Pest: archived service disappears from customer API
 Pest: unpublished CMS page disappears from public API
 Pest: enforcement action is audit-logged
 Pest: restored service follows allowed moderation path

CUT-LIST:
User-facing report/appeal workflow defers to Phase 1.5.

EXIT CRITERIA:

✅ Admin can hide live services
✅ Admin can moderate/remove reviews
✅ Admin can unpublish CMS content
✅ Every enforcement action is audit-logged

BLOCKS: 8.9

PHASE 8.6 — Loyalty Governance & Vendor Rule Oversight (1 day)

GOAL: Admin can oversee vendor loyalty programs while respecting the locked rule that loyalty is per-vendor only.

PRD COVERAGE: Admin Journey “manage loyalty rules,” constrained to per-vendor configurable loyalty in the locked docs.

ADR REQUIRED: None. This is governance over existing loyalty schema.

DEPENDS ON:

Phase 5.2 Loyalty (per-vendor)
Existing admin settings/reporting patterns
Locked no-platform-wide-loyalty rule.

TABLES TOUCHED:
loyalty_programs, loyalty_rules, loyalty_ledger, loyalty_redemptions, optionally audit_logs. No platform_loyalty_settings.

DELIVERABLE:
Admin can inspect, filter, activate/deactivate, and audit vendor loyalty programs and detect outlier redemption behavior.

DAYS:

Day 1 — Oversight views + tests
 Build LoyaltyProgramsAdminView
 Build LoyaltyRulesAdminView
 Add filters: active/inactive, high redemption rate, expired, vendor-specific
 Add admin action to suspend or reactivate a vendor loyalty program
 Show per-vendor balances and redemption totals
 Pest: loyalty remains isolated per vendor
 Pest: admin can deactivate/reactivate vendor program
 Pest: redemption math still respects vendor rules
 Pest: no global loyalty configuration path exists

CUT-LIST:
Cross-vendor loyalty analytics can defer to Phase 1.5.

EXIT CRITERIA:

✅ Admin can review any vendor loyalty program
✅ Admin can disable abusive or misconfigured vendor programs
✅ Loyalty remains strictly per-vendor
✅ Tests prove no platform-wide rule leakage

BLOCKS: 8.7, 8.9

PHASE 8.7 — Admin Review & Policy Oversight Hub (1 day)

GOAL: Admin can monitor review health, policy thresholds, and vendor reputation risk from one place.

PRD COVERAGE: Reviews + Admin Journey “review ratings, reviews, percentages, policies.”

ADR REQUIRED: None. Built on existing Reviews + Settings + Reporting layers.

DEPENDS ON:

Phase 5.1 Reviews
Phase 6.0 Reports + Audit Log
Phase 8.6 loyalty oversight for cross-vendor quality view if desired.

TABLES TOUCHED:
service_reviews, vendor_reviews, review_responses, review_moderation_log, vendor_profiles, optionally app_settings for thresholds.

DELIVERABLE:
Admin oversight hub showing flagged reviews, low-rating vendors, repeated complaint patterns, moderation backlog, and configurable risk thresholds.

DAYS:

Day 1 — Oversight hub + tests
 Build ReviewPolicyOversightHub
 Add panels: flagged reviews, low-rating vendors, repeated complaint vendors, unresolved moderation items
 Add thresholds in app_settings: minimum vendor rating, repeat complaint count, moderation SLA
 Add drill-down links into review moderation pages
 Pest: low-rating vendor enters alert list
 Pest: repeat complaint threshold flags vendor
 Pest: threshold changes in app_settings affect hub results

CUT-LIST:
Sentiment analysis or NLP clustering defers to Phase 1.5.

EXIT CRITERIA:

✅ Admin sees reviews + vendor reputation risk in one hub
✅ Threshold-based alerts work
✅ Existing moderation pages remain source of action
✅ Tests confirm threshold behavior

BLOCKS: 8.8, 8.9

PHASE 8.8 — Admin Daily Operations Runbook Actions (1 day)

GOAL: Admin has a repeatable daily ops page with explicit acknowledgment and resolution actions, not just passive dashboards.

PRD COVERAGE: Admin Journey “continue daily operations” and return-to-dashboard operational loop.

ADR REQUIRED: None. Uses existing audit and queue infrastructure.

DEPENDS ON:

Phase 8.1 dashboard/queues
Phase 8.2 chat compliance
Phase 8.7 oversight hub
Existing audit_logs and queue resources.

TABLES TOUCHED:
No new checklist table. Use existing data + audit_logs. Optionally use event_outbox for follow-up jobs.

DELIVERABLE:
Daily admin operations page showing unresolved critical items with actions such as acknowledge alert and resolve issue, all logged through audit trails.

DAYS:

Day 1 — Daily ops page + actions + tests
 Build DailyOpsPage
 Sections: pending approvals, pending moderation, overdue bookings, pending withdrawals, flagged chat issues, low-rating alerts
 Implement AcknowledgeAlertAction
 Implement ResolveOperationalIssueAction
 Log every acknowledgment/resolution into audit_logs
 Add “still unresolved” logic so items remain visible until state changes
 Pest: unresolved critical items remain visible
 Pest: acknowledge is audit-logged
 Pest: resolve is audit-logged
 Pest: totals match underlying queues

CUT-LIST:
Per-admin checklists and shift handoff notes defer to Phase 1.5.

EXIT CRITERIA:

✅ Admin has one daily-ops screen
✅ Critical items can be acknowledged/resolved
✅ Nothing disappears without a real underlying state change
✅ Audit trail exists for all ops actions

BLOCKS: 8.9

PHASE 8.9 — Full Admin Journey Smoke Test (1 day)

GOAL: Prove that the complete admin journey works end-to-end on staging, not just isolated resources.

PRD COVERAGE: Final validation of Admin Journey steps across moderation, intervention, compliance, settlements, content safety, reviews, and daily ops.

ADR REQUIRED: None.

DEPENDS ON:
All phases 8.0–8.8 plus existing staging flow in Phase 7.1.

TABLES TOUCHED:
No schema change. End-to-end verification only.

DELIVERABLE:
A documented pass/fail admin smoke checklist with evidence for the full admin lifecycle.

DAYS:

Day 1 — Staging E2E admin validation
 Approve vendor per type
 Moderate and publish service
 Detect overdue vendor response
 Propose alternative vendor without auto-replacement
 Review suspicious chat flag
 Freeze/unfreeze chat thread
 Approve withdrawal and upload transfer proof
 Archive violating service or unpublish violating CMS page
 Run campaign-backed offer flow
 Review invoice-requested booking flow
 Moderate flagged review
 Use daily ops page to acknowledge/resolve at least one issue
 Capture screenshots/log evidence for each step
 Record pass/fail checklist with notes

CUT-LIST:
None. This is launch-readiness validation for the admin journey.

EXIT CRITERIA:

✅ Full admin journey passes on staging
✅ FR-17 preserved: admin only proposes alternatives, never auto-replaces
✅ Chat oversight works without content editing
✅ Settlement/content/review/daily-ops flows all work
✅ Evidence pack exists for handoff

BLOCKS: Phase 7.2 documentation updates

Final notes

This final version keeps your added admin phases aligned with the locked rules and existing Phase 1 scope. The biggest deliberate narrowings are:

8.3 is offer governance through campaigns/settings, not a brand-new discount engine.
8.4 is tax-invoice-request oversight, not a full tax invoice subsystem.
8.6 is vendor loyalty oversight, not platform-wide loyalty.

**GOAL:** Project documented for ops handoff. Lessons learned for Phase 1.5.

**PRD COVERAGE:** Delivery readiness

**DELIVERABLE:** README, API docs, admin guide, runbook, retrospective doc.

**DAYS:**

### Day 1 — Docs + Retro
- [ ] README.md (project overview + setup)
- [ ] API docs via Scribe (run `php artisan scribe:generate`)
- [ ] Filament admin user guide for ops team
- [ ] Runbook: deploy, rollback, common operations
- [ ] **Retrospective doc:** what worked, what didn't, what to fix in Phase 1.5

**CUT-LIST:**
- Defer Scribe → keep internal Postman collection only

**EXIT CRITERIA:**
- ✅ README builds correctly
- ✅ API docs reflect actual endpoints
- ✅ Admin user can find their way around `/admin`
- ✅ Retrospective drives Phase 1.5 priorities

**BLOCKS:** Phase 1.5 kickoff

---

## Daily Discipline (unchanged from v1)

1. **One micro-phase per chunk** — finish the 1-3 day phase fully before starting the next
2. **Tests with the code, not after** — Pest tests are Day-3 work, not "later"
3. **Commit at end of every day** even WIP
4. **End-of-phase deploy to staging** — small enough to test deploy each phase
5. **Run `php artisan pint` and `phpstan` daily** (auto via post-edit hook)
6. **Spec-guard hook warnings → fix in same session**
7. **No new packages without entry in `10_Package_List.md`**
8. **End of every Friday: 1-hour review + plan next Monday's micro-phase**

---

## How to Use This Plan with Claude Code

### Starting a phase

```
START PHASE X.Y — {Name}

[Use the optimized prompt from earlier conversation]
```

Claude Code:
1. Reads CLAUDE.md, this file, schema, etc.
2. Restates phase goal + tables + ADR + tests
3. Plans tasks with file paths and citations
4. Waits for "go"

### Mid-phase resume

```
RESUME PHASE X.Y

[Use the resume prompt]
```

Claude Code shows last completed task, next task, failing tests.

### End of phase

```
1. Verify all exit criteria pass
2. Commit with conventional format
3. Update phase index above (mark done)
4. Move to next phase
```

### When behind

Reference the per-phase cut-list. **Cut features, not quality.** Quality cut = bug in production = lost vendors.

---

## Comparison: v1 → v2

**Same:**
- Total scope (8 weeks, ~40 days, all 60 tables, all 13 modules)
- Final deliverable (production-ready Phase 1)
- Tech stack (locked)
- Source-of-truth hierarchy
- Cut-list philosophy

**Different:**
- 8 phases → 16 phases
- 5-7 days/phase → 1-3 days/phase
- "Catalog" as one phase → "Catalog Foundation" + "Rental" + "Sale" + "Digital" + "Excel"
- "Discovery + Booking" → "Discovery" + "Booking Draft" + "Booking Negotiation"
- "Payments + Settlement" → "Paymob Gateway" + "Refunds" + "Settlement"
- "Communications + Reviews + Loyalty" → 4 separate phases
- One ADR per major module → one ADR per phase (forces granular thinking)

**Why this matters for AI:**
- Claude Code can hold 1-3 day's worth of context cleanly
- Each phase has ONE deliverable to focus on
- ADR per phase prevents scope drift
- `/scope-audit` works better on small phases

---

## Archive Note

The original `09_Phasing_Plan.md` is still valid as a high-level overview but **this v2 file is the executable plan**. When in doubt, follow v2 day-by-day tasks.