# Implementation Plan: Identity & Vendor Onboarding (Phases 0.2 + 1.0 + 1.1)

**Branch**: `002-identity-vendor-onboarding` | **Date**: 2026-04-28 | **Spec**: [spec.md](spec.md)

---

## Summary

Three sequential phases that build the supply-side identity layer for InstaParty:

- **Phase 0.2** (1 day): All 10 Identity table migrations applied + `MoneyCast` value object in Shared module. No models yet — schema-only gate.
- **Phase 1.0** (3 days): Eloquent models, Sanctum auth, Spatie roles/permissions seeder, RegisterCustomerAction, RegisterVendorAction, LoginAction, LogoutAction, API Resources, bilingual routes. API is functional end-to-end.
- **Phase 1.1** (2 days): Admin Filament Vendor Approval Queue, per-type approve/revoke actions, document upload (S3 direct), domain events, notification stubs.

**Blocks downstream**: Phase 2.x Catalog (vendors must exist and be approved per type before service creation).

---

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12  
**Primary Dependencies**: Sanctum (auth), spatie/laravel-permission (roles), spatie/laravel-translatable (i18n), brick/money (money), Filament v3 (admin), bezhansalleh/filament-shield (Filament permissions)  
**Storage**: MySQL 8 (utf8mb4_unicode_ci), Redis (OTP + cache), DigitalOcean Spaces/MinIO (S3 document storage)  
**Testing**: Pest v3 (Feature + Unit), SQLite in-memory for unit tests, MySQL for feature tests  
**Target Platform**: Linux server (Laravel Sail / Docker)  
**Project Type**: Modular monolith — backend API + Filament admin  
**Performance Goals**: Identity endpoints < 200ms p95; OTP rate-limit enforced server-side  
**Constraints**: No float money, no cross-module model imports, no STI for product types, soft delete only on approved tables (CLAUDE.md §15)  
**Scale/Scope**: Phase 1 targets ~100 vendors, ~1k customers; schema headroom for 10k+ vendors

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Rule | Status | Notes |
|---|---|---|
| Thin controllers (3-line action body max) | ✅ | All controllers delegate to Action classes |
| Fat single-purpose Actions (one `execute()`) | ✅ | RegisterCustomerAction, LoginAction, etc. — each has one public method |
| Models hold only relationships/casts/scopes | ✅ | No business logic in Domain/Models |
| Translatable fields via spatie/laravel-translatable | ✅ | `business_name`, `bio`, `address_line`, `rejection_reason` — JSON columns |
| Internal `id` BIGINT + external `public_id` ULID | ✅ | All entities have both |
| Money columns: BIGINT `_minor` + CHAR(3) `_currency` | ✅ | `VendorCoverageArea.delivery_fee_minor/currency`, `min_order_minor/currency` |
| Domain events fire after `DB::afterCommit` | ✅ | Pattern locked in R7 research + actions.md rules |
| No cross-module Eloquent model imports | ✅ | Geography cross-module via FK only; no Geography model import |
| Soft delete only on approved tables | ✅ | `users`, `vendor_profiles`, `customer_addresses` — correct per CLAUDE.md §15 |
| `MoneyCast` in Shared module | ✅ | Phase 0.2 deliverable — `app/Modules/Shared/Domain/Casts/MoneyCast` |
| Per-product-type vendor permissions | ✅ | 12 explicit permissions, not role-based |
| No business logic in Filament closures | ✅ | Filament actions delegate to Application Actions |
| `shield:generate --all` after every new Resource | ✅ | Reminder in quickstart.md |

**Constitution violations**: None.

---

## Project Structure

### Documentation (this feature)

```text
specs/002-phase-1-identity-vendor-onboarding/
├── plan.md              ← this file
├── spec.md              ← feature specification (updated 2026-04-28)
├── research.md          ← Phase 0 research (complete)
├── data-model.md        ← entity map, state transitions, domain events
├── quickstart.md        ← dev setup + verification steps
├── contracts/
│   ├── customer-auth.md           ← POST /register/customer, POST /login, etc.
│   ├── vendor-registration.md     ← POST /register/vendor, PUT /profile, etc.
│   └── admin-vendor-approval.md   ← Filament-side approval contract
├── checklists/
│   └── requirements.md
└── tasks.md             ← Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code Layout

```text
app/Modules/Shared/
├── Domain/
│   └── Casts/
│       └── MoneyCast.php               ← Phase 0.2
└── Http/
    └── Middleware/
        └── SetLocaleMiddleware.php     ← Phase 1.0

app/Modules/Identity/
├── Domain/
│   ├── Models/
│   │   ├── User.php
│   │   ├── VendorProfile.php
│   │   ├── VendorDocument.php
│   │   ├── VendorApprovedProductType.php
│   │   ├── VendorBusinessHour.php
│   │   ├── VendorCoverageArea.php
│   │   ├── CustomerProfile.php
│   │   ├── CustomerAddress.php
│   │   ├── UserDevice.php
│   │   └── TwoFactorSecret.php
│   ├── Enums/
│   │   ├── ApprovalStatus.php
│   │   ├── BusinessType.php
│   │   └── DocumentType.php
│   ├── Events/
│   │   ├── CustomerRegistered.php
│   │   ├── VendorRegistered.php
│   │   ├── VendorApproved.php
│   │   ├── VendorApprovedForType.php
│   │   ├── VendorRejected.php
│   │   ├── VendorSuspended.php
│   │   ├── VendorTypeRevoked.php
│   │   └── PhoneVerified.php
│   └── Contracts/
│       └── OtpGateway.php
├── Application/
│   ├── Actions/
│   │   ├── RegisterCustomerAction.php
│   │   ├── RegisterVendorAction.php
│   │   ├── LoginAction.php
│   │   ├── LogoutAction.php
│   │   ├── VerifyPhoneAction.php
│   │   ├── UpdateVendorProfileAction.php
│   │   ├── UploadVendorDocumentAction.php
│   │   ├── ApproveVendorProfileAction.php
│   │   ├── ApproveVendorForTypeAction.php
│   │   ├── RejectVendorProfileAction.php
│   │   ├── RevokeVendorTypeAction.php
│   │   └── SuspendVendorAction.php
│   ├── DTOs/
│   │   ├── RegisterCustomerData.php
│   │   ├── RegisterVendorData.php
│   │   └── VendorApprovalData.php
│   └── Listeners/
│       └── RevokeAllVendorTypesOnStatusChange.php
├── Infrastructure/
│   ├── Repositories/
│   │   ├── UserRepository.php
│   │   └── VendorProfileRepository.php
│   └── Gateways/
│       └── StubOtpGateway.php
├── Http/
│   ├── Controllers/
│   │   ├── CustomerAuthController.php
│   │   ├── CustomerProfileController.php
│   │   ├── VendorAuthController.php
│   │   ├── VendorProfileController.php
│   │   └── VendorDocumentController.php
│   ├── Requests/
│   │   ├── RegisterCustomerRequest.php
│   │   ├── RegisterVendorRequest.php
│   │   ├── LoginRequest.php
│   │   ├── UpdateVendorProfileRequest.php
│   │   └── UploadVendorDocumentRequest.php
│   └── Resources/
│       ├── UserResource.php
│       ├── VendorProfileResource.php
│       └── CustomerProfileResource.php
├── Filament/
│   └── Resources/
│       ├── VendorProfileResource.php
│       └── VendorApprovalQueueResource.php
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   └── admin.php
├── Database/
│   └── Migrations/
│       ├── 2026_01_01_000010_create_vendor_profiles_table.php
│       ├── 2026_01_01_000011_create_vendor_documents_table.php
│       ├── 2026_01_01_000012_create_vendor_approved_product_types_table.php
│       ├── 2026_01_01_000013_create_vendor_business_hours_table.php
│       ├── 2026_01_01_000014_create_customer_profiles_table.php
│       ├── 2026_01_01_000015_create_customer_addresses_table.php
│       ├── 2026_01_01_000016_create_user_devices_table.php
│       └── 2026_01_01_000017_create_two_factor_secrets_table.php
├── Resources/
│   └── lang/{en,ar}/identity.php
└── Providers/
    └── IdentityServiceProvider.php

