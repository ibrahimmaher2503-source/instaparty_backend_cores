# InstaParty — Phasing Plan (Phase 1, 8 weeks aggressive solo)

> **Owner:** Ibrahim (solo full-stack)
> **Scope:** Backend (Laravel API) + Admin (Filament v3) only. Mobile and Next.js come AFTER Phase 1.
> **Honest sizing:** This is aggressive. Plan assumes ~6 productive hours/day, no major distractions, no client scope changes.

---

## Source-of-truth rule

Every phase must respect: `docs/specs/01_PRD.md`, `CLAUDE.md`, `docs/specs/03_Three_Product_Types.md`, `docs/specs/02_Tech_Decisions.md`, `docs/specs/10_Package_List.md`, `.claude/rules/filament-components.md`.

Phase 2 features (subscription tiers, platform packages, dispute engine, card templates, page slider, QR catalog, advanced tax) are **forbidden** in Phase 1. If asked to add them, push back and reference `01_PRD.md` §5.2.

---

## Risk register (read once, refer back when slipping)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Three product types triple effort vs single-type marketplace | High | High | Commit to per-type pattern in W2; once one type works, replicate via copy-modify |
| Paymob sandbox webhook signing | High | Medium | Allocate full 2 days in W5; have ngrok ready Day 1 |
| Bilingual EN/AR Filament forms slower than expected | High | Medium | Use translatable plugin from W2; never delay AR until later |
| Meilisearch Arabic tokenizer config | Medium | Low | Standard `ar` tokenizer + index per locale field works for Phase 1 |
| Real-time chat moderation (regex blocker) | Medium | Medium | Phase 1 = synchronous regex check before write; advanced ML can be Phase 2 |
| Excel imports across 3 product types | Medium | High | Start with one type's importer in W2-3; replicate in W7 |
| Test coverage falling behind | High | High | Pest tests written *with* each module — never deferred |

**If any risk fires and you fall a week behind, follow the cut-list per phase.**

---

## Phase 0 — Foundation (Week 1)

**Goal:** A bootable Laravel 12 + Filament v3 app with the Geography module, Docker dev environment, CI pipeline. Verified deployable to staging.

### Day 1
- [ ] `composer create-project laravel/laravel instaparty`
- [ ] Drop in this entire setup bundle: `CLAUDE.md`, `docs/specs/`, `.claude/`
- [ ] Make hooks executable: `chmod +x .claude/hooks/*.sh`
- [ ] `composer require` foundation packages (see `10_Package_List.md` §1)
- [ ] `git init`, first commit, push to GitHub

### Day 2
- [ ] Docker Compose: app, mysql, redis, meilisearch, mailpit, minio
- [ ] `.env.example` with all keys documented
- [ ] Laravel Sanctum scaffold (SPA mode)
- [ ] Spatie Permission + roles seeder
- [ ] Filament v3 install at `/admin`, custom resource discovery scanning `app/Modules/*/Filament/Resources/`

### Day 3
- [ ] **Geography module** — full slice: migrations (governorates, regions, cities), models, factories, seeders for Egypt, Filament Resources with translatable EN/AR
- [ ] First Pest tests: model factory, translatable read/write
- [ ] **Verification:** seed Egypt geography, browse it in Filament

### Day 4
- [ ] **Identity module migrations only:** users, vendor_profiles, vendor_documents, vendor_approved_product_types, vendor_business_hours, vendor_coverage_areas, customer_profiles, customer_addresses, user_devices, two_factor_secrets
- [ ] Run `php artisan migrate` and verify schema
- [ ] Add `MoneyCast` (custom Eloquent cast for `Brick\Money`)

### Day 5
- [ ] GitHub Actions CI: install, migrate, run Pest, run Pint, run PHPStan
- [ ] Caddy + Docker compose for staging
- [ ] Manual deploy to Hetzner CX22 staging box
- [ ] **Sanity check:** Visit `https://staging.instaparty.com/admin` and log in

### End of W1 — Deliverables
- Bootable app at `/` (API) and `/admin` (Filament)
- Geography module complete with tests
- Identity migrations applied (models/Resources come in W2)
- CI green
- Staging deployed

