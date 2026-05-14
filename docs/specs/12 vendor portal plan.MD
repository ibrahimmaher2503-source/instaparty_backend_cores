# InstaParty — Vendor Portal Plan (Filament v3) — Final v1.2

> **Status:** Locked addition to Phase 1 plan
> **Owner:** Ibrahim
> **Last updated:** 2026-04-27
>
> **What changed in v1.2:**
> - 5 new vendor features added (gaps from v1.1 review):
>   - Vendor services list page (separate from create form)
>   - Service clone/duplicate action
>   - Impersonation indicator banner
>   - Payment history per booking
>   - Commission breakdown per booking
> - Every feature now lists its **Action class + API endpoint** for Phase 2 mobile parity
> - New §12 "Action & API Reference" — single table of all vendor features with their Action class and REST endpoint
> - Effort updated: 9d → 10.5d (the 5 new features = ~1.5d total)
>
> **Source-of-truth hierarchy:**
> 1. `01_PRD.md` (FR-10 to FR-30, vendor-related)
> 2. `CLAUDE.md`
> 3. `02_Tech_Decisions.md` §13 (Filament v3)
> 4. `07_Vendor_Journey.md`
> 5. `11_DB_Schema.md`
> 6. `09_Phasing_Plan_v2.md` (parent phasing)
> 7. **This file**
>
> **Companion files (planned, not yet built):**
> - `13_Admin_Portal_Plan.md` — admin equivalents for vendor features (admin can act on vendor's behalf)
> - `14_API_Surface_Map.md` — full API endpoint inventory for Phase 1.5/2 mobile/web consumption
>
> **What this is NOT:**
> - NOT a Flutter Vendor app (Phase 2)
> - NOT a separate Laravel app (same codebase, separate Filament panel)
> - NOT a new module (uses existing Catalog, Booking, Settlement, Reviews)

---

## 1. Decision Summary

| Question | Decision |
|---|---|
| Where do vendors manage their business in Phase 1? | Filament v3 web portal at `/vendor` |
| Same panel as admin or separate? | **Separate panel** (`VendorPanelProvider`) |
| Mobile vendor app? | **Phase 2** |
| Resource discovery split? | `Modules/*/Filament/Admin/Resources/` vs `Modules/*/Filament/Vendor/Resources/` |
| Authentication | Same `users` table, `web` guard, `role:vendor` middleware |
| Email verification | Required (per Tech Decisions §3) |
| 2FA for vendors | Phase 1.5 |
| Vendor data scoping | Only their own — Policies + global query scopes + middleware |
| Every Filament action backed by | **One reusable Action class** that both Filament and API endpoints invoke |
| API endpoints for Phase 2 mobile | Documented per feature in §12; built in Phase 1.5 alongside Flutter app |

---

## 2. Architecture

### 2.1 Two-Panel Filament

```
Laravel app (single codebase)
├── /admin  ← AdminPanelProvider
│   └── auth: web guard + 'role:admin' middleware
│   └── resources: app/Modules/*/Filament/Admin/Resources/*
│
└── /vendor ← VendorPanelProvider
    └── auth: web guard + 'verified', 'role:vendor' middleware
    └── resources: app/Modules/*/Filament/Vendor/Resources/*
```

**Why separate panels (recap):**

| Aspect | Single panel | Two panels (chosen) |
|---|---|---|
| Authorization | Conditional UI per role — error-prone | Each panel sees only its own resources |
| Branding | One color scheme | Admin = blue; Vendor = amber |
| Security risk | Higher (one missed policy = leak) | Lower (defense in depth) |
| Filament v3 idiom | Possible but not the pattern | Officially supported |

### 2.2 Folder Structure

```
app/Modules/Catalog/
├── Domain/
│   ├── Models/
│   ├── Policies/
│   └── States/                              # spatie/laravel-model-states
├── Application/
│   └── Actions/                             # CALLED BY BOTH Filament AND API controllers
│       ├── CreateRentalServiceAction.php
│       ├── CloneServiceAction.php
│       └── ...
├── Infrastructure/
├── Http/
│   ├── Controllers/                         # API controllers — wrap Actions
│   │   ├── Vendor/
│   │   │   ├── VendorServiceController.php
│   │   │   └── ...
│   │   └── Admin/
│   ├── Requests/                            # Form Requests (SHARED between Filament and API)
│   │   ├── Vendor/
│   │   │   ├── CreateRentalServiceRequest.php
│   │   │   └── ...
│   │   └── Admin/
│   ├── Resources/                           # API Resources for response shaping
│   └── Routes/
│       ├── vendor.php                       # vendor API routes
│       └── admin.php
└── Filament/
    ├── Admin/Resources/                     # Admin Filament UI (calls Actions)
    └── Vendor/                              # Vendor Filament UI (calls SAME Actions)
        ├── Resources/
        ├── Pages/
        └── Widgets/

app/Providers/Filament/
├── AdminPanelProvider.php
└── VendorPanelProvider.php
```

**Key principle — single source of business logic:**

```
Vendor's Filament page ─┐
                        ├─→ Same Action class ─→ Same DB writes ─→ Same domain events
Vendor's API endpoint ──┘
```

This means:
- Bug in Action = fixes both UI and API simultaneously
- Test the Action once, both surfaces tested
- Phase 2 mobile app calls API endpoints that wrap the **same Actions** Filament uses today
- No "Filament-only logic" anywhere — that's an architectural violation

### 2.3 Policies & Query Scoping (Defense in Depth)

Three layers, all required:

**Layer 1: Middleware** — `role:vendor` blocks anyone without vendor role at the panel level.

**Layer 2: Policies** — every resource checks ownership:

```php
// app/Modules/Catalog/Domain/Policies/ServicePolicy.php
public function view(User $user, Service $service): bool
{
    if ($user->hasRole('admin')) return true;
    return $user->hasRole('vendor')
        && $service->vendor_id === $user->vendor_profile_id;
}

public function create(User $user, ProductType $type): bool
{
    return $user->hasPermissionTo("service.create.{$type->value}.own");
    // Per-type permission granted only after admin approval
}
```

**Layer 3: Global query scope** — vendor queries auto-filter:

```php
// VendorRentalServiceResource.php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('vendor_id', auth()->user()->vendor_profile_id);
}
```

**Test required:** Pest test asserting vendor cannot view another vendor's resource by URL manipulation.

### 2.4 Branding & Panel Config

```php
// VendorPanelProvider.php
->id('vendor')
->path('vendor')
->login()
->emailVerification()
->passwordReset()
->brandName('InstaParty Vendor')
->brandLogo(asset('images/vendor-logo.svg'))
->favicon(asset('images/vendor-favicon.ico'))
->colors(['primary' => Color::Amber])
->font('Cairo')
->discoverResources(in: app_path('Modules'), for: 'App\\Modules\\*\\Filament\\Vendor\\Resources')
->discoverPages(in: app_path('Modules'), for: 'App\\Modules\\*\\Filament\\Vendor\\Pages')
->discoverWidgets(in: app_path('Modules'), for: 'App\\Modules\\*\\Filament\\Vendor\\Widgets')
->authGuard('web')
->authMiddleware(['auth', 'verified', 'role:vendor', 'check.vendor.suspension'])  // NEW v1.2
->plugin(SpatieLaravelTranslatablePlugin::make()
    ->defaultLocales(['en', 'ar']))
```

**New middleware `check.vendor.suspension`:** redirects suspended vendors to suspension page on every request.

### 2.5 Locale Handling

- Vendor's `preferred_locale` (from `users` table) drives Filament UI direction
- RTL automatic for Arabic
- All translatable resource fields show EN+AR tabs
- Validation messages localized
- Translations stored under `lang/{locale}/vendor-portal.php`

### 2.6 Performance Constraints

- All vendor list pages use **eager-loading** for displayed relationships
- Filament `defaultPaginationPageOption(25)`
- Heavy dashboard widgets use **Redis cached queries** (TTL=5 min), invalidated on relevant domain events
- Phase 7.1 smoke test includes: vendor with 50 services + 100 bookings, dashboard <2s

### 2.7 Impersonation Transparency (NEW v1.2)

Admin can impersonate vendor for support (Phase 1.5 feature in admin plan), BUT:

- When admin is impersonating, vendor's UI shows a **persistent red banner**: "InstaParty Support is currently acting on your account. Started [time]. [End session]"
- Vendor can end impersonation session anytime via the banner
- Every impersonated action is double-logged: actor=admin_user_id, on_behalf_of=vendor_user_id
- All notifications during impersonation tagged "(via support)"

**Implementation:** Use `lab404/laravel-impersonate` package, with Filament integration. Vendor's panel reads `session('impersonator_id')` to show banner.

---

## 3. Vendor Portal Scope (Phase 1)

### 3.1 What vendors can do — full journey mapping

Direct mapping from `07_Vendor_Journey.md`:

| Journey Step | Vendor Portal Feature | Phase |
|---|---|---|
| 1-3 Registration | Public sign-up at `/vendor/register` + email verification | 1.0 |
| Account self-edit | `VendorAccountPage` (password, email, phone) | 1.0 |
| 4 Document upload | `VendorDocumentsPage` | 1.1 |
| Awaiting approval | `VendorApprovalStatusPage` (per-type status) | 1.1 |
| 5a Profile | `VendorProfilePage` | 1.1 |
| 5b Business hours | `VendorBusinessHoursPage` | 2.0 |
| 5c Coverage areas | `VendorCoverageAreasPage` | 2.0 |
| 5d-i Services list | **`VendorServicesListPage` (NEW v1.2)** | 2.1 |
| 5d-ii Services create/edit | Per-type service resources | 2.1, 2.2, 2.3 |
| 5d-iii Service clone | **`CloneServiceAction` (NEW v1.2)** | 2.1 |
| 5e Service availability | `VendorServiceAvailabilityPage` | 2.1 |
| Excel bulk upload | Per-type Excel imports | 2.4, 6.1 |
| Loyalty config | `VendorLoyaltyProgramPage` | 5.2 |
| 8 Receive reviews | `VendorReviewsPage` (with response) | 5.1 |
| 9-12 Booking decisions | `VendorIncomingBookingsPage` | 3.1, 3.2 |
| Restricted chat | Chat surface (wraps Firestore) | 3.2 |
| 13-17 Execution / fulfillment | `VendorActiveBookingsPage` (3 state machines) | 3.2 |
| **NEW: Payment history per booking** | **`VendorBookingPaymentsPage`** | 4.2 |
| **NEW: Commission breakdown per booking** | **Inline on booking detail page** | 4.2 |
| 18 Wallet management | `VendorWalletPage` | 4.2 |
| 19-21 Withdrawals | `VendorRequestWithdrawalAction` + history | 4.2 |
| Notifications | `VendorNotificationPreferencesPage` | 5.0 |
| Dashboard / metrics | `VendorDashboardPage` | 6.0 |
| **NEW: Impersonation banner** | Renders globally when admin impersonating | 1.0 |

### 3.2 What vendors CANNOT do (explicit)

- ❌ See other vendors' services, bookings, customers
- ❌ Modify their own `approval_status` (admin only)
- ❌ Modify their own `vendor_approved_product_types` (admin only)
- ❌ Modify commission rates (admin only — global)
- ❌ Approve/reject their own services (admin moderation)
- ❌ Access admin panel `/admin`
- ❌ Edit reviews left by customers (response only)
- ❌ Manipulate wallet ledger directly (read-only)
- ❌ Send platform-wide campaigns (admin only)
- ❌ See `app_settings`, `commission_rates`, `audit_logs`, `cms_pages` admin
- ❌ Force-cancel a confirmed/in-fulfillment booking (admin intervention required)
- ❌ Clone another vendor's service (clone restricted to own services)

### 3.3 Out of scope for vendor portal (Phase 1)

These exist but are admin-driven in Phase 1, vendor-self-service in Phase 2:

- Self-suspension / account closure
- Document re-upload after rejection (admin uploads on behalf — Phase 1)
- Direct vendor → customer messaging outside booking review window
- Vendor branded landing page
- Vendor QR / barcode catalog (Phase 2)
- Vendor page slider (Phase 2)
- Subscription tier self-upgrade (Phase 2)
- 2FA for vendors (Phase 1.5)
- Pricing tiers UI per service (single `base_price` in Phase 1)
- Custom delivery fee per booking
- Map preview for coverage areas (Phase 1.5)
- Dispute initiation by vendor (Phase 2 dispute engine)
- Vendor team accounts / multi-user (Phase 2)

---

## 4. Phase-by-Phase Additions

### 4.1 Phase 0.0 Addition — VendorPanelProvider Scaffold (1 hour)

**Goal:** `/vendor` route returns Filament login page.

**Tasks:**
- [ ] Create `app/Providers/Filament/VendorPanelProvider.php`
- [ ] Register in `config/app.php` providers
- [ ] Update `AdminPanelProvider.php` discovery path to `app/Modules/*/Filament/Admin/Resources`
- [ ] Empty folders: `app/Modules/{Identity,Catalog,Booking,Settlement,Communication,Reviews,Loyalty,Reporting}/Filament/Vendor/Resources/.gitkeep`
- [ ] Add `Routes/vendor.php` registration per module
- [ ] Configure email verification redirect to `/vendor/email/verify`
- [ ] Create `CheckVendorSuspension` middleware + register

**Exit:**
- ✅ `/admin` still works
- ✅ `/vendor` returns Filament login
- ✅ Non-vendor user attempt → "Forbidden"
- ✅ Unverified vendor → email verification screen

---

### 4.2 Phase 1.0 Addition — Vendor Auth + Dashboard + Account + Impersonation Banner (0.75 day)

**Goal:** Vendor can register, verify, log in, manage credentials. Impersonation banner renders when applicable.

**Actions used (Phase 2 API parity):**
- `RegisterVendorAction` → `POST /api/v1/vendor/register`
- `VerifyVendorEmailAction` → `POST /api/v1/vendor/email/verify`
- `RequestPasswordResetAction` → `POST /api/v1/vendor/password/reset-link`
- `ResetPasswordAction` → `POST /api/v1/vendor/password/reset`
- `LoginAction` → `POST /api/v1/vendor/login`
- `LogoutAction` → `POST /api/v1/vendor/logout`
- `ChangeVendorPasswordAction` → `PATCH /api/v1/vendor/account/password`
- `UpdateVendorEmailAction` → `PATCH /api/v1/vendor/account/email`
- `UpdateVendorPhoneAction` → `PATCH /api/v1/vendor/account/phone`

**Tasks:**
- [ ] Vendor registration at `/vendor/register` (public)
- [ ] Email verification flow
- [ ] Password reset at `/vendor/password/reset`
- [ ] `VendorDashboardPage` skeleton
- [ ] `VendorAccountPage`:
  - Change password
  - Update email (re-verification triggered)
  - Update phone (OTP — stubbed Phase 1.0, real Phase 5.0)
  - Account info (creation date, last login)
- [ ] **NEW v1.2: Impersonation banner widget** (renders globally if `session('impersonator_id')` set):
  - Red banner top of every vendor page
  - Shows "Support session active" + admin name + start time
  - "End session" button calls `EndImpersonationAction`
- [ ] Logout button

**Tests:**
- [ ] Pest: register → `vendor_profile` created with `approval_status=pending`, `email_verified_at=null`
- [ ] Pest: unverified vendor blocked from dashboard
- [ ] Pest: verified vendor reaches dashboard
- [ ] Pest: vendor cannot access `/admin/login`
- [ ] Pest: admin cannot access `/vendor/login`
- [ ] Pest: password change requires current password
- [ ] Pest: email update triggers re-verification
- [ ] Pest: impersonation banner shows when session has `impersonator_id`
- [ ] Pest: ending impersonation redirects to admin panel

---

### 4.3 Phase 1.1 Addition — Profile + Documents + Approval Status (0.5 day)

**Actions used:**
- `UpdateVendorProfileAction` → `PATCH /api/v1/vendor/profile`
- `UploadVendorDocumentAction` → `POST /api/v1/vendor/documents`
- `DeleteVendorDocumentAction` → `DELETE /api/v1/vendor/documents/{public_id}` (only if status=rejected)
- `RequestApprovalForTypeAction` → `POST /api/v1/vendor/approval-requests`
- `GetVendorApprovalStatusAction` → `GET /api/v1/vendor/approval-status`

**Tasks:**
- [ ] `VendorProfilePage` — business name, bio (translatable), address, bank info (IBAN validated)
- [ ] `VendorDocumentsPage` — upload CR, tax card, IBAN proof to S3 private; re-upload disabled while `status=pending`
- [ ] `VendorApprovalStatusPage` — overall + per-type status, "Request approval" button per type, rejection reasons visible
- [ ] Suspended state UX (all routes redirect to suspension page via middleware)

**Tests:**
- [ ] Pest: vendor uploads CR → S3 stored, row with `status=pending`
- [ ] Pest: vendor can't manipulate `approval_status` directly
- [ ] Pest: suspended vendor redirected on every request
- [ ] Pest: IBAN format validation
- [ ] Pest: vendor can't re-upload `pending` document
- [ ] Pest: re-upload allowed when `status=rejected`

---

### 4.4 Phase 2.0 Addition — Categories Browse + Business Hours + Coverage Areas (1 day)

**Actions used:**
- `ListVendorCategoriesAction` → `GET /api/v1/vendor/categories`
- `UpdateVendorBusinessHoursAction` → `PUT /api/v1/vendor/business-hours`
- `AddBusinessHoursExceptionAction` → `POST /api/v1/vendor/business-hours/exceptions`
- `RemoveBusinessHoursExceptionAction` → `DELETE /api/v1/vendor/business-hours/exceptions/{public_id}`
- `UpdateVendorCoverageAreasAction` → `PUT /api/v1/vendor/coverage-areas`

**Tasks:**
- [ ] `VendorCategoriesPage` — read-only tree filtered to approved types
- [ ] `VendorBusinessHoursPage`:
  - Weekly grid (day × is_open × open_time × close_time)
  - Default: Sat-Thu 09:00-22:00, Fri closed
  - Holiday exceptions calendar
  - Times stored UTC, displayed `Africa/Cairo`
- [ ] `VendorCoverageAreasPage`:
  - Multi-select cities
  - Per-city: `delivery_fee_minor`, `min_booking_value_minor`
  - Phase 1: dropdown list (map preview = Phase 1.5)

**Tests:**
- [ ] Pest: vendor approved for rental sees only rental categories
- [ ] Pest: closing a day blocks bookings on that day
- [ ] Pest: holiday exception overrides weekly schedule
- [ ] Pest: coverage areas filter vendor from search in non-covered cities
- [ ] Pest: per-area delivery fee in booking total
- [ ] Pest: min_booking_value rejected at booking creation

---

### 4.5 Phase 2.1 Addition — Services List + Rental CRUD + Clone + Availability (1 day)

> **v1.2 expansion:** Adds Services List page + Clone action (2 of the 5 gaps).

**Actions used:**
- `ListVendorServicesAction` → `GET /api/v1/vendor/services?type={rental|sale|digital}&status={...}` (NEW v1.2)
- `CreateRentalServiceAction` → `POST /api/v1/vendor/services/rental`
- `UpdateRentalServiceAction` → `PATCH /api/v1/vendor/services/rental/{public_id}`
- `DeleteRentalServiceAction` → `DELETE /api/v1/vendor/services/rental/{public_id}` (soft delete only if no bookings)
- `SubmitServiceForReviewAction` → `POST /api/v1/vendor/services/{public_id}/submit-for-review`
- `ArchiveServiceAction` → `POST /api/v1/vendor/services/{public_id}/archive`
- **`CloneServiceAction` → `POST /api/v1/vendor/services/{public_id}/clone`** (NEW v1.2)
- `BlockServiceDatesAction` → `POST /api/v1/vendor/services/{public_id}/availability-blocks`
- `UnblockServiceDatesAction` → `DELETE /api/v1/vendor/services/availability-blocks/{public_id}`

**Tasks:**

#### Part A: VendorServicesListPage (NEW v1.2)

- [ ] `app/Modules/Catalog/Filament/Vendor/Pages/VendorServicesListPage.php`
- [ ] Unified list of ALL vendor services across 3 types
- [ ] Filter chips: All / Rental / Sale / Digital / By status
- [ ] Columns: name (translatable), type badge, status badge, price, bookings count, last modified
- [ ] Bulk actions: Submit for review (multi), Archive (multi)
- [ ] Row actions: Edit (routes to per-type resource), Clone, View bookings, Block dates (rentals only)

#### Part B: VendorRentalServiceResource (existing v1.1)

- [ ] `app/Modules/Catalog/Filament/Vendor/Resources/VendorRentalServiceResource.php`
- [ ] Form per rental schema in `03_Three_Product_Types.md`
- [ ] EN/AR translatable tabs
- [ ] Spatie Media Library images (max 11)
- [ ] Permission gate: `service.create.rental.own`
- [ ] Global query scope: `vendor_id = auth user's vendor_profile_id`
- [ ] Status column (read-only — admin moderates)
- [ ] "Submit for review" button on drafts

#### Part C: CloneServiceAction (NEW v1.2)

- [ ] `app/Modules/Catalog/Application/Actions/CloneServiceAction.php`
- [ ] Logic:
  - Verify source service belongs to acting vendor (Policy + scope)
  - Duplicate all fields except `public_id`, `id`, `status`, `bookings_count`, `created_at`
  - New service: `status=draft`, `name_en` becomes "Original name (Copy)", `name_ar` becomes "اسم أصلي (نسخة)"
  - Copies images (re-uploads to new media records)
  - Does NOT copy `service_availability_blocks` or `service_reviews`
  - Returns new service for editing
- [ ] Row action on services list: "Clone" button → calls Action → redirects to edit page of new draft
- [ ] Per-type permission check (vendor approved for rental can clone rental; not sale)

#### Part D: VendorServiceAvailabilityPage (existing v1.1)

- [ ] Per-rental-service: calendar of blocked dates
- [ ] Reasons: maintenance / vacation / already booked elsewhere / custom note
- [ ] Stored in `service_availability_blocks`

**Tests:**

For Services List:
- [ ] Pest: list shows all 3 types of services for vendor
- [ ] Pest: filter chips correctly narrow
- [ ] Pest: vendor sees only own services
- [ ] Pest: bulk submit-for-review works

For RentalServiceResource:
- [ ] Pest: vendor approved for rental creates service → status=draft
- [ ] Pest: vendor not approved → 403
- [ ] Pest: vendor cannot edit another vendor's service via URL (403)
- [ ] Pest: published service can't edit material fields without re-moderation

For CloneServiceAction (NEW v1.2):
- [ ] Pest: vendor clones own rental → new draft created with copied fields
- [ ] Pest: vendor cannot clone another vendor's service (403)
- [ ] Pest: clone copies images but not reviews/bookings/availability blocks
- [ ] Pest: clone naming: "X (Copy)" / "X (نسخة)"
- [ ] Pest: vendor with revoked rental permission can't clone rental

For Availability:
- [ ] Pest: blocking a date prevents bookings on that date
- [ ] Pest: removing block re-enables

---

### 4.6 Phase 2.2 Addition — Sale Type (0.5 day)

Same pattern as 2.1 Part B. Differences:

**Actions used:**
- `CreateSaleServiceAction` → `POST /api/v1/vendor/services/sale`
- `UpdateSaleServiceAction` → `PATCH /api/v1/vendor/services/sale/{public_id}`
- Clone action reused (cross-type via `match($enum)` internally)

**Differences from rental:**
- Schema: `is_perishable`, `is_made_to_order`, `lead_time_hours`, `customization_fields`, `stock_quantity`
- Conditional validation: `lead_time_hours` required when `is_made_to_order=true`
- No availability blocks page (sale uses stock, not slots)

**Tests:**
- All from 2.1 minus availability tests
- Pest: conditional validation
- Pest: stock decrement on booking confirmation
- Pest: clone copies sale-specific fields

---

### 4.7 Phase 2.3 Addition — Digital Type (0.5 day)

Same pattern as 2.1 Part B. Differences:

**Actions used:**
- `CreateDigitalServiceAction` → `POST /api/v1/vendor/services/digital`
- `UpdateDigitalServiceAction` → `PATCH /api/v1/vendor/services/digital/{public_id}`
- `UploadDigitalAssetAction` → `POST /api/v1/vendor/services/digital/{public_id}/asset`

**Differences from rental:**
- Schema: `delivery_method`, `has_expiry`, `expiry_days_after_purchase`, `is_refundable_after_delivery`
- Asset upload (private S3)
- No availability blocks (unlimited availability)

**Tests:**
- All from 2.1 minus availability
- Pest: digital-specific validation
- Pest: asset stored on private S3

---

### 4.8 Phase 2.4 Addition — Rental Excel Upload (0.5 day)

**Actions used:**
- `ImportRentalServicesFromExcelAction` → `POST /api/v1/vendor/services/rental/import`
- `GetVendorImportHistoryAction` → `GET /api/v1/vendor/imports`
- `DownloadImportErrorReportAction` → `GET /api/v1/vendor/imports/{public_id}/errors`

**Tasks:**
- [ ] `VendorRentalExcelImportPage`
- [ ] Download template (bilingual `.xlsx`)
- [ ] Upload → queue job → email results
- [ ] Per-row errors in vendor locale
- [ ] Zero rows committed on any failure
- [ ] Import history (last 10 with download links)

**Tests:**
- [ ] Pest: valid Excel → all imported with `vendor_id` forced
- [ ] Pest: invalid row → 0 imported, errors logged
- [ ] Pest: vendor can't import for another vendor (vendor_id forced)
- [ ] Pest: history shows only own imports

---

### 4.9 Phase 3.1 Addition — Incoming Bookings List (0.5 day)

**Actions used:**
- `ListVendorIncomingBookingsAction` → `GET /api/v1/vendor/bookings/incoming`
- `GetBookingDetailAction` → `GET /api/v1/vendor/bookings/{public_id}`

**Tasks:**
- [ ] `VendorIncomingBookingsPage` — table where `sub_status=pending`
- [ ] Columns: booking public_id, customer name, event date+slot, items count+total with type badges, response deadline countdown, risk flag (<2h)
- [ ] Detail page: full booking + items + customer notes + delivery address
- [ ] Filter chips: All / By type / Overdue / Today

**Tests:**
- [ ] Pest: only own incoming bookings
- [ ] Pest: URL manipulation 403
- [ ] Pest: response deadline accurate
- [ ] Pest: filter chips work
- [ ] Pest: overdue indicator fires <2h

---

### 4.10 Phase 3.2 Addition — Accept/Modify/Reject + Active Fulfillment (1 day)

#### Part A: Incoming Bookings Actions

**Actions used:**
- `VendorAcceptBookingAction` → `POST /api/v1/vendor/bookings/{public_id}/accept`
- `VendorModifyBookingAction` → `POST /api/v1/vendor/bookings/{public_id}/modify`
- `VendorRejectBookingAction` → `POST /api/v1/vendor/bookings/{public_id}/reject`

**Tasks:**
- [ ] Accept action: confirmation modal + optional notes
- [ ] Modify action: form (add line items, adjust price, change slot, notes) → updates diff_snapshot
- [ ] Reject action: required bilingual reason
- [ ] Idempotency keys on all three
- [ ] Restricted chat: only while `sub_status IN ('pending','modified')`

#### Part B: Active Bookings Fulfillment

**Actions used (rental):**
- `MarkRentalInTransitAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-in-transit`
- `MarkRentalSetupStartedAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-setup-started`
- `MarkRentalActiveAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-active`
- `MarkRentalTeardownStartedAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-teardown`
- `MarkRentalCompletedAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-completed`

**Actions used (sale):**
- `MarkSaleInPreparationAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-in-preparation`
- `MarkSaleReadyAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-ready`
- `MarkSaleOutForDeliveryAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-out-for-delivery`
- `MarkSaleDeliveredAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-delivered`

**Actions used (digital):**
- `MarkDigitalDeliveredAction` → `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-delivered`
- `MarkDigitalRedeemedAction` → fired by webhook or `POST /api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-redeemed`

**Tasks:**
- [ ] `VendorActiveBookingsPage` — `sub_status=accepted` AND fulfillment in non-terminal state
- [ ] Filtered tabs by product type (each its own state machine)
- [ ] Per-type action buttons aligned with state machines above
- [ ] Each transition logged in `booking_state_transitions`
- [ ] Each transition fires domain event for notifications
- [ ] Optional photo upload on key transitions
- [ ] Per-type "Mark issue" button (stub for Phase 2 dispute engine)

**Tests:**

Incoming actions:
- [ ] Pest: accept → `sub_status=accepted`, event fired
- [ ] Pest: modify → `booking_modifications` row with diff_snapshot
- [ ] Pest: reject → `sub_status=rejected`, reason persisted
- [ ] Pest: chat blocked after final decision
- [ ] Pest: idempotency under concurrent clicks

Fulfillment:
- [ ] Pest: rental state machine — valid prior state required
- [ ] Pest: sale state machine — valid prior state required
- [ ] Pest: digital state machine — valid prior state required
- [ ] Pest: `MarkRentalCompletedAction` triggers commission credit
- [ ] Pest: `MarkSaleDeliveredAction` triggers commission credit
- [ ] Pest: `MarkDigitalDeliveredAction` triggers commission credit
- [ ] Pest: vendor can't mark another vendor's booking
- [ ] Pest: notification fires on each transition

---

### 4.11 Phase 4.2 Addition — Wallet + Withdrawals + Payment History + Commission Breakdown (0.75 day)

> **v1.2 expansion:** Adds Payment History + Commission Breakdown (2 of the 5 gaps).

**Actions used:**
- `GetVendorWalletBalanceAction` → `GET /api/v1/vendor/wallet`
- `ListWalletLedgerAction` → `GET /api/v1/vendor/wallet/ledger`
- `RequestWithdrawalAction` → `POST /api/v1/vendor/withdrawals`
- `ListVendorWithdrawalsAction` → `GET /api/v1/vendor/withdrawals`
- `GetWithdrawalDetailAction` → `GET /api/v1/vendor/withdrawals/{public_id}`
- **`ListBookingPaymentsAction` → `GET /api/v1/vendor/bookings/{public_id}/payments`** (NEW v1.2)
- **`GetBookingCommissionBreakdownAction` → `GET /api/v1/vendor/bookings/{public_id}/commission-breakdown`** (NEW v1.2)

#### Part A: VendorWalletPage (existing v1.1)

- [ ] Current balance (sum of ledger)
- [ ] Available balance (current − pending withdrawals)
- [ ] Ledger read-only with filter
- [ ] Money as `Brick\Money`, locale-formatted

#### Part B: Withdrawal Request + History (existing v1.1)

- [ ] Request form: amount, bank account (re-verification on change), cooldown (1 pending max)
- [ ] History page with status badges
- [ ] Click row → see proof if admin uploaded
- [ ] CSV export (last 12 months)

#### Part C: Payment History Per Booking (NEW v1.2)

- [ ] **`VendorBookingPaymentsPage`** accessible from booking detail page
- [ ] Shows all `payments` rows linked to this booking's vendor share
- [ ] Columns: payment date, customer name, amount captured, status (captured/refunded/partially_refunded), gateway reference
- [ ] Refund rows shown as negative amounts in red

#### Part D: Commission Breakdown Per Booking (NEW v1.2)

- [ ] Inline on booking detail page (vendor view)
- [ ] Shows:
  - Booking gross total
  - Commission rate applied (e.g., "Rental rate for 'Inflatables' category: 15%")
  - Commission amount deducted
  - Net to vendor
  - Note if special rate applied (e.g., promotional commission)
- [ ] Read-only — vendor can't modify

**Tests:**

Wallet + withdrawal (existing):
- [ ] Pest: balance = sum of ledger
- [ ] Pest: available = balance − pending withdrawals
- [ ] Pest: can't withdraw more than available (422)
- [ ] Pest: can't request 2nd withdrawal while first pending
- [ ] Pest: only own withdrawals visible
- [ ] Pest: ledger is read-only

Payment history (NEW v1.2):
- [ ] Pest: vendor sees payments only for own bookings
- [ ] Pest: refund shown as negative
- [ ] Pest: vendor can't see another vendor's payment history (403)

Commission breakdown (NEW v1.2):
- [ ] Pest: breakdown matches commission rate resolution logic
- [ ] Pest: rate fallback resolved correctly (category × type → category × NULL → ...)
- [ ] Pest: special rate flagged with reason
- [ ] Pest: vendor can't see another vendor's breakdown

---

### 4.12 Phase 5.0 Addition — Notification Preferences (1 hour)

**Actions used:**
- `GetVendorNotificationPreferencesAction` → `GET /api/v1/vendor/notification-preferences`
- `UpdateVendorNotificationPreferencesAction` → `PUT /api/v1/vendor/notification-preferences`

**Tasks:**
- [ ] `VendorNotificationPreferencesPage`:
  - Toggle per channel: push / email / SMS / WhatsApp
  - Toggle per event group: Booking / Payment / Fulfillment / Review / Loyalty / Wallet / Marketing
- [ ] Marketing defaults ON (opt-out)
- [ ] Transactional events can't be disabled (compliance)

**Tests:**
- [ ] Pest: disabling marketing → `DispatchNotificationAction` skips marketing for this vendor
- [ ] Pest: can't disable transactional (validation)
- [ ] Pest: channel disabled but group enabled → skip that channel only

---

### 4.13 Phase 5.1 Addition — Reviews Display + Response (0.5 day)

**Actions used:**
- `ListVendorReviewsAction` → `GET /api/v1/vendor/reviews?type={service|vendor}`
- `RespondToReviewAction` → `POST /api/v1/vendor/reviews/{public_id}/respond`
- `FlagReviewAction` → `POST /api/v1/vendor/reviews/{public_id}/flag`

**Tasks:**
- [ ] `VendorReviewsPage`:
  - Tabs: Service reviews / Vendor reviews
  - Filter by rating, service, date range
  - Rating distribution chart (1-5)
- [ ] Response action (one per review, admin-moderated)
- [ ] Flag inappropriate review

**Tests:**
- [ ] Pest: only own reviews visible
- [ ] Pest: response goes to `pending_moderation`
- [ ] Pest: can't edit response once submitted
- [ ] Pest: can't edit/delete reviews
- [ ] Pest: flag → admin queue updated

---

### 4.14 Phase 5.2 Addition — Loyalty Program Config (0.5 day)

**Actions used:**
- `GetVendorLoyaltyProgramAction` → `GET /api/v1/vendor/loyalty/program`
- `UpdateVendorLoyaltyProgramAction` → `PUT /api/v1/vendor/loyalty/program`
- `ActivateLoyaltyProgramAction` → `POST /api/v1/vendor/loyalty/program/activate`
- `DeactivateLoyaltyProgramAction` → `POST /api/v1/vendor/loyalty/program/deactivate`

**Tasks:**
- [ ] `VendorLoyaltyProgramPage`:
  - Enable/disable toggle
  - Earn rule (X points per Y EGP)
  - Redemption rule (X points = Y EGP)
  - Max redemption % per booking
  - Min points to redeem
  - Expiration policy
  - Welcome bonus toggle
- [ ] Preview panel ("Customer spends 1000 EGP → earns N points")

**Tests:**
- [ ] Pest: config persists
- [ ] Pest: loyalty isolated per vendor
- [ ] Pest: deactivation preserves existing balances, blocks new earning
- [ ] Pest: preview matches actual earn logic

---

### 4.15 Phase 6.0 Addition — Vendor Dashboard Widgets (0.5 day)

**Actions used:**
- `GetVendorDashboardMetricsAction` → `GET /api/v1/vendor/dashboard/metrics`
- `GetVendorMonthlyRevenueAction` → `GET /api/v1/vendor/dashboard/revenue?period={...}`

**Tasks:**
- [ ] `VendorDashboardPage` widgets:
  - Bookings this month (count + change)
  - Revenue this month (net after commission)
  - Pending services awaiting moderation
  - Pending bookings awaiting response (overdue red)
  - Active bookings in fulfillment (per-type)
  - Average rating
  - Wallet balance (current + available)
- [ ] Per-vendor scoping
- [ ] Locale-aware formatting
- [ ] Redis cache 5 min, invalidated on relevant events

**Tests:**
- [ ] Pest: widget counts correct per vendor
- [ ] Pest: vendor can't see another's metrics (403)
- [ ] Pest: time ranges respect user timezone
- [ ] Pest: cache invalidation works

---

### 4.16 Phase 6.1 Addition — Sale + Digital Excel (0.5 day)

**Actions used:**
- `ImportSaleServicesFromExcelAction` → `POST /api/v1/vendor/services/sale/import`
- `ImportDigitalServicesFromExcelAction` → `POST /api/v1/vendor/services/digital/import`

Same pattern as 4.8 for the two remaining types.

---

### 4.17 Phase 7.1 Addition — Vendor Portal E2E Smoke Test (0.5 day)

**Goal:** Full vendor lifecycle works on staging via portal.

**Tasks:** Manual E2E using `/vendor` only:
1. Vendor registers + verifies email
2. Uploads documents
3. Admin approves profile + rental type (other panel)
4. Vendor sets business hours + coverage areas
5. Vendor creates rental service
6. Vendor clones the service (NEW v1.2 — verify clone works)
7. Vendor blocks 2 dates
8. Admin moderates → publishes both
9. Customer books (via API or admin impersonation)
10. Vendor sees in incoming list
11. Vendor accepts
12. Customer pays via Paymob sandbox
13. Vendor sees in active list
14. Walks rental through state machine (in_transit → setup → active → teardown → completed)
15. Vendor sees commission breakdown on booking detail (NEW v1.2)
16. Vendor sees payment history on booking (NEW v1.2)
17. Vendor sees credit in wallet
18. Vendor requests withdrawal
19. Admin approves withdrawal + uploads proof
20. Vendor sees proof in history
- [ ] Repeat for sale type (sale state machine)
- [ ] Repeat for digital type (digital flow)
- [ ] Repeat in Arabic (RTL)
- [ ] Load test: 50 services + 100 bookings, dashboard <2s
- [ ] Impersonation banner test (NEW v1.2): admin impersonates → vendor sees banner

**Exit:**
- ✅ 3 product types pass full lifecycle
- ✅ 3 state machines exercised
- ✅ Vendor never touched API directly
- ✅ EN + AR work end-to-end
- ✅ Impersonation banner shows correctly
- ✅ Performance acceptable under load

---

## 5. Total Vendor Portal Effort (v1.2)

| Phase | v1.0 | v1.1 | v1.2 (final) |
|---|---|---|---|
| 0.0 | 1 hour | 1 hour | 1 hour |
| 1.0 | 0.5 day | 0.75 day | 0.75 day (impersonation banner included) |
| 1.1 | 0.5 day | 0.5 day | 0.5 day |
| 2.0 | 1 hour | 1 day | 1 day |
| 2.1 | 0.5 day | 0.75 day | **1 day** (added services list + clone) |
| 2.2 | 0.5 day | 0.5 day | 0.5 day |
| 2.3 | 0.5 day | 0.5 day | 0.5 day |
| 2.4 | 0.5 day | 0.5 day | 0.5 day |
| 3.1 | 0.5 day | 0.5 day | 0.5 day |
| 3.2 | 0.5 day | 1 day | 1 day |
| 4.2 | 0.5 day | 0.5 day | **0.75 day** (added payment history + commission breakdown) |
| 5.0 | 1 hour | 1 hour | 1 hour |
| 5.1 | 0.5 day | 0.5 day | 0.5 day |
| 5.2 | 0.5 day | 0.5 day | 0.5 day |
| 6.0 | 0.5 day | 0.5 day | 0.5 day |
| 6.1 | 0.5 day | 0.5 day | 0.5 day |
| 7.1 | 0.5 day | 0.5 day | 0.5 day |
| **Total** | **~7.5 days** | **~9 days** | **~10.5 days** |

**Within 6h/day budget.** ~1.3 day/week spread across 8 weeks.

---

## 6. Daily Discipline (Vendor Portal Specific)

In addition to rules in `09_Phasing_Plan_v2.md`:

1. **Admin resource first, vendor second.** Always.
2. **Leakage test** for every vendor resource — vendor A cannot see vendor B's data
3. **Translatable everywhere** — every label through `__()`
4. **Mobile-responsive** — every page tested on 375px viewport
5. **Permission gate at THREE layers** — middleware + policy + query scope
6. **`public_id` in URLs always** — never raw `id`
7. **Eager-load relationships** in list queries (no N+1)
8. **Money via `Brick\Money` + `MoneyCast`** — never raw integer/float
9. **State transitions via spatie/laravel-model-states** — never manual updates
10. **Cache dashboard queries 5 min (Redis)** — invalidate on events
11. **NEW v1.2: Every Filament action calls an Action class** — never inline logic. The same Action class is what API endpoint will wrap.
12. **NEW v1.2: API endpoint signatures documented per feature** — even if endpoint not yet built, the contract is fixed.

---

## 7. Cut-List (If Behind)

Order to defer (highest = first to cut):

| Order | What | Defer to |
|---|---|---|
| 1st | Excel page UI polish (functional, less pretty) | Phase 1.5 |
| 2nd | Service clone action (vendor re-enters manually) | Phase 1.5 |
| 3rd | Service availability blocks UI (vendor asks admin) | Phase 1.5 |
| 4th | Per-channel notification preferences (binary on/off) | Phase 1.5 |
| 5th | Vendor responds to reviews (read-only) | Phase 6.0 |
| 6th | Loyalty config UI (admin configures via /admin) | Phase 1.5 |
| 7th | Coverage areas per-area delivery fee (single fee) | Phase 1.5 |
| 8th | Active booking optional photo upload | Phase 1.5 |
| 9th | Withdrawal request from portal (admin creates) | Phase 1.5 |

**Never cut:**
- Per-vendor data scoping (security)
- Service moderation workflow (compliance)
- Bilingual EN+AR (locked)
- Audit log integration (compliance)
- Active bookings fulfillment state machines (revenue core)
- Business hours (bookings break without)
- Coverage areas (search breaks without)
- **Payment history + commission breakdown** (vendor trust — they need to see why they got X EGP)
- **Impersonation banner** (compliance + transparency)
- **Services list page** (basic UX — can't ship without this)

---

## 8. How to Use This Plan with Claude Code

```
ADDITION TO PHASE X.Y — Vendor Portal

CONTEXT TO LOAD:
- CLAUDE.md
- docs/specs/12_Vendor_Portal_Plan.md (this file) — section 4.X
- docs/specs/09_Phasing_Plan_v2.md (parent phase X.Y)
- docs/specs/02_Tech_Decisions.md §13
- docs/specs/07_Vendor_Journey.md
- .claude/rules/filament.md
- .claude/rules/filament-components.md

PRE-REQUISITES TO CONFIRM:
1. Admin resource for {feature} exists at app/Modules/{Module}/Filament/Admin/Resources/
2. Action class for {feature} exists in app/Modules/{Module}/Application/Actions/
3. Policy defined
4. Migration run
5. If state machine: spatie/laravel-model-states states defined
6. If type-aware: covered for rental + sale + digital

OUTPUT REQUIRED:
1. Restate section of section 4 you're implementing
2. List Action classes used + API endpoint signatures
3. List files to create
4. List Pest tests (always including leakage test)
5. Confirm three-layer authorization
6. Wait for "go"

CONSTRAINTS:
- Vendor sees own data only (Pest leakage test)
- Bilingual EN+AR
- Mobile-responsive (375px)
- public_id in URLs
- Money via Brick\Money + MoneyCast
- Cross-type code via match($enum)
- Eager-load relationships
- State transitions via model-states
- Filament UI calls Action class (no inline logic)
- Action class is the same one wrapped by API endpoint
```

---

## 9. Future (Phase 2)

Deferred — DO NOT build in Phase 1:

- Flutter Vendor Mobile App (`instaparty-vendor-mobile`)
- Vendor self-service tier upgrade (subscription module)
- Vendor branded landing page
- Vendor QR / barcode catalog
- Vendor analytics dashboard (funnel + cohort)
- Vendor team accounts (multi-user)
- API tokens for vendors (own integrations)
- Pricing tiers UI
- Custom per-booking delivery fee override
- Vendor 2FA
- Dispute initiation by vendor
- Map preview for coverage areas
- Self-service document re-upload after rejection

---

## 10. Comparison: Flutter Vendor App vs Filament Vendor Portal

| Concern | Flutter Vendor App | Filament Vendor Portal |
|---|---|---|
| Phase 1 timeline | +14-21 days | +10.5 days |
| Codebase | Separate repo | Same Laravel |
| API contracts | External (freeze) | Internal Actions (no freeze, but documented in §12) |
| Bulk operations | Hard on mobile | Native on web |
| Catalog mgmt for 50+ items | Painful UX | Native UX |
| Real Egyptian vendor preference | ~30% mobile | ~70% web for management |
| Phase 1 deliverable | "Backend + API" | "Working vendor portal" |
| Phase 1.5 cost | From scratch | Reuse Actions + add mobile UI |
| Burnout risk | High (parallel tracks) | Low (single codebase) |

**Filament Phase 1 + Flutter Phase 2** — pattern locked.

---

## 11. Review History

### v1.0 (initial)

Skeleton based on `07_Vendor_Journey.md` + PRD.

### v1.1 — 9 gaps closed

1. 🔴 Business hours
2. 🔴 Coverage areas
3. 🔴 Active fulfillment state machines (3 per-type)
4. 🟡 Service availability blocks
5. 🟡 Pricing tiers cut not explicit
6. 🟡 Account self-edit
7. 🟢 Notification event groups
8. 🟢 Performance constraints
9. 🟡 Email verification + 2FA

### v1.2 — 5 vendor UX gaps + API parity

5 new vendor features:

1. 🟡 **Services list page** (separate from create form) — §4.5 Part A
2. 🟢 **Service clone action** — §4.5 Part C
3. 🟡 **Impersonation indicator banner** — §2.7 + §4.2
4. 🟡 **Payment history per booking** — §4.11 Part C
5. 🟡 **Commission breakdown per booking** — §4.11 Part D

Plus structural:

- Every feature now lists **Action class + REST API endpoint** for Phase 2 mobile parity (NEW §12)
- New §2.2 principle: Filament UI + API both call the same Action
- New daily discipline rules 11 + 12 (Action class + API contract)
- Cut-list updated: 3 new "never cut" items (payment history, commission breakdown, impersonation banner)
- Effort: 9d → 10.5d

---

## 12. Action & API Reference (NEW v1.2)

Complete map of vendor portal features → Action class → API endpoint. **Single source of truth** for what Phase 2 Flutter app will consume.

> **Note:** Routes use `/api/v1/vendor/...` prefix; all require Sanctum token + `role:vendor` ability. URLs use `{public_id}` ULIDs.

### 12.1 Authentication & Account

| Feature | Action class | HTTP method | Endpoint | Auth |
|---|---|---|---|---|
| Register | `RegisterVendorAction` | POST | `/api/v1/vendor/register` | Public |
| Verify email | `VerifyVendorEmailAction` | POST | `/api/v1/vendor/email/verify/{hash}` | Public |
| Resend verification | `ResendVerificationAction` | POST | `/api/v1/vendor/email/resend` | Auth, not verified |
| Login | `LoginAction` | POST | `/api/v1/vendor/login` | Public |
| Logout | `LogoutAction` | POST | `/api/v1/vendor/logout` | Auth |
| Request password reset | `RequestPasswordResetAction` | POST | `/api/v1/vendor/password/reset-link` | Public |
| Reset password | `ResetPasswordAction` | POST | `/api/v1/vendor/password/reset` | Public + token |
| Change password | `ChangeVendorPasswordAction` | PATCH | `/api/v1/vendor/account/password` | Auth |
| Update email | `UpdateVendorEmailAction` | PATCH | `/api/v1/vendor/account/email` | Auth |
| Update phone | `UpdateVendorPhoneAction` | PATCH | `/api/v1/vendor/account/phone` | Auth |
| End impersonation | `EndImpersonationAction` | POST | `/api/v1/vendor/impersonation/end` | Auth, impersonated |

### 12.2 Profile & Documents

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get profile | `GetVendorProfileAction` | GET | `/api/v1/vendor/profile` |
| Update profile | `UpdateVendorProfileAction` | PATCH | `/api/v1/vendor/profile` |
| Upload document | `UploadVendorDocumentAction` | POST | `/api/v1/vendor/documents` |
| List documents | `ListVendorDocumentsAction` | GET | `/api/v1/vendor/documents` |
| Delete document | `DeleteVendorDocumentAction` | DELETE | `/api/v1/vendor/documents/{public_id}` |
| Request approval for type | `RequestApprovalForTypeAction` | POST | `/api/v1/vendor/approval-requests` |
| Get approval status | `GetVendorApprovalStatusAction` | GET | `/api/v1/vendor/approval-status` |

### 12.3 Business Hours & Coverage

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get business hours | `GetVendorBusinessHoursAction` | GET | `/api/v1/vendor/business-hours` |
| Update business hours | `UpdateVendorBusinessHoursAction` | PUT | `/api/v1/vendor/business-hours` |
| Add exception | `AddBusinessHoursExceptionAction` | POST | `/api/v1/vendor/business-hours/exceptions` |
| Remove exception | `RemoveBusinessHoursExceptionAction` | DELETE | `/api/v1/vendor/business-hours/exceptions/{public_id}` |
| Get coverage areas | `GetVendorCoverageAreasAction` | GET | `/api/v1/vendor/coverage-areas` |
| Update coverage areas | `UpdateVendorCoverageAreasAction` | PUT | `/api/v1/vendor/coverage-areas` |

### 12.4 Catalog (Categories & Services)

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| List categories | `ListVendorCategoriesAction` | GET | `/api/v1/vendor/categories` |
| List own services | `ListVendorServicesAction` | GET | `/api/v1/vendor/services?type={...}&status={...}` |
| Get service detail | `GetVendorServiceAction` | GET | `/api/v1/vendor/services/{public_id}` |
| Create rental | `CreateRentalServiceAction` | POST | `/api/v1/vendor/services/rental` |
| Update rental | `UpdateRentalServiceAction` | PATCH | `/api/v1/vendor/services/rental/{public_id}` |
| Create sale | `CreateSaleServiceAction` | POST | `/api/v1/vendor/services/sale` |
| Update sale | `UpdateSaleServiceAction` | PATCH | `/api/v1/vendor/services/sale/{public_id}` |
| Create digital | `CreateDigitalServiceAction` | POST | `/api/v1/vendor/services/digital` |
| Update digital | `UpdateDigitalServiceAction` | PATCH | `/api/v1/vendor/services/digital/{public_id}` |
| Upload digital asset | `UploadDigitalAssetAction` | POST | `/api/v1/vendor/services/digital/{public_id}/asset` |
| Delete service | `DeleteServiceAction` | DELETE | `/api/v1/vendor/services/{public_id}` |
| Submit for review | `SubmitServiceForReviewAction` | POST | `/api/v1/vendor/services/{public_id}/submit-for-review` |
| Archive service | `ArchiveServiceAction` | POST | `/api/v1/vendor/services/{public_id}/archive` |
| **Clone service** | `CloneServiceAction` | POST | `/api/v1/vendor/services/{public_id}/clone` |
| Block dates | `BlockServiceDatesAction` | POST | `/api/v1/vendor/services/{public_id}/availability-blocks` |
| Unblock dates | `UnblockServiceDatesAction` | DELETE | `/api/v1/vendor/services/availability-blocks/{public_id}` |
| List service images | `ListServiceImagesAction` | GET | `/api/v1/vendor/services/{public_id}/images` |
| Upload service image | `UploadServiceImageAction` | POST | `/api/v1/vendor/services/{public_id}/images` |
| Delete service image | `DeleteServiceImageAction` | DELETE | `/api/v1/vendor/services/{public_id}/images/{image_id}` |
| Reorder service images | `ReorderServiceImagesAction` | PUT | `/api/v1/vendor/services/{public_id}/images/order` |

### 12.5 Excel Import

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Download rental template | `DownloadRentalTemplateAction` | GET | `/api/v1/vendor/services/rental/template` |
| Download sale template | `DownloadSaleTemplateAction` | GET | `/api/v1/vendor/services/sale/template` |
| Download digital template | `DownloadDigitalTemplateAction` | GET | `/api/v1/vendor/services/digital/template` |
| Import rentals | `ImportRentalServicesFromExcelAction` | POST | `/api/v1/vendor/services/rental/import` |
| Import sales | `ImportSaleServicesFromExcelAction` | POST | `/api/v1/vendor/services/sale/import` |
| Import digital | `ImportDigitalServicesFromExcelAction` | POST | `/api/v1/vendor/services/digital/import` |
| List imports | `GetVendorImportHistoryAction` | GET | `/api/v1/vendor/imports` |
| Get import detail | `GetImportDetailAction` | GET | `/api/v1/vendor/imports/{public_id}` |
| Download error report | `DownloadImportErrorReportAction` | GET | `/api/v1/vendor/imports/{public_id}/errors` |

### 12.6 Bookings (Incoming + Active)

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| List incoming bookings | `ListVendorIncomingBookingsAction` | GET | `/api/v1/vendor/bookings/incoming` |
| List active bookings | `ListVendorActiveBookingsAction` | GET | `/api/v1/vendor/bookings/active` |
| List historical bookings | `ListVendorHistoricalBookingsAction` | GET | `/api/v1/vendor/bookings/history` |
| Get booking detail | `GetBookingDetailAction` | GET | `/api/v1/vendor/bookings/{public_id}` |
| Accept booking | `VendorAcceptBookingAction` | POST | `/api/v1/vendor/bookings/{public_id}/accept` |
| Modify booking | `VendorModifyBookingAction` | POST | `/api/v1/vendor/bookings/{public_id}/modify` |
| Reject booking | `VendorRejectBookingAction` | POST | `/api/v1/vendor/bookings/{public_id}/reject` |
| Mark rental in transit | `MarkRentalInTransitAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-in-transit` |
| Mark rental setup started | `MarkRentalSetupStartedAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-setup-started` |
| Mark rental active | `MarkRentalActiveAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-active` |
| Mark rental teardown | `MarkRentalTeardownStartedAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-teardown` |
| Mark rental completed | `MarkRentalCompletedAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-completed` |
| Mark sale in preparation | `MarkSaleInPreparationAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-in-preparation` |
| Mark sale ready | `MarkSaleReadyAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-ready` |
| Mark sale out for delivery | `MarkSaleOutForDeliveryAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-out-for-delivery` |
| Mark sale delivered | `MarkSaleDeliveredAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-delivered` |
| Mark digital delivered | `MarkDigitalDeliveredAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-delivered` |
| Mark digital redeemed | `MarkDigitalRedeemedAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-redeemed` |
| Mark issue (stub) | `MarkBookingIssueAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/mark-issue` |
| Upload fulfillment photo | `UploadFulfillmentPhotoAction` | POST | `/api/v1/vendor/bookings/{public_id}/items/{item_id}/photos` |

### 12.7 Wallet, Payments, Commission, Withdrawals

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get wallet | `GetVendorWalletBalanceAction` | GET | `/api/v1/vendor/wallet` |
| List ledger | `ListWalletLedgerAction` | GET | `/api/v1/vendor/wallet/ledger?type={...}&from={...}&to={...}` |
| Request withdrawal | `RequestWithdrawalAction` | POST | `/api/v1/vendor/withdrawals` |
| List withdrawals | `ListVendorWithdrawalsAction` | GET | `/api/v1/vendor/withdrawals` |
| Get withdrawal detail | `GetWithdrawalDetailAction` | GET | `/api/v1/vendor/withdrawals/{public_id}` |
| Export withdrawals CSV | `ExportVendorWithdrawalsAction` | GET | `/api/v1/vendor/withdrawals/export?from={...}&to={...}` |
| **List booking payments** | `ListBookingPaymentsAction` | GET | `/api/v1/vendor/bookings/{public_id}/payments` |
| **Get commission breakdown** | `GetBookingCommissionBreakdownAction` | GET | `/api/v1/vendor/bookings/{public_id}/commission-breakdown` |

### 12.8 Notifications

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get preferences | `GetVendorNotificationPreferencesAction` | GET | `/api/v1/vendor/notification-preferences` |
| Update preferences | `UpdateVendorNotificationPreferencesAction` | PUT | `/api/v1/vendor/notification-preferences` |
| List notifications | `ListVendorNotificationsAction` | GET | `/api/v1/vendor/notifications?unread={true|false}` |
| Mark notification read | `MarkNotificationReadAction` | PATCH | `/api/v1/vendor/notifications/{public_id}/read` |
| Mark all read | `MarkAllNotificationsReadAction` | POST | `/api/v1/vendor/notifications/read-all` |

### 12.9 Reviews

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| List own reviews | `ListVendorReviewsAction` | GET | `/api/v1/vendor/reviews?type={service|vendor}` |
| Respond to review | `RespondToReviewAction` | POST | `/api/v1/vendor/reviews/{public_id}/respond` |
| Flag review | `FlagReviewAction` | POST | `/api/v1/vendor/reviews/{public_id}/flag` |

### 12.10 Loyalty

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get loyalty program | `GetVendorLoyaltyProgramAction` | GET | `/api/v1/vendor/loyalty/program` |
| Update loyalty program | `UpdateVendorLoyaltyProgramAction` | PUT | `/api/v1/vendor/loyalty/program` |
| Activate program | `ActivateLoyaltyProgramAction` | POST | `/api/v1/vendor/loyalty/program/activate` |
| Deactivate program | `DeactivateLoyaltyProgramAction` | POST | `/api/v1/vendor/loyalty/program/deactivate` |
| List customers with points | `ListLoyaltyMembersAction` | GET | `/api/v1/vendor/loyalty/members` |

### 12.11 Dashboard

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| Get dashboard metrics | `GetVendorDashboardMetricsAction` | GET | `/api/v1/vendor/dashboard/metrics` |
| Get monthly revenue | `GetVendorMonthlyRevenueAction` | GET | `/api/v1/vendor/dashboard/revenue?period={...}` |
| Get bookings trend | `GetVendorBookingsTrendAction` | GET | `/api/v1/vendor/dashboard/bookings-trend?period={...}` |

### 12.12 Chat (restricted to booking review window)

| Feature | Action class | HTTP method | Endpoint |
|---|---|---|---|
| List chat threads | `ListVendorChatThreadsAction` | GET | `/api/v1/vendor/chat/threads` |
| Get thread messages | `GetChatThreadAction` | GET | `/api/v1/vendor/chat/threads/{public_id}` |
| Send chat message | `SendChatMessageAction` | POST | `/api/v1/vendor/chat/threads/{public_id}/messages` |

**Implementation note:** chat content lives in Firebase; these endpoints wrap Firebase writes + audit-log to MySQL.

---

## 13. Build Order Discipline

To ensure both Filament and API stay in sync, build EVERY feature in this order:

1. **Migration** (if needed)
2. **Model** (relationships, casts, scopes — no logic)
3. **Policy**
4. **Action class** (the business logic — single source of truth)
5. **Form Request** (validation — used by both Filament and API)
6. **Filament Resource/Page** (calls Action)
7. **API Controller method** (calls same Action — even if API endpoint not consumed yet in Phase 1, the contract is locked)
8. **Pest tests** (cover Action + Policy + Filament + API)

**Why step 7 is mandatory even in Phase 1:**

Building the API endpoint now (even if only Filament uses the Action today) means:
- Phase 2 Flutter starts with all endpoints already built and tested
- The contract is enforced — you can't accidentally introduce Filament-only logic
- API tests double as Filament tests for the Action layer
- Documentation (Scribe) generates automatically at Phase 7.2

**Cost:** ~15% more time per feature.
**Benefit:** Phase 2 mobile starts 2-3 weeks ahead of where it would otherwise.

---

**End of Vendor Portal Plan v1.2 (Final).**