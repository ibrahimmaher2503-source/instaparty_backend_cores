# Implementation Plan: Phase 1 — Identity & Vendor Onboarding

**Branch**: `002-identity-vendor-onboarding` | **Date**: 2026-04-27 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/002-phase-1-identity-vendor-onboarding/spec.md`

---

## Summary

Build the complete Identity module slice: customer registration + auth, vendor registration + document upload, admin per-product-type vendor approval, and the four Filament resources for admin management. All 10 Identity migrations are already in place. This phase delivers the foundation layer that Catalog (Phase 2) depends on for vendor permission checks.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Sanctum 4.x, Spatie Permission 6.x, Spatie Translatable 6.x, Spatie MediaLibrary 11.x, Spatie Data 4.x, Filament 3.x
**Storage**: MySQL 8 (MariaDB 11), S3-compatible (MinIO dev / DigitalOcean Spaces prod), Redis (queues)
**Testing**: Pest 3.x + pest-plugin-laravel + pest-plugin-arch
**Target Platform**: Linux server (Docker), API consumed by Next.js SPA + Flutter mobile
**Project Type**: Modular monolith web service + Filament admin panel
**Performance Goals**: Auth endpoints <200ms p95; Filament admin pages load <1s
**Constraints**: EN+AR bilingual required on all user-facing strings; Sanctum token + SPA cookie dual-mode auth; domain events fire only after DB commit
**Scale/Scope**: Phase 1 target: ~50 vendors, ~200 customers at soft launch

---

## Constitution Check

*Gates verified against CLAUDE.md (project constitution)*

| Gate | Status | Notes |
|---|---|---|
| Modular monolith — new code under `app/Modules/Identity/` | ✅ PASS | All code targets Identity module path |
| Cross-module via events/contracts only — no direct Model imports | ✅ PASS | Geography referenced via FK only (city_id); no `Geography\City` import |
| Models hold relationships/casts/scopes ONLY | ✅ PASS | Business logic in Actions per plan |
| Controllers max 3-line action body | ✅ PASS | Controllers delegate to Actions immediately |
| Money: integer minor units (delivery_fee_minor) | ✅ PASS | vendor_coverage_areas uses `_minor` + `_currency` |
| IDs: `public_id` CHAR(26) ULID | ✅ PASS | On users, vendor_profiles, vendor_documents, customer_addresses (not on lookup/pivot tables) |
| Migrations: utf8mb4 + utf8mb4_unicode_ci | ✅ PASS | All 10 Identity migrations verified |
| Domain events via `DB::afterCommit` | ✅ PASS | Enforced in Action contracts |
| EN+AR translatable fields as JSON | ✅ PASS | business_name, bio, address_line, rejection_reason on vendor_profiles |
| Append-only tables have no softDeletes | ✅ PASS | No append-only tables in Identity |
| Soft-deletes on allowed tables only | ✅ PASS | users, vendor_profiles, customer_addresses only |
| Filament resources in module's `Filament/Resources/` | ✅ PASS | All 4 resources target Identity Filament folder |
| `shield:generate --all` after new resources | ✅ PLANNED | Noted in Layer 6 plan |
| No Phase 2 features | ✅ PASS | 2FA deferred to Phase 6; sub tiers not touched |

**One pre-implementation flag**: `vendor_documents` migration uses `file_path` (direct S3 path) rather than MediaLibrary morph relation. This is intentional — MediaLibrary is for image galleries (service photos, profile logo). For typed documents with review workflow, direct S3 storage is cleaner. MediaLibrary will still be used for vendor profile `logo_path`/`cover_path` (Phase 2 when vendor updates their profile images). **No schema change needed.**

---

## Project Structure

### Documentation (this feature)

```text
specs/002-phase-1-identity-vendor-onboarding/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   ├── customer-auth.md
│   ├── vendor-registration.md
│   └── admin-vendor-approval.md
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code (Identity module)

```text
app/Modules/Identity/
├── Domain/
│   ├── Models/           User, VendorProfile, VendorDocument, VendorApprovedProductType,
│   │                     VendorBusinessHour, VendorCoverageArea, CustomerProfile,
│   │                     CustomerAddress, UserDevice, TwoFactorSecret
│   ├── Enums/            VendorApprovalStatus, BusinessType, DocumentType
│   ├── Events/           CustomerRegistered, VendorRegistered, VendorApproved,
│   │                     VendorApprovedForType, VendorRejected, VendorTypeRevoked, PhoneVerified
│   └── Contracts/        UserRepository, VendorProfileRepository
├── Application/
│   ├── Actions/          RegisterCustomerAction, RegisterVendorAction, LoginAction,
│   │                     LogoutAction, VerifyPhoneAction, ApproveVendorAction,
│   │                     ApproveVendorForTypeAction, RevokeVendorTypeAction,
│   │                     AddCustomerAddressAction
│   ├── DTOs/             RegisterCustomerData, RegisterVendorData, VendorApprovalData
│   └── Listeners/        SendWelcomeNotification, NotifyAdminOfPendingVendor
├── Infrastructure/
│   └── Repositories/     EloquentUserRepository, EloquentVendorProfileRepository
├── Http/
│   ├── Controllers/
│   │   ├── Customer/     RegisterController, LoginController, ProfileController
│   │   └── Vendor/       RegisterController, ProfileController
│   ├── Requests/         RegisterCustomerRequest, RegisterVendorRequest, LoginRequest,
│   │                     UploadVendorDocumentRequest, UpdateVendorProfileRequest,
│   │                     AddCustomerAddressRequest
│   ├── Resources/        UserResource, VendorProfileResource, CustomerProfileResource
│   └── Middleware/       EnsurePhoneVerified
├── Filament/
│   └── Resources/        UserResource, CustomerProfileResource, VendorProfileResource,
│                         VendorApprovalQueueResource
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   └── admin.php
├── Database/
│   ├── Migrations/       (10 files — already in place)
│   └── Factories/        VendorProfileFactory, CustomerProfileFactory
└── Providers/
    └── IdentityServiceProvider.php

tests/
├── Feature/Modules/Identity/
│   ├── RegisterCustomerTest.php
│   ├── RegisterVendorTest.php
│   ├── LoginTest.php
│   ├── PhoneVerificationTest.php
│   ├── ApproveVendorTest.php
│   ├── ApproveVendorForTypeTest.php
│   └── CustomerAddressTest.php
└── Unit/Modules/Identity/
    ├── VendorApprovalStatusEnumTest.php
    └── EloquentVendorProfileRepositoryTest.php
```

---

## Complexity Tracking

No constitution violations. No complexity justification needed.