tests/
├── Feature/
│   └── Modules/
│       └── Identity/
│           ├── CustomerRegistrationTest.php
│           ├── VendorRegistrationTest.php
│           ├── AuthTest.php
│           ├── VendorApprovalTest.php
│           └── MigrationSmokeTest.php
└── Unit/
    └── Modules/
        ├── Identity/
        │   └── VendorApprovalStatusTest.php
        └── Shared/
            └── MoneyCastTest.php           ← Phase 0.2

database/seeders/
└── IdentityRolesSeeder.php
```

---

## Phase 0.2 — Identity Migrations + MoneyCast (Day 1)

### Deliverables

1. **`MoneyCast`** — `app/Modules/Shared/Domain/Casts/MoneyCast.php`
   - Implements `CastsAttributes<Brick\Money\Money|null, never>`
   - Constructor receives `string $minorField, string $currencyField`
   - `get()`: returns `Money::ofMinor($minor, $currency)` or null
   - `set()`: converts `Money` to `['field_minor' => $minor, 'field_currency' => $iso]`
   - Declared on models via `$casts = ['delivery_fee' => MoneyCast::class . ':delivery_fee_minor,delivery_fee_currency']`

2. **All Identity migrations** — in `app/Modules/Identity/Database/Migrations/`:
   - `000010_create_vendor_profiles_table`
   - `000011_create_vendor_documents_table`
   - `000012_create_vendor_approved_product_types_table`
   - `000013_create_vendor_business_hours_table`
   - `000014_create_customer_profiles_table`
   - `000015_create_customer_addresses_table`
   - `000016_create_user_devices_table`
   - `000017_create_two_factor_secrets_table`
   - Geography module owns `vendor_coverage_areas` (migration 000005 — already exists)

3. **Tests** — `tests/Unit/Modules/Shared/MoneyCastTest.php` and `MigrationSmokeTest.php`

### Exit Gate

- `php artisan migrate:fresh` → zero errors
- FK constraint on `vendor_coverage_areas.city_id` → delete city with coverage → rejected
- `MoneyCastTest::it_round_trips_minor_units` → passes

---

## Phase 1.0 — Identity Core: Models + Auth (Days 2–4)

### Day 2: Models + Sanctum + Roles

**Models** (all in `Domain/Models/`) — relationships, casts, scopes only:
- `User` — HasRoles, HasApiTokens, SoftDeletes, HasPublicId
- `VendorProfile` — HasTranslations, SoftDeletes, HasPublicId; casts: `approval_status → ApprovalStatus`, `business_type → BusinessType`
- `VendorDocument` — HasPublicId; casts: `doc_type → DocumentType`, `status → DocumentStatus`
- `VendorApprovedProductType` — scopes: `active()` (revoked_at IS NULL)
- `VendorCoverageArea` — MoneyCast on `delivery_fee` + `min_order`
- `CustomerProfile`, `CustomerAddress` (SoftDeletes, HasPublicId), `UserDevice`, `TwoFactorSecret`

**Sanctum config** — `config/auth.php`:
- `api` guard → `sanctum`
- SPA stateful domains via `SANCTUM_STATEFUL_DOMAINS`

**IdentityRolesSeeder** — creates `customer`, `vendor`, `admin` roles + all 12 per-type service permissions + admin approval permissions. First admin user seeded.

### Day 3: Auth Actions + API

**Actions** (each in `Application/Actions/`):
- `RegisterCustomerAction::execute(RegisterCustomerData $dto): User` — creates user + customer_profile + sends OTP stub; fires `CustomerRegistered` after commit
- `RegisterVendorAction::execute(RegisterVendorData $dto): User` — creates user + vendor_profile (pending); fires `VendorRegistered` after commit
- `LoginAction::execute(LoginRequest $request): array` — validates credentials, returns token (mobile) or sets cookie (SPA); detects mode via `$request->expectsJson()` + stateful check
- `LogoutAction::execute(User $user): void` — revokes current token or forgets SPA session
- `VerifyPhoneAction::execute(User $user, string $code): void` — checks Redis OTP; sets `phone_verified_at`; fires `PhoneVerified` after commit

**OTP rate-limiting**: `RateLimiter::for('otp-send:{phone}')` — 3/10min, then 60min lockout. `ThrottleOtpSendMiddleware` or inline in `VerifyPhoneAction`.

**API Resources**: `UserResource`, `VendorProfileResource`, `CustomerProfileResource` — locale-aware via `app()->getLocale()`

**Routes** loaded by `IdentityServiceProvider`:
- `Routes/customer.php` — `/register/customer`, `/phone/verify`, `/customer/profile`, `/customer/addresses`
- `Routes/vendor.php` — `/register/vendor`, `/vendor/profile`, `/vendor/documents`, `/vendor/coverage-areas`, `/vendor/business-hours`
- `Routes/admin.php` — admin-only vendor endpoints (consumed by Filament actions)

**SetLocaleMiddleware** — reads `Accept-Language` header, calls `app()->setLocale()`; registered in `IdentityServiceProvider` → applied to all Identity API routes

### Day 4: Tests

| Test | Location | Coverage |
|---|---|---|
| Customer registration happy path | `CustomerRegistrationTest` | 201 + user row + customer_profile row |
| Duplicate phone → 422 | `CustomerRegistrationTest` | field-level error in request locale |
| Vendor registration happy path | `VendorRegistrationTest` | 201 + vendor_profile approval_status=pending |
| Login returns Sanctum token | `AuthTest` | 200 + `data.token` present |
| Login invalid credentials → 401 | `AuthTest` | |
| Logout revokes token | `AuthTest` | subsequent request → 401 |
| EN response locale | `CustomerRegistrationTest` | `Accept-Language: en` |
| AR response locale | `CustomerRegistrationTest` | `Accept-Language: ar` |
| Rate limit on register (5/min) | `AuthTest` | 6th request → 429 |
| 4th OTP send → 429 + Retry-After | `AuthTest` | Redis lockout |

### Phase 1.0 Exit Gate

- `POST /api/v1/register/customer` and `POST /api/v1/register/vendor` → 201
- `POST /api/v1/login` → 200 with Sanctum token
- All Pest tests pass

---

## Phase 1.1 — Vendor Onboarding + Approval (Days 5–6)

### Day 5: Approval Actions + Filament Queue

**Actions** (each in `Application/Actions/`):
- `ApproveVendorProfileAction::execute(VendorProfile $vendor, User $admin): void` — sets `approval_status=approved`, `approved_at`, `approved_by`; fires `VendorApproved` after commit
- `ApproveVendorForTypeAction::execute(VendorProfile $vendor, ProductType $type, User $admin): VendorApprovedProductType` — guards: vendor must be `approved` (422 otherwise); creates `vendor_approved_product_types` row; grants Spatie permissions for that type; fires `VendorApprovedForType` after commit
- `RejectVendorProfileAction::execute(VendorProfile $vendor, array $reason, User $admin): void` — sets `approval_status=rejected`, fires `VendorRejected`, triggers `RevokeAllVendorTypesOnStatusChange`
- `RevokeVendorTypeAction::execute(VendorProfile $vendor, ProductType $type, User $admin): void` — sets `revoked_at`; revokes Spatie permissions for that type; fires `VendorTypeRevoked`
- `SuspendVendorAction::execute(VendorProfile $vendor, User $admin): void` — sets `approval_status=suspended`, fires `VendorSuspended`, triggers `RevokeAllVendorTypesOnStatusChange`
- `UploadVendorDocumentAction::execute(VendorProfile $vendor, UploadVendorDocumentRequest $request): VendorDocument` — validates MIME + size; stores via `Storage::disk('s3-private')`; creates `vendor_documents` row

**Listener**: `RevokeAllVendorTypesOnStatusChange` — listens to `VendorSuspended` + `VendorRejected`; revokes all active `vendor_approved_product_types` rows + Spatie permissions in one transaction; fires `VendorTypeRevoked` per type after commit

**Filament Resources**:
- `VendorProfileResource` — table with `approval_status` badge column, searchable `business_name`, per-type approve/revoke action buttons; delegates to Action classes
- `VendorApprovalQueueResource` — filtered view of `pending` vendors; inherits from VendorProfileResource with additional queue filters

Run `php artisan shield:generate --all` after creating both resources.

**Domain Events**: `VendorApprovedForType`, `VendorTypeRevoked`, `VendorApproved`, `VendorSuspended`, `VendorRejected`

**Notification stubs** (real dispatch in Phase 5):
- `VendorApprovedNotification` — logs "vendor {id} approved for {type}"
- `VendorRejectedNotification` — logs "vendor {id} rejected: {reason}"

### Day 6: Tests

| Test | Coverage |
|---|---|
| Approve vendor for rental → `hasPermissionTo('service.create.rental.own')` = true | `VendorApprovalTest` |
| Approve vendor for rental → cannot create sale service (`service.create.sale.own` = false) | `VendorApprovalTest` |
| Approve pending vendor profile first, then type → success | `VendorApprovalTest` |
| Approve type on pending vendor → 422 | `VendorApprovalTest` |
| Revoke type → permission revoked | `VendorApprovalTest` |
| Suspend vendor → all type rows revoked + permissions revoked | `VendorApprovalTest` |
| Audit log captured for approval/revocation | `VendorApprovalTest` |
| Non-admin calling ApproveVendorForTypeAction → 403 | `VendorApprovalTest` |

### Phase 1.1 Exit Gate

- Admin Filament queue shows pending vendors
- Per-type approve/revoke buttons call correct Action classes
- `vendor_approved_product_types` row created + Spatie permissions granted on approve
- Audit log row created for every approval/revocation
- All Pest tests pass

---

## Cross-cutting Concerns

### Audit Logging

Every state transition and admin action writes to `audit_logs` via `spatie/laravel-activitylog` (`rmsramos/activitylog` package — installed):

```php
activity('vendor_approval')
    ->causedBy($admin)
    ->performedOn($vendorProfile)
    ->withProperties(['product_type' => $type, 'action' => 'approved'])
    ->log('vendor_approved_for_type');