### W1 Cut-list (if behind)
- Defer Caddy/staging deploy to W8
- Skip CI for now, run tests locally only

---

## Phase 1 — Identity & Vendor Onboarding (Week 2 first half)

**Goal:** Vendors can sign up, upload documents, get approved per product type. Customers can sign up.

### Days 6-7
- [ ] Identity module: Models (User, VendorProfile, CustomerProfile, etc.), all relationships, scopes, casts
- [ ] Sanctum SPA + token guards configured
- [ ] Spatie roles + per-type vendor permissions: `service.create.{rental|sale|digital}.own` (and `update`, `delete`, `publish`)
- [ ] Auth Actions: `RegisterCustomerAction`, `RegisterVendorAction`, `LoginAction`, `LogoutAction`
- [ ] Phone verification flow (OTP via SMS gateway — stub provider in W2, real in W6)

### Day 8
- [ ] Filament Resources:
  - Users (basic)
  - Vendor Profiles
  - **Vendor Approval Queue** (with per-product-type approval action buttons)
  - Customer Profiles (read-only)
- [ ] Shield permissions generated: `php artisan shield:generate --all`
- [ ] First admin user seeder

### W2 (first half) — Deliverables
- Sign up as customer or vendor via API
- Admin can approve/reject vendor per product type
- Filament Vendor Approval Queue functional in EN + AR

---

## Phase 2 — Catalog (Week 2 second half + Week 3)

**Goal:** Vendors can create rental, sale, and digital services with full bilingual fields. Excel import works for at least one type. Filament has 3 separate Service Resources.

### Days 9-10
- [ ] Catalog migrations: occasions, categories, service_themes, category_service_field_schemas, services (base), service_rental_details, service_sale_details, service_digital_details, service_pricing_tiers, service_availability_blocks, service_excluded_dates, service_themes pivot, **service_inventory_reservations**
- [ ] `App\Modules\Catalog\Domain\Enums\ProductType` enum
- [ ] Models with `$translatable` arrays
- [ ] Per-type Form Requests: `CreateRentalServiceRequest`, etc.

### Days 11-12
- [ ] Per-type Actions:
  - `CreateRentalServiceAction`, `UpdateRentalServiceAction`
  - `CreateSaleServiceAction`, `UpdateSaleServiceAction`
  - `CreateDigitalServiceAction`, `UpdateDigitalServiceAction`
  - `PublishServiceAction`, `ArchiveServiceAction`, `ModerateServiceAction` (cross-type)
- [ ] `ProductType` enum used everywhere with `match($enum)` for cross-type code
- [ ] API endpoints (vendor-facing): `POST /api/v1/vendor/services/{type}`, `GET /api/v1/vendor/services?type={...}`
- [ ] API Resources: `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` extending base

### Days 13-14
- [ ] **Filament Resources for services — all three:**
  - `RentalServiceResource`
  - `SaleServiceResource`
  - `DigitalServiceResource`
- All grouped under "Services" navigation. Each with translatable EN/AR tabs.
- [ ] Filament Resources: Occasions, Categories, Service Themes
- [ ] Inventory reservation Action: `HoldServiceInventoryAction` (15-min cart hold, 24-hour payment hold)
- [ ] Pest tests covering all three types per Action

### Day 15
- [ ] **Excel imports** — start with rental template:
  - `RentalServicesImport` (Maatwebsite Excel)
  - Bilingual columns (`name_en`, `name_ar`, etc.)
  - Validation: per-row, no partial commits
  - Background queue for >50 rows
- [ ] `ImportRentalServicesFromExcelAction`
- [ ] Filament page: bulk-upload with progress + per-row error feedback in user's locale

### End of W2-W3 — Deliverables
- Three product types fully scaffolded in DB, code, Filament
- Vendors create services via API per type (each tested)
- Vendors bulk-import rental services from Excel
- Per-type Filament Resources working in EN + AR

### W2-3 Cut-list (if behind)
- Defer Sale and Digital Excel importers to W7
- Defer service themes pivot/UI (use simple text tag for now)
- Defer pricing tier UI (one base price per service for now)

