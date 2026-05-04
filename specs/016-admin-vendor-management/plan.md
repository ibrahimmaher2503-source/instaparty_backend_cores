---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack
---

# Implementation Plan: Phase 6.4 — Admin Vendor Management (Full CRUD)

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)

## Summary

Extend the existing `VendorProfileResource` (Identity module) with full admin management capabilities: an edit form with EN+AR translatable fields, tabbed detail view (Services, Bookings, Wallet, Withdrawals, Reviews, Activity), per-type revocation (action already exists — surface it correctly), document re-upload, and a new `ImpersonateVendorAction` that writes an audit log entry before returning a Sanctum token. No schema migrations are needed — all four tables (`vendor_profiles`, `vendor_documents`, `vendor_business_hours`, `vendor_coverage_areas`) and the `revoke_reason` JSON column on `vendor_approved_product_types` are already in the locked schema.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Filament v3, spatie/laravel-translatable, spatie/laravel-activitylog, spatie/laravel-permission, spatie/laravel-media-library, Laravel Sanctum
**Storage**: MySQL 8 (existing tables), MinIO/S3 (vendor documents — private bucket)
**Testing**: Pest (Feature tests in `tests/Feature/Modules/Identity/`)
**Target Platform**: Filament admin panel at `/admin` (no customer-facing API changes)
**Project Type**: Admin panel (Filament v3 Resource)
**Performance Goals**: Vendor list renders under 500ms at 1,000 vendors; tabbed view sub-queries all eager-loaded
**Constraints**: All audit writes use existing `activity()` helper (spatie/laravel-activitylog → `activity_log` table); no new packages
**Scale/Scope**: ~1,000 vendors in Phase 1; impersonation is low-frequency admin debugging tool

---

## Constitution Check

*GATE: Must pass before implementation. Re-checked after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I — Modular Monolith | ✅ PASS | All files stay in `app/Modules/Identity/`. No new module needed. |
| II — Three Product Types / match($enum) | ✅ PASS | No type-aware business logic introduced. Type revocation already uses `ProductType` enum. |
| III — Money Discipline | ✅ PASS | No money columns created. Wallet/withdrawal tabs are read-only views of existing money columns. |
| IV — Bilingual EN+AR | ✅ PASS | Edit form uses translatable plugin tabs. All new lang keys added to both `en/identity.php` and `ar/identity.php`. |
| V — Append-Only Tables | ✅ PASS | `audit_logs` / `activity_log` are never updated — only inserted via `activity()` helper. Wallet and commission tabs are read-only. |
| VI — Spec-Driven / ADR Before Code | ✅ PASS | Identity module ADR already accepted (Phase 1.1). No new module, no new ADR required. |
| VII — Test-First for Critical Paths | ✅ PASS | Impersonation (auth-critical) and revocation (state-changing) require Pest tests on the same day. |
| VIII — Idempotency | ✅ PASS | Impersonation and profile edit are not payment-mutating endpoints — idempotency key not required. |
| IX — Domain Events DB::afterCommit | ✅ PASS | `VendorTypeRevoked` event already fires via `DB::afterCommit` in `RevokeVendorTypeAction` (verified in code). |
| X — Vendor Approval Two-Step Gate | ✅ PASS | Edit form explicitly does NOT touch `approval_status`. Revocation is separate from profile approval. |
| XI — Document Storage — Direct S3 | ✅ PASS | Document re-upload uses `UploadVendorDocumentAction` (existing) + direct S3 `file_path` column. No MediaLibrary for typed documents. |

**No violations. Phase 0 research can proceed.**

---

## Project Structure

### Documentation (this feature)

```text
specs/016-admin-vendor-management/
├── plan.md              ← this file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code — files created or modified

```text
app/Modules/Identity/
├── Application/
│   └── Actions/
│       └── ImpersonateVendorAction.php          [NEW]
├── Filament/
│   └── Resources/
│       ├── VendorProfileResource.php            [MODIFY — add form(), filters, impersonate action, edit route]
│       └── VendorProfileResource/
│           └── Pages/
│               ├── ListVendorProfiles.php       [no change]
│               ├── ViewVendorProfile.php        [MODIFY — add tabbed sections via RelationManagers]
│               └── EditVendorProfile.php        [NEW]
├── Resources/
│   └── lang/
│       ├── en/identity.php                      [MODIFY — add new lang keys]
│       └── ar/identity.php                      [MODIFY — add new lang keys]

tests/
└── Feature/
    └── Modules/
        └── Identity/
            └── AdminVendorManagementTest.php    [NEW]
