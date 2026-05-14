# Vendor Portal — Implementation Plan
> **Lead Engineer:** Claude Code  
> **Plan version:** 1.0  
> **Date:** 2026-05-13  
> **Source spec:** `docs/specs/12 vendor portal plan.MD` v1.2  
> **Status:** PLAN MODE — awaiting roadmap approval before any further implementation

---

## 1. Files Read (Pre-Plan Requirement)

1. ✅ `CLAUDE.md` — master engineering guide, coding conventions, module layout
2. ✅ `docs/specs/11_DB_Schema.md` — locked 60-table schema, all relationships and constraints
3. ✅ `docs/specs/12 vendor portal plan.MD` v1.2 — full vendor portal scope, phases, cut-list, API reference

---

## 2. Feature Breakdown

### 2.1 Filament Vendor Portal (/vendor panel) — IMPLEMENTATION STATUS

| Phase | Feature | File(s) | Status |
|---|---|---|---|
| 0.0 | VendorPanelProvider scaffold | `VendorPanelProvider.php` | ✅ COMPLETED |
| 0.0 | CheckVendorSuspension middleware | `CheckVendorSuspension.php` | ✅ COMPLETED |
| 1.0 | Vendor registration page | `RegisterVendorPage.php` | ✅ COMPLETED |
| 1.0 | Account suspended page | `AccountSuspendedPage.php` | ✅ COMPLETED |
| 1.0 | VendorAccountPage (password/email/phone) | `VendorAccountPage.php` | ✅ COMPLETED |
| 1.0 | ImpersonationBannerWidget | `ImpersonationBannerWidget.php` | ✅ COMPLETED |
| 1.1 | VendorProfilePage | `VendorProfilePage.php` | ✅ COMPLETED |
| 1.1 | VendorDocumentsPage | `VendorDocumentsPage.php` | ✅ COMPLETED |
| 1.1 | VendorApprovalStatusPage | `VendorApprovalStatusPage.php` | ✅ COMPLETED |
| 2.0 | VendorBusinessHoursPage | `VendorBusinessHoursPage.php` | ✅ COMPLETED |
| 2.0 | VendorCoverageAreasPage | `VendorCoverageAreasPage.php` | ✅ COMPLETED |
| 2.0 | VendorCategoriesPage | `VendorCategoriesPage.php` | ✅ COMPLETED |
| 2.1 | VendorServicesListPage (all 3 types) | `VendorServicesListPage.php` | ✅ COMPLETED |
| 2.1 | CloneServiceAction | `CloneServiceAction.php` | ✅ COMPLETED |
| 2.1 | VendorRentalServiceResource + pages | `VendorRentalServiceResource.php` + 3 pages | ✅ COMPLETED |
| 2.1 | VendorServiceAvailabilityPage | `VendorServiceAvailabilityPage.php` | ✅ COMPLETED |
| 2.2 | VendorSaleServiceResource + pages | `VendorSaleServiceResource.php` + 3 pages | ✅ COMPLETED |
| 2.3 | VendorDigitalServiceResource + pages | `VendorDigitalServiceResource.php` + 3 pages | ✅ COMPLETED |
| 2.4 | VendorImportRentalServicesPage | `VendorImportRentalServicesPage.php` | ✅ COMPLETED |
| 6.1 | VendorImportSaleServicesPage | `VendorImportSaleServicesPage.php` | ✅ COMPLETED |
| 6.1 | VendorImportDigitalServicesPage | `VendorImportDigitalServicesPage.php` | ✅ COMPLETED |
| 3.1 | VendorIncomingBookingsPage (accept/reject) | `VendorIncomingBookingsPage.php` | ✅ COMPLETED |
| 3.2 | VendorActiveBookingsPage (11 transitions) | `VendorActiveBookingsPage.php` | ✅ COMPLETED |
| 3.2 | MarkBookingItemStateAction | `MarkBookingItemStateAction.php` | ✅ COMPLETED |
| 3.2 | TeardownState (rental) / OutForDeliveryState (sale) | state files | ✅ COMPLETED |
| 4.2 | VendorWalletPage | `VendorWalletPage.php` | ✅ COMPLETED |
| 4.2 | VendorBookingDetailPage (commission breakdown) | `VendorBookingDetailPage.php` | ✅ COMPLETED |
| 4.2 | VendorBookingPaymentsPage (payment history) | `VendorBookingPaymentsPage.php` | ✅ COMPLETED |
| 4.2 | GetBookingCommissionBreakdownAction | `GetBookingCommissionBreakdownAction.php` | ✅ COMPLETED |
| 4.2 | ListBookingPaymentsAction | `ListBookingPaymentsAction.php` | ✅ COMPLETED |
| 5.0 | VendorNotificationPreferencesPage | `VendorNotificationPreferencesPage.php` | ✅ COMPLETED |
| 5.1 | VendorReviewsPage + RespondToReviewAction | `VendorReviewsPage.php` | ✅ COMPLETED |
| 5.2 | VendorLoyaltyProgramPage | `VendorLoyaltyProgramPage.php` | ✅ COMPLETED |
| 6.0 | VendorDashboardPage | `VendorDashboardPage.php` | ✅ COMPLETED |
| 6.0 | VendorStatsOverviewWidget (Redis-cached) | `VendorStatsOverviewWidget.php` | ✅ COMPLETED |