---

## Phase 3 — Discovery + Booking Core (Week 4)

**Goal:** Customers browse services, build a booking with multiple vendors, and submit it. Vendors see incoming bookings.

### Day 16
- [ ] Discovery module:
  - Meilisearch setup (Scout driver) with `product_type` facet
  - Service indexer with per-locale fields
  - `SearchServicesAction` with type filter
  - Customer-facing API: `GET /api/v1/customer/services?occasion=...&type=...&filters=...`

### Days 17-18
- [ ] Booking migrations: bookings, **booking_snapshots** (versioned read model), booking_addresses, booking_locks, booking_vendors, booking_items, booking_modifications, booking_state_transitions, booking_customer_notes
- [ ] Booking module Models, with the **3 orthogonal status columns** on `bookings`: `lifecycle_status`, `payment_status`, `fulfillment_status`
- [ ] Per-type fulfillment state machines on `booking_items.item_status` (3 distinct graphs via spatie/laravel-model-states)

### Day 19
- [ ] Booking Actions:
  - `CreateBookingDraftAction` (cross-type)
  - `AddItemToBookingAction` (with `match($enum)` for type-specific reservation logic)
  - `SubmitBookingAction` — splits per vendor, fires `BookingSubmittedToVendor` event
  - `VendorAcceptBookingAction`, `VendorModifyBookingAction`, `VendorRejectBookingAction`
  - `CustomerConfirmModifiedBookingAction`
- [ ] Idempotency keys for submit and confirm

### Day 20
- [ ] Filament Resources: Bookings Monitor (with per-type filter), Booking Item Fulfillment
- [ ] Filament action: admin can intervene on stalled bookings (NEVER auto-replace per FR-17)
- [ ] Pest tests: full negotiation loop covering all three types

### End of W4 — Deliverables
- Customer can search services with `product_type` facet
- Customer creates draft → adds items → submits booking
- Vendor accepts / modifies / rejects via API
- Admin monitors via Filament

### W4 Cut-list (if behind)
- Defer Meilisearch sync to a queued job (run sync manually for testing)
- Skip booking_snapshots versioning (use latest snapshot only)
- Defer admin intervention workflow to W7

---

## Phase 4 — Payments & Settlement (Week 5)

**Goal:** Customer pays for confirmed bookings via Paymob. Commissions calculated. Vendors see wallet balance. Withdrawals work.

### Day 21
- [ ] `PaymentGateway` interface in `Modules/Payments/Domain/Contracts/`
- [ ] `PaymobGateway` implementation in `Infrastructure/Gateways/`
- [ ] Webhook endpoint `/webhooks/paymob` with signature verification
- [ ] `idempotency_keys` table + middleware

### Days 22-23
- [ ] Payments migrations: payments, payment_attempts, refunds, idempotency_keys
- [ ] `InitiatePaymentAction`, `CapturePaymentAction`, `ProcessPaymobWebhookAction`
- [ ] Per-type refund policies via `RefundPolicyService::policyFor(ProductType)`:
  - Rental: 24h before event_starts_at (configurable)
  - Sale: until item enters `in_preparation` state
  - Digital: per `is_refundable_after_delivery` flag

### Days 24-25
- [ ] Settlement migrations: commissions, commission_rates, wallets, wallet_ledger (append-only), withdrawals, settlement_runs
- [ ] Commission rate resolution: `(category × type) → (category × NULL) → (NULL × type) → (NULL × NULL)` — most specific wins
- [ ] `CalculateCommissionAction`, `CreditVendorWalletAction`, `RequestWithdrawalAction`, `ApproveWithdrawalAction`
- [ ] Filament Resources: Withdrawals Queue, Wallets & Ledger Viewer, Commission Rules

### End of W5 — Deliverables
- End-to-end booking → payment → commission → vendor wallet credit
- Admin processes withdrawal via Filament with proof upload
- All three product types tested through full payment cycle

### W5 Cut-list (if behind)
- Defer split payments (one payment per booking)
- Defer GCC gateway adapters (Tabby, Tamara) — stay Paymob-only
- Defer settlement runs (manual reconciliation OK for soft launch)