```

---

## Phase 0: Research

*Resolved unknowns:*

### R-01: Audit trail target — `activity_log` (spatie) vs custom `audit_logs`

**Decision**: Use the existing `activity()` helper → writes to spatie's `activity_log` table (not the custom `audit_logs` table from Cross-cutting).

**Rationale**: All existing actions (`UpdateVendorProfileAction`, `RevokeVendorTypeAction`, `ApproveVendorForTypeAction`) already use `activity()->on(...)->causedBy(...)->log(...)`. Adding a parallel write to the custom `audit_logs` table for the same events would be duplicated infrastructure. The `audit_logs` custom table is used by the Reporting module (Phase 6.0) for high-level operational audit events. Impersonation is the only event in this phase that ALSO writes to `audit_logs` (as a security-critical impersonation trail).

**How to apply**: For profile edits, hours, coverage, document re-upload → `activity()` helper only. For impersonation → `activity()` AND a direct insert into `audit_logs` (action `'vendor_impersonated'`) to ensure it appears in the admin audit report.

---

### R-02: Filament v3 tabbed view page pattern

**Decision**: Use Filament v3 `RelationManager` classes for each tab on the `ViewVendorProfile` page.

**Rationale**: Filament v3 `Resource::view` pages natively support `getRelationManagers()`. Each tab becomes a `RelationManager` subclass with its own `table()` definition. This is the idiomatic Filament pattern — no custom Blade views or `Tabs` hacks needed. Available on the view page via `->pages(['view' => ...])`.

**Tabs to implement as RelationManagers**:
- `ServicesRelationManager` — `services` table filtered by `vendor_profile_id`
- `BookingsRelationManager` — `booking_vendors` join `bookings` filtered by `vendor_profile_id`
- `WalletRelationManager` — `wallets` (single row) + `wallet_ledger` (last 50 entries)
- `WithdrawalsRelationManager` — `withdrawals` filtered by `wallet.owner_id`
- `ReviewsRelationManager` — `vendor_reviews` filtered by `vendor_profile_id`
- `ActivityRelationManager` — `activity_log` filtered by `subject_type = VendorProfile` and `subject_id`

**Cross-module access**: RelationManagers inside Identity module MUST NOT import Eloquent models from other modules directly. They access data via raw DB queries or via the VendorProfile's relationships. VendorProfile model's relationships to `services`, `bookings`, etc. must go through approved cross-module patterns (relationship methods that return query builders are acceptable for read-only Filament display).

**Alternative rejected**: Custom `Tabs` component in the infolist — requires manual query logic, no built-in pagination, more boilerplate.

---

### R-03: Impersonation implementation with Sanctum

**Decision**: `ImpersonateVendorAction::execute(VendorProfile $target, User $admin): string` returns a plain-text Sanctum API token for the vendor user, scoped with `['impersonation']` ability, expiring in 30 minutes.

**Rationale**: Sanctum `createToken()` with abilities and expiry is the cleanest non-session impersonation. The returned token is displayed in a Filament modal for the admin to copy — this is a debugging tool, not a seamless session hijack. Token expiry limits blast radius.

**Audit requirement**: The `audit_logs` entry is written INSIDE the `DB::transaction`, before the token is created, so that if the DB write fails the token is not returned. Token creation happens AFTER `DB::transaction` commits — `DB::afterCommit(fn() => $token = $vendor->user->createToken(...))`. Actually, token creation must happen synchronously and returned. Pattern: write audit log inside transaction, then create token after commit via callback that stores it in a local variable.

**Permission gate**: `impersonate_vendor` — Shield-managed. Only `super_admin` role gets this by default.

**Rejected**: Session-based `Auth::loginUsingId()` — server-side session manipulation in Filament context; doesn't work for API clients (Flutter).

---

### R-04: Business hours and coverage area edit pattern

**Decision**: Business hours use the existing `UpsertVendorBusinessHoursAction` (full replace of all 7 rows). Coverage areas use a new `AdminReplaceVendorCoverageAction` (full replace of all city rows for a vendor). Both are wrapped in `DB::transaction` and produce an `activity()` log entry.

**Rationale**: `UpsertVendorBusinessHoursAction` already exists. A new `AdminReplaceVendorCoverageAction` is needed because the existing `AddVendorCoverageAreaAction` adds one area at a time; admin needs full replace for bulk edit. The Filament edit form uses `Repeater` for hours and `CheckboxList` for coverage cities.

---

### R-05: Document re-upload pattern

**Decision**: Re-upload reuses the existing `UploadVendorDocumentAction` with `$replaceExisting = true` flag. The action finds the existing `vendor_documents` row by `(vendor_profile_id, doc_type)`, updates `file_path` and `file_name`, and optionally deletes the old S3 object.

**Rationale**: Direct S3 storage (per Constitution Principle XI) — no MediaLibrary for typed vendor documents. The admin re-upload goes through the same action class as vendor self-upload to avoid duplicating S3 logic.

---

## Phase 1: Design & Data Model

See `data-model.md` for entity definitions.

### Key design decisions

**1. Edit form scope**

The `VendorProfileResource::form()` exposes only business fields:
- `business_name` (JSON tabs EN/AR) — via translatable plugin
- `bio` (JSON tabs EN/AR)
- `business_type` (ENUM select — individual / company)
- `primary_governorate_id` → `primary_city_id` (cascading selects)
- `address_line` (JSON tabs EN/AR)
- `commercial_register_no`, `tax_id`, `national_id` (text inputs)
- Bank fields: `bank_holder_name`, `bank_iban`, `bank_swift`, `bank_name`

**NOT editable via this form**: `approval_status`, `approved_at`, `approved_by`, `rating_avg`, `rating_count` (computed), `slug` (locked after creation), `user_id`.

**2. Impersonation modal**

`ImpersonateVendorAction` in `VendorProfileResource` table actions shows a confirmation modal (`->requiresConfirmation()`) with warning text, then calls the action and displays the token in a `Notification::make()->body($token)->info()->persistent()->send()` so admin can copy it.

**3. Tabbed view RelationManagers**

Each RelationManager is read-only (no `canCreate`, `canEdit`, `canDelete`). Data comes from relationships defined on `VendorProfile` model:
- `VendorProfile::services()` — hasMany via `vendor_profile_id` (cross-module read-only query)
- `VendorProfile::bookingVendors()` → load `booking` relation for status display
- `VendorProfile::wallets()` — cross-module via Settlement module contract
- `VendorProfile::withdrawals()` — cross-module via Settlement module contract

**Cross-module boundary enforcement**: RelationManagers use `DB::table()` or raw relationship methods rather than importing Settlement/Booking Eloquent models. The VendorProfile model MAY define lightweight relationship methods for read-only joins since they return query builders, not model instances — this is acceptable for the admin view layer per project conventions.

**4. Permissions added**

- `manage_vendor_profile` — for edit (already exists as part of Shield generation)
- `impersonate_vendor` — NEW, must be added to permissions seeder and Shield configuration
- `re_upload_vendor_document` — NEW

---

## Implementation Sequence (Day 1 — 1 day total)

### Morning: Actions (3h)

1. **`ImpersonateVendorAction`** — write audit log to `audit_logs` (direct insert) + `activity()` log, then create Sanctum token with `impersonation` ability, 30-minute expiry
2. **`AdminReplaceVendorCoverageAction`** — full delete-reinsert of `vendor_coverage_areas` with activity log
3. **Verify** `UpsertVendorBusinessHoursAction` works for admin context (likely no change needed)
4. **Verify** `UpdateVendorProfileAction` logs ALL field changes (not just sensitive) — add broader activity log call if missing

### Afternoon: Filament (3h)

5. **`VendorProfileResource::form()`** — add edit form with translatable tabs, bank fields section, location cascade, business hours Repeater, coverage areas CheckboxList
6. **`EditVendorProfile` page** — standard Filament edit page, registers at `'edit' => Pages\EditVendorProfile::route('/{record}/edit')`
7. **RelationManagers** — Services, Bookings, Wallet, Reviews, Activity (read-only tables)
8. **`ViewVendorProfile` update** — wire up RelationManagers via `getRelationManagers()`
9. **Impersonate action** wired into `VendorProfileResource` table actions row
10. **Document re-upload section** in the Documents tab
11. **Filters** — add `product_type` approval filter and `governorate_id` filter to the list table
12. **Lang keys** — add all new strings to `en/identity.php` and `ar/identity.php`
13. **`php artisan shield:generate --all`**

### End of Day: Tests (1h)

14. **`AdminVendorManagementTest.php`** — Pest feature tests:
    - Edit preserves `approval_status`
    - Impersonation creates `audit_logs` entry + returns token
    - Unauthorized impersonation returns 403
    - Revocation with empty reason is rejected by form validation
    - Revocation stores reason in `revoke_reason` JSON column
    - Coverage area replace is transactional

---

## Cut-list (if behind schedule)

| Item | Defer to | Impact |
|------|----------|--------|
| Wallet + Withdrawals tab in view | Phase 7.0 hardening | Admin can still view wallets via Settlement resource |
| Activity tab (RelationManager) | Phase 7.0 | Activity log still exists in DB |
| Document re-upload UI | Phase 7.0 | Docs still viewable; admin contacts Ibrahim to replace manually |
| `AdminReplaceVendorCoverageAction` | Phase 7.0 | Use existing `AddVendorCoverageAreaAction` for single-area changes |

**Non-deferrable** (exit criteria hard requirements):
- Edit form (profile + hours + coverage)
- Impersonation with mandatory audit log
- Type revocation with reason (already implemented; just needs correct Filament wiring)

---

## Exit Criteria

- ✅ Admin edits vendor business name (EN+AR), saves, and `vendor_profiles.approval_status` is unchanged
- ✅ Impersonation always creates `audit_logs` row with `action = 'vendor_impersonated'` before token is returned
- ✅ Per-type revocation rejects empty reason (form validation) and stores non-empty reason in `vendor_approved_product_types.revoke_reason`
- ✅ `php artisan shield:generate --all` runs without error after new permissions are added
- ✅ All Pest tests in `AdminVendorManagementTest.php` pass (6 test cases minimum)
- ✅ EN and AR admin labels render correctly in Filament locale switcher