**Filament implementation: 100% complete (37 files, all phases covered).**

### 2.2 Quality Gate Status

| Gate | Status | Notes |
|---|---|---|
| Backend works | ✅ Implemented | All action classes and Filament pages created |
| Frontend works | ⚠️ Untested | Browser testing not yet performed |
| DB integration works | ⚠️ Partial tests | Action layer has some coverage; vendor portal-specific tests missing |
| Lint (Pint) | ✅ PASSED | 25 files fixed; `pint --test` now clean |
| Typecheck (PHPStan) | ⛔ BLOCKED | PHP 8.2 installed; Symfony 7 deps require PHP ≥ 8.4; `vendor/phpstan/phpstan` is stub-only — no binary; CI (PHP 8.4 container) needed |
| Playwright passes | ❌ NOT SET UP | No Playwright installed; no e2e tests |
| No console errors | ❌ Not checked | Browser not started |

---

## 3. Architecture Analysis

### 3.1 Stack (per CLAUDE.md — LOCKED)

- **Backend:** Laravel 12, PHP 8.3+, modular monolith (`app/Modules/{Name}/`)
- **Vendor Portal:** Filament v3 at `/vendor` via `VendorPanelProvider`
- **DB:** MySQL 8 / MariaDB 11, `utf8mb4_unicode_ci`
- **Cache:** Redis (5-min TTL on dashboard widgets)
- **Auth:** `web` guard + `role:vendor` + `verified` + `check.vendor.suspension`
- **i18n:** `spatie/laravel-translatable`, EN+AR everywhere
- **Money:** `Brick\Money`, integer minor units — never floats
- **Three product types:** `rental`, `sale`, `digital` — per-type Resources, Actions, state machines
- **Playwright:** Node.js tool, to be added alongside PHP project

### 3.2 Three-Layer Authorization (per spec §2.3)