---

## Phase 5 — Communications + Reviews + Loyalty (Week 6)

**Goal:** Notifications fire on key events. Reviews work. Per-vendor loyalty rules.

### Day 26
- [ ] Communication module migrations: notification_templates, notification_dispatches, notification_preferences, chat_message_log, chat_moderation_flags, marketing_campaigns, campaign_recipients
- [ ] `DispatchNotificationAction` selects template by `(event_key × channel × audience × locale)`
- [ ] Channel adapters: push (FCM), email (Mailgun/Mailchimp), SMS (Vonage or local), WhatsApp (Cloud API)

### Day 27
- [ ] Per-type notification events:
  - `rental.delivery_scheduled`, `rental.setup_started`, `rental.teardown_completed`
  - `sale.preparation_started`, `sale.out_for_delivery`, `sale.delivered`
  - `digital.delivered`, `digital.redeemed`, `digital.expiring_soon`
- [ ] Cross-type events: `booking.submitted`, `booking.modified`, `booking.confirmed`, `payment.captured`
- [ ] Templates seeded with EN + AR content
- [ ] Filament Resource: Notification Templates with translatable plugin

### Day 28
- [ ] Reviews module: service_reviews, vendor_reviews, review_responses
- [ ] `SubmitReviewAction`, `RespondToReviewAction`, `ModerateReviewAction`
- [ ] Aggregation triggers (update vendor average rating)
- [ ] Filament: Reviews Moderation page

### Day 29
- [ ] Loyalty module (per vendor): loyalty_programs, loyalty_rules, loyalty_ledger (append-only), loyalty_redemptions
- [ ] `CalculateLoyaltyPointsAction` (after booking completion)
- [ ] `RedeemLoyaltyPointsAction` (during booking)
- [ ] Filament: Loyalty Programs (per-vendor config)

### Day 30
- [ ] Marketing campaigns: Filament Campaign Builder
- [ ] Push/SMS/WA/email campaign dispatch via queue
- [ ] Test all channels in EN + AR

### End of W6 — Deliverables
- Notifications fire correctly per event × channel × locale
- Reviews end-to-end with admin moderation
- Loyalty points credited on completed bookings
- Marketing campaigns dispatchable from Filament

### W6 Cut-list (if behind)
- Defer Marketing Campaigns to Phase 1.5
- Defer review responses (vendor reply to review) to W7
- WhatsApp can be stubbed in Phase 1 — Mailchimp + email + push enough for soft launch

---

## Phase 6 — Reporting + Excel Import Replication + Hardening Setup (Week 7)

**Goal:** Reports work. Excel imports cover all three types. Audit log is queryable. Phase 1 features are functionally complete.

### Day 31
- [ ] Reporting module: read models / aggregate views
- [ ] `BookingsByTypeReport`, `RevenueByPeriodReport`, `TopVendorsReport`, `InventoryUtilizationReport` (rental), `RedemptionRateReport` (digital)
- [ ] Filament Reports & Dashboards page with per-type breakdown widgets

### Day 32
- [ ] Sale Excel importer: `SaleServicesImport` + Action + Filament UI
- [ ] Digital Excel importer: `DigitalServicesImport` + Action + Filament UI
- [ ] Conditional validation per type (e.g., `lead_time_hours` required when `is_made_to_order = true`)

### Day 33
- [ ] Audit log infrastructure (spatie/laravel-activitylog)
- [ ] Audit Log Viewer in Filament with filtering by entity, user, date range
- [ ] CMS pages module: terms, privacy, about, contact (translatable)
- [ ] CMS Filament Resource

### Day 34
- [ ] Performance pass:
  - N+1 audit (use Laravel Debugbar locally)
  - Add missing indexes per slow query log
  - Eager-load on heavy queries
- [ ] Cache configuration: Redis for sessions, queues, cache, model caching where useful

### Day 35
- [ ] 2FA for admin: `TwoFactorAuthAction` (TOTP via Google Authenticator)
- [ ] Test idempotency under concurrent load (basic)

### End of W7 — Deliverables
- All Phase 1 features functionally complete
- All three product type Excel templates working
- Reports rendering with per-type breakdowns
- Audit log queryable