```

### Signed Document URLs

`VendorDocument` signed URL generation (15-minute TTL):

```php
Storage::disk('s3-private')->temporaryUrl($document->file_path, now()->addMinutes(15));
```

Admin Filament view uses this signed URL — document never served publicly.

### ApiResponse Envelope

All API responses use the `ApiResponse` envelope: `{ data, meta, errors }`. Shared helper or trait in `app/Modules/Shared/Http/`.

---

## Complexity Justification

No constitution violations. No complexity table required.

---

## Research Reference

All NEEDS CLARIFICATION markers resolved in [research.md](research.md):

| Research Item | Decision |
|---|---|
| R1 — Auth mode | Sanctum dual-mode: token (mobile) + SPA cookie (web) |
| R2 — OTP strategy | Redis-based stub (`000000`) in Phase 1; real gateway in Phase 5 |
| R3 — Document storage | Direct S3 via `Storage::disk('s3-private')`; NOT MediaLibrary |
| R4 — Spatie permissions | Direct user permissions, not role-based; 12 per-type permissions |
| R5 — Activity log package | `rmsramos/activitylog` (installed); update `10_Package_List.md` |
| R6 — Bilingual strategy | `SetLocaleMiddleware` reads `Accept-Language` header |
| R7 — Event firing | `DB::afterCommit()` inside every transaction |

**New — Phase 0.2** (added 2026-04-28):

| Research Item | Decision |
|---|---|
| R8 — MoneyCast contract | Custom `CastsAttributes` implementation in `Shared/Domain/Casts/`; constructor receives column pair; null-safe |
| R9 — Migration dependency order | `users` (Laravel default) → `vendor_profiles` → `vendor_documents` → `vendor_approved_product_types` → `vendor_business_hours` → `customer_profiles` → `customer_addresses` → `user_devices` → `two_factor_secrets`; `vendor_coverage_areas` owned by Geography (already migrated before Identity) |