Every vendor resource is protected by:
1. **Middleware layer:** `role:vendor` + `verified` + `check.vendor.suspension`
2. **Policy layer:** ownership check (vendor_profile_id === auth user's vendor profile)
3. **Query scope layer:** `getEloquentQuery()` filtered to `vendor_profile_id`

### 3.3 Module Boundaries

- Vendor Filament files live in `app/Modules/*/Filament/Vendor/`
- Auto-discovered via `VendorPanelProvider::discoverPages/Resources/Widgets`
- Cross-module access: only through Action classes and domain contracts, never direct model imports

### 3.4 Data Flow

```
Vendor UI (Filament) → Action class → DB write + domain event
                    ↕ (same Action)
API endpoint (Phase 2 Flutter) → same Action class
```

---

## 4. Backend Requirements

### 4.1 Remaining Pest Tests (missing from codebase)

| Test file | What it covers | Priority |
|---|---|---|
| `tests/Feature/Modules/Catalog/CloneServiceTest.php` | Clone ownership, naming (en/ar copy suffix), media copy, no reviews/availability copied, 403 on wrong vendor, type permission check | HIGH |
| `tests/Feature/Modules/Catalog/VendorServicesListTest.php` | List shows all 3 types, filter chips, leakage (vendor A can't see vendor B), bulk submit-for-review | HIGH |
| `tests/Feature/Modules/Booking/VendorBookingDetailTest.php` | Commission breakdown values correct, 403 on wrong vendor URL manipulation | HIGH |
| `tests/Feature/Modules/Booking/VendorBookingPaymentsTest.php` | Payment list for own booking, refund shown negative, 403 on wrong vendor | HIGH |
| `tests/Feature/Modules/Booking/FulfillmentStateTransitionTest.php` | All 11 transitions (rental/sale/digital), invalid prior state rejected (409), state logged in booking_state_transitions | HIGH |
| `tests/Feature/Modules/Settlement/VendorWalletTest.php` | Balance = sum of ledger, available = balance − pending, can't withdraw more than available, can't have 2 pending | MEDIUM |
| `tests/Feature/Modules/Reviews/RespondToReviewVendorTest.php` | Response goes to pending_moderation, can't edit once submitted, can't see other vendor's reviews | MEDIUM |
| `tests/Feature/Modules/Loyalty/VendorLoyaltyIsolationTest.php` | Config isolated per vendor, deactivation preserves balances | MEDIUM |
| `tests/Feature/Modules/Shared/VendorDashboardMetricsTest.php` | Stats accurate per vendor, vendor can't see another's metrics, Redis cache invalidation | LOW |

### 4.2 Lint + Typecheck

- Run `./vendor/bin/pint` on all new files (37 Filament files + 7 Action files)
- Run `./vendor/bin/phpstan analyse app/Modules/*/Filament/Vendor/ app/Modules/*/Application/Actions/` at level 5+

---

## 5. Frontend Requirements

### 5.1 Playwright E2E Test Coverage Required

The vendor portal is a **server-rendered Filament v3 UI** (Livewire-based, not a SPA). Playwright interacts with the rendered HTML at `http://localhost:8000/vendor`.

**Auth flows:**
- Vendor registration (form + email verification redirect)
- Login with valid credentials → dashboard
- Login with wrong password → error message
- Suspended vendor → suspension page
- Logout

**CRUD flows:**
- Create rental service (form with EN/AR tabs, media upload)
- Edit rental service (mutateFormDataBeforeFill hydration)
- Archive service (confirm modal)
- Clone service (clone button → new draft, name has "(Copy)")
- Block availability dates (date range form)

**Booking flows:**
- Accept incoming booking (confirm modal)
- Reject incoming booking (reason form)
- Fulfill rental through all 5 states
- Fulfill sale through all 4 states
- View booking detail (commission breakdown visible)
- View payment history (payments table)

**Filter/search:**
- Filter services by type (rental/sale/digital chips)
- Filter services by status
- Search bookings by reference

**Form validation:**
- Service creation with missing required fields → inline errors
- Withdrawal amount > available balance → error
- Review response empty → error

**Responsive (mobile 375px viewport):**
- Dashboard stats wrap correctly
- Table horizontal scroll on mobile
- Form inputs not clipped

**Loading/error states:**
- Widget loading skeleton
- Form submission loading state (button disabled)
- Network failure on save → notification error

### 5.2 Accessibility

- All form labels properly associated (`for`/`id`)
- Focus management after modal open/close
- RTL layout (Arabic) renders correctly (test at `?locale=ar`)

---

## 6. DB Impact Analysis

### 6.1 Schema Changes Required

**None.** The vendor portal is purely a UI + business logic layer on top of the existing locked 60-table schema. All tables referenced:

| Tables used | Module | Access type |
|---|---|---|
| `services`, `service_*_details`, `service_availability_blocks` | Catalog | Read + Write (via Actions) |
| `bookings`, `booking_vendors`, `booking_items`, `booking_state_transitions` | Booking | Read + Write (via Actions) |
| `payments` | Payments | Read only (vendor cannot initiate payments) |
| `commissions`, `wallet_ledger`, `wallets`, `withdrawals` | Settlement | Read + conditional write (withdrawal request) |
| `service_reviews`, `vendor_reviews`, `review_responses` | Reviews | Read + Write (respond action) |
| `loyalty_programs`, `loyalty_rules` | Loyalty | Read + Write (config) |
| `notification_preferences` | Communication | Read + Write |
| `vendor_profiles`, `vendor_documents`, `vendor_business_hours`, `vendor_coverage_areas` | Identity | Read + Write |
| `categories`, `occasions`, `cities` | Catalog / Geography | Read only |

### 6.2 No Migration Required

All schema was created in earlier phases. Vendor portal adds no new tables.

---

## 7. Dependencies

### 7.1 PHP Dependencies (all already in composer.json)

- `filament/filament` v3 — UI framework
- `filament/spatie-laravel-translatable-plugin` — EN/AR tabs
- `filament/spatie-laravel-media-library-plugin` — media upload
- `bezhansalleh/filament-shield` — permissions
- `spatie/laravel-permission` — role:vendor gate
- `spatie/laravel-translatable` — translatable fields
- `spatie/laravel-medialibrary` — S3 media
- `spatie/laravel-model-states` — state machines
- `brick/money` — money formatting
- `maatwebsite/excel` — Excel imports

### 7.2 Node.js Dependencies (MISSING — to be added)

```json
{
  "@playwright/test": "^1.48.0"
}
```

- `@playwright/test` — E2E test framework
- Browsers: chromium (desktop), chromium mobile (375px)

### 7.3 External Services (dev environment needed)

- **MySQL** — database
- **Redis** — cache (dashboard widgets)
- **MinIO** — S3-compatible media storage
- **Meilisearch** — not needed for vendor portal tests (mock Scout)

---

## 8. Edge Cases

### 8.1 Authorization Edge Cases

- Vendor A manipulates URL to access Vendor B's booking detail → must get 403
- Vendor with revoked type permission tries to clone a service of that type → 403
- Vendor with `approval_status=suspended` loads any page → redirected to suspension page by middleware
- Unverified vendor tries to access dashboard → Filament email verification redirect
- Admin impersonating vendor: all actions double-logged; impersonation banner visible

### 8.2 Data Integrity Edge Cases

- Clone action: source service has no detail record (null) → clone gracefully skips detail
- Clone action: media copy fails (S3 unreachable) → clone succeeds, media skipped (non-fatal)
- Wallet withdrawal: race condition two simultaneous requests → one succeeds, second gets "already pending" error
- Fulfillment transition: two concurrent requests for same item → second rejected with 409
- Commission breakdown: booking vendor has no commissions yet (booking accepted, not paid) → breakdown shows zeros gracefully
- Payment history: booking has no payments yet → empty table with appropriate empty state message
- Dashboard widget: vendor has no wallet yet → wallet balance shows EGP 0.00, no null pointer

### 8.3 Bilingual Edge Cases

- Arabic name `name_ar` empty on service create → validation error (both required)
- Filament locale switcher to Arabic → all labels translate, form direction RTL
- `name_snapshot` in booking item (stored at booking time) has only `en` key → graceful fallback

### 8.4 State Machine Edge Cases

- Rental: vendor tries to skip `TeardownState` and go straight to `PickedUpState` → rejected (invalid transition)
- Sale: vendor tries to mark `OutForDelivery` from `InPreparation` (skipping `ReadyState`) → rejected
- Digital: marking `Redeemed` when already `Redeemed` → rejected (no self-loops)

---

## 9. Testing Strategy

### 9.1 Pest Unit / Feature Tests (PHP)

**Structure:**
```
tests/
├── Feature/
│   └── Modules/
│       ├── Catalog/
│       │   ├── CloneServiceTest.php           # TODO
│       │   └── VendorServicesListTest.php     # TODO
│       ├── Booking/
│       │   ├── VendorBookingDetailTest.php    # TODO
│       │   ├── VendorBookingPaymentsTest.php  # TODO
│       │   └── FulfillmentStateTransitionTest.php # TODO
│       ├── Settlement/
│       │   └── VendorWalletTest.php           # TODO
│       ├── Reviews/
│       │   └── RespondToReviewVendorTest.php  # TODO
│       ├── Loyalty/
│       │   └── VendorLoyaltyIsolationTest.php # TODO
│       └── Shared/
│           └── VendorDashboardMetricsTest.php # TODO
```

**Required per test:**
- Happy path
- Auth (unauthenticated → 401/403)
- Authorization (wrong vendor → 403)
- Validation (each required field, each business rule)
- Leakage (vendor A cannot see vendor B's data by URL manipulation)
- All three product types for type-aware features

### 9.2 E2E Tests (Playwright)

**Structure:**
```
tests/e2e/
├── playwright.config.ts
├── auth/
│   ├── registration.spec.ts
│   ├── login.spec.ts
│   └── logout.spec.ts
├── profile/
│   ├── vendor-profile.spec.ts
│   └── vendor-documents.spec.ts
├── services/
│   ├── rental-service-crud.spec.ts
│   ├── sale-service-crud.spec.ts
│   ├── digital-service-crud.spec.ts
│   ├── service-clone.spec.ts
│   └── service-availability.spec.ts
├── bookings/
│   ├── incoming-bookings.spec.ts
│   ├── active-bookings.spec.ts
│   ├── booking-detail.spec.ts
│   └── booking-payments.spec.ts
├── wallet/
│   └── vendor-wallet.spec.ts
├── reviews/
│   └── vendor-reviews.spec.ts
├── loyalty/
│   └── vendor-loyalty.spec.ts
└── dashboard/
    └── vendor-dashboard.spec.ts
```

---

## 10. Playwright Strategy

### 10.1 Installation Plan

Since this is a Laravel project with no existing Playwright setup, install as a dev dependency:

```bash
npm install --save-dev @playwright/test
npx playwright install chromium
```

### 10.2 Configuration (`playwright.config.ts`)

Key settings required by /goal:
- **Screenshots on failure:** `screenshot: 'only-on-failure'`
- **HTML reports:** `reporter: [['html', { outputFolder: 'playwright-report' }]]`
- **Retries:** `retries: 2` (CI: 2, local: 0)
- **Mobile testing:** `devices['Pixel 5']` project (375px viewport)
- **Base URL:** `http://localhost:8000`
- **Auth state reuse:** stored in `tests/e2e/.auth/vendor.json` (one-time login)

### 10.3 Projects (browsers)

```
projects:
  - name: 'Desktop Chrome'     (1280x720)
  - name: 'Mobile Chrome'      (Pixel 5, 375px)
  - name: 'Desktop RTL Arabic' (1280x720, locale=ar)
```

### 10.4 Global Setup

- `globalSetup`: seeds a test vendor user + approved profile in SQLite test DB
- `storageState`: saves vendor auth cookie after login, reused per spec file

### 10.5 Test Categories (per /goal requirements)

| Category | Spec file(s) | Key assertions |
|---|---|---|
| Auth flows | `auth/*.spec.ts` | Login success, login failure, suspension redirect, logout |
| Forms | `services/*.spec.ts`, `profile/*.spec.ts` | Required field errors, bilingual tabs, save success notification |
| CRUD | `services/*.spec.ts` | Create → list shows new row, edit → saved, archive → status badge changes |
| Filters | `services/rental-service-crud.spec.ts` | Type filter chips, status filter |
| Modals | `bookings/incoming-bookings.spec.ts` | Accept confirm modal, reject modal with form |
| Responsive | All `*.spec.ts` via Mobile Chrome project | No overflow, table scroll, form not clipped |
| Validation | All form specs | Empty required fields, business rule violations |
| Failure states | `wallet/vendor-wallet.spec.ts` | Overdraft attempt shows error notification |
| Loading states | `services/*.spec.ts` | Save button disabled during submit |

---

## 11. Phased Roadmap

### Phase A — Lint + Typecheck (Quality Baseline)
**Goal:** All existing files pass lint and static analysis.

| Task | Status | Notes |
|---|---|---|
| A1: Run `./vendor/bin/pint` on vendor portal files | ✅ COMPLETED | 25 files fixed; pint --test passes clean |
| A2: Run `./vendor/bin/phpstan analyse` at level 8 | ⛔ BLOCKED | No phpstan binary (stub-only install); PHP 8.2 installed, Symfony 7 requires PHP ≥ 8.4. Filament files already excluded in phpstan.neon. New Action classes (GetBookingCommissionBreakdownAction, ListBookingPaymentsAction) need CI with PHP 8.4 |

### Phase B — Pest Tests (Backend Quality Gates)
**Goal:** Every vendor portal feature has Pest coverage including leakage tests.

| Task | Status | Notes |
|---|---|---|
| B1: `CloneServiceTest.php` | ✅ COMPLETED | 10 tests: 3 types, name suffix, ownership, type permission, no availability copy |
| B2: `VendorServicesListTest.php` | ✅ COMPLETED | 11 tests: all 3 types, type/status filter, leakage, bulk submit-for-review; also fixed `SubmitServiceForReviewAction` event bug |
| B3: `VendorBookingDetailTest.php` | ✅ COMPLETED | 5 tests: commission values, delivery_fee, 403 ownership |
| B4: `VendorBookingPaymentsTest.php` | ✅ COMPLETED | 5 tests: list, ordering, empty, refunds, 403 |
| B5: `FulfillmentStateTransitionTest.php` | ✅ COMPLETED | 15 tests: all 11 transitions, invalid 409, log audit, 403; also fixed `resolveItemState()` bug |
| B6: `VendorWalletTest.php` | ✅ COMPLETED | 7 tests: balance = sum of credits, debit decrements, available = balance − pending, zero available when fully reserved, cross-vendor isolation |
| B7: `RespondToReviewVendorTest.php` | ✅ COMPLETED | 6 tests: pending status, empty body, cross-vendor isolation, update resets to pending |
| B8: `VendorLoyaltyIsolationTest.php` | ✅ COMPLETED | 5 tests: two vendors independent, deactivate A doesn't affect B, balance isolated per vendor, deactivation preserves ledger |
| B9: `VendorDashboardMetricsTest.php` | ✅ COMPLETED | 6 tests: pending services/bookings per vendor, cache key per vendor, no cache bleed, forget invalidates |

### Phase C — Playwright Setup
**Goal:** Playwright installed, configured, and base infrastructure ready.

| Task | Status | Notes |
|---|---|---|
| C1: `npm install --save-dev @playwright/test` | TODO | Add to package.json |
| C2: `npx playwright install chromium` | TODO | Download browser binaries |
| C3: Create `playwright.config.ts` | TODO | Screenshots, HTML reports, retries, mobile |
| C4: Create `tests/e2e/` directory structure | TODO | Auth, services, bookings, wallet, etc. |
| C5: Create global setup (seed test vendor) | TODO | `tests/e2e/setup/global-setup.ts` |
| C6: Create auth fixture (login + save state) | TODO | `tests/e2e/auth/vendor-auth.setup.ts` |

### Phase D — Playwright E2E Tests (UI Quality Gates)
**Goal:** All user-facing flows tested in browser.

| Task | Status | Notes |
|---|---|---|
| D1: Auth flow tests | TODO | Register, login, logout, suspension |
| D2: Profile + documents tests | TODO | CRUD profile, document upload |
| D3: Rental service CRUD + clone | TODO | Create, edit, archive, clone |
| D4: Sale + Digital service CRUD | TODO | Same pattern |
| D5: Service availability tests | TODO | Block dates, remove block |
| D6: Incoming bookings tests | TODO | Accept modal, reject form |
| D7: Active bookings + fulfillment | TODO | State transitions, all 3 types |
| D8: Booking detail + commission | TODO | Commission breakdown renders |
| D9: Payment history | TODO | Table, refund negative |
| D10: Wallet + withdrawal | TODO | Balance, request form, overdraft error |
| D11: Reviews + response | TODO | Respond action |
| D12: Loyalty config | TODO | Form save |
| D13: Dashboard widgets | TODO | Stats render, Redis-cached |
| D14: Mobile responsive (375px) | TODO | All pages, no overflow |
| D15: RTL Arabic layout | TODO | Labels, form direction |

### Phase E — Final Checklist + Documentation
**Goal:** All quality gates green, summary written.

| Task | Status | Notes |
|---|---|---|
| E1: All Pest tests pass | TODO | `./vendor/bin/pest` |
| E2: All Playwright tests pass | TODO | `npx playwright test` |
| E3: Pint passes (zero violations) | TODO | `./vendor/bin/pint --test` |
| E4: PHPStan passes (zero errors) | TODO | `./vendor/bin/phpstan analyse` |
| E5: Generate final summary | TODO | Checklist, architecture summary, test coverage |

---

## 12. Task Tracker

### Current Sprint

| ID | Task | Status | Blocker |
|---|---|---|---|
| A1 | Pint lint | ✅ COMPLETED | — |
| A2 | PHPStan typecheck | ⛔ BLOCKED | PHP 8.2 local; phpstan needs PHP ≥ 8.4 (Symfony 7) |
| A1 | Pint lint | ✅ COMPLETED | — |
| A2 | PHPStan typecheck | ⛔ BLOCKED | PHP 8.2 local; phpstan needs PHP ≥ 8.4 (Symfony 7) |
| B1 | CloneServiceTest | ✅ COMPLETED | 10 tests pass; fixed `blocked_date` → `starts_at/ends_at` |
| B2 | VendorServicesListTest | TODO | — |
| B3 | VendorBookingDetailTest | ✅ COMPLETED | 5 tests pass |
| B4 | VendorBookingPaymentsTest | ✅ COMPLETED | 5 tests pass |
| B5 | FulfillmentStateTransitionTest | ✅ COMPLETED | 15 tests pass; fixed `resolveItemState()` bug (abstract class + missing field) |
| B6 | VendorWalletTest | TODO | — |
| B7 | RespondToReviewVendorTest | TODO | — |
| B8 | VendorLoyaltyIsolationTest | TODO | — |
| B9 | VendorDashboardMetricsTest | TODO | — |
| C1–C6 | Playwright setup | TODO | Requires npm |
| D1–D15 | Playwright E2E tests | TODO | C1–C6 must complete first |
| E1–E5 | Final checklist | TODO | All above |

---

## 13. Deployment / Run Commands

```bash
# PHP quality checks
./vendor/bin/pint                                  # Fix code style
./vendor/bin/pint --test                           # Check without fixing (CI)
./vendor/bin/phpstan analyse --level=5             # Static analysis

# Pest tests
./vendor/bin/pest                                  # All tests
./vendor/bin/pest --filter=CloneService            # Single test
./vendor/bin/pest --group=vendor-portal            # Group (once tagged)
./vendor/bin/pest --parallel                       # Parallel (faster)

# Playwright install (one-time)
npm install --save-dev @playwright/test
npx playwright install chromium

# Playwright tests
npx playwright test                                # All E2E tests
npx playwright test --project="Desktop Chrome"    # Desktop only
npx playwright test --project="Mobile Chrome"     # Mobile only
npx playwright test auth/                          # Auth tests only
npx playwright test --debug                        # Debug mode (headed)
npx playwright show-report                         # Open HTML report

# Dev server (required for Playwright)
php artisan serve                                  # Start at :8000
php artisan queue:work                             # Process queued jobs
php artisan reverb:start                           # Real-time events

# Full test suite (CI-equivalent)
./vendor/bin/pint --test && \
./vendor/bin/phpstan analyse --level=5 && \
./vendor/bin/pest --parallel && \
npx playwright test --reporter=html
```

---

## 14. Approval Gate

**This plan is complete. No implementation has been written since this plan was generated.**

**Awaiting approval to begin Phase A (Lint + Typecheck).**

Once approved, implementation will proceed ONE task at a time in order: A1 → A2 → B1 → B2 ... → E5.

Each task will be reported with:
- Summary of changes
- Modified files list
- Test coverage explanation

Before marking any task COMPLETED, all quality gates for that task must pass.