### W7 Cut-list (if behind)
- Defer 2FA to Phase 1.5 (use strong passwords + IP allowlist for admin)
- Defer Inventory Utilization report (Phase 1.5)
- Skip caching layer (revisit when load testing reveals need)

---

## Phase 7 — Hardening + Staging Deploy (Week 8)

**Goal:** Production-ready Phase 1 backend + admin. Deployed to staging. Smoke tests pass. Ready for soft launch with first vendors.

### Day 36
- [ ] Security audit:
  - Rate limiting on auth, payments, search (Laravel built-in)
  - CORS lockdown
  - CSRF on stateful routes
  - SQL injection check (review all `whereRaw` and `DB::raw`)
  - Mass assignment audit
- [ ] Backup configured (spatie/laravel-backup → separate S3 bucket)

### Day 37
- [ ] Test coverage gap analysis:
  - Pest run with `--coverage` flag
  - Target: 70%+ on Action classes, 80%+ on Models, 60%+ overall
  - Fill critical gaps (auth, payments, three-product-types)

### Day 38
- [ ] Staging deploy via GitHub Actions:
  - Build Docker image, push to ghcr.io
  - SSH deploy to Hetzner staging
  - Caddy + automatic HTTPS
  - Database migration in deploy
  - Health check endpoint `/up`

### Day 39
- [ ] End-to-end smoke tests:
  - Vendor registers → admin approves per type → vendor creates service per type → customer books → vendor accepts/modifies → customer pays → admin sees commission → vendor withdraws
  - Run for all three product types
  - Run in EN and AR locales

### Day 40
- [ ] Documentation pass:
  - README.md (project setup)
  - API documentation (Scribe or stubbed OpenAPI)
  - Filament admin user guide for the eventual ops team
  - Runbook (deploy, rollback, common ops)
- [ ] **Phase 1 retrospective:** what worked, what didn't, what to fix in Phase 1.5

### End of W8 — Deliverables
- Phase 1 backend + admin in production-ready state on staging
- Smoke tests passing for all 3 product types in EN + AR
- Backup running daily
- Documentation in place
- **Ready for first 5 vendors to onboard for soft launch**

### W8 Cut-list (if behind)
- Defer 80% test coverage target to Phase 1.5 (60% is acceptable for soft launch with monitoring)
- Defer Scribe/OpenAPI docs (just keep an internal Postman collection)

---

## After Phase 1 — what comes next

These are NOT Phase 1, but you should know they exist so you don't accidentally over-build:

### Phase 1.5 (mobile-and-web phase, ~8-12 weeks)
- Customer Flutter app (browse, book, pay, chat, reviews)
- Vendor Flutter app (manage services, accept bookings, wallet, withdrawals)
- Customer Next.js web (catalog browse, booking, account)
- Mobile push setup (FCM credentials, deep linking)

### Phase 2 (after Phase 1 + 1.5 stable, ~12+ weeks)
- Vendor subscription tiers (silver/gold/bronze)
- Platform-owned package products
- Dispute resolution engine
- Per-category card layout templates
- Vendor page slider
- Vendor QR / barcode catalog
- Advanced tax invoicing
- Multi-currency activation (schema is ready; just enable conversion)
- GCC gateway expansion (Tabby, Tamara, HyperPay)

---

## Daily discipline (for the 8 weeks)

These habits are what make 8 weeks possible. Skip them and you'll need 12.

1. **One module per day max** — avoid context-switching
2. **Tests with the code, not after** — Pest while modules are fresh
3. **Commit at end of every day** — even WIP
4. **End-of-week deploy to staging** — catch deployment issues weekly, not at the end
5. **Run `php artisan pint` and `phpstan` daily** — auto-runs via the post-edit-pint hook anyway
6. **Spec-guard hook warnings → fix within the same session**
7. **No new packages mid-week without entry in `10_Package_List.md`**
8. **Friday afternoon: 1-hour review of the week's commits + plan Monday**

If you hit a wall, **ask Claude Code to do `/scope-audit <feature>` before adding anything new**. Discipline > velocity.
