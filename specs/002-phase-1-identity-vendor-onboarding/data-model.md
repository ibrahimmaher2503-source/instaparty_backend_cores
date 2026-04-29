# Data Model: Phase 1 — Identity & Vendor Onboarding

**Date**: 2026-04-27
**Status**: Migrations complete. Models to be built.

---

## Entity Map

### User (extends Authenticatable)

**Table**: `users` (framework-managed + Spatie HasRoles)
**Traits**: `HasRoles`, `HasApiTokens`, `HasPublicId`, `SoftDeletes`, `HasFactory`
**Public identifier**: `public_id` CHAR(26) ULID

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | internal |
| public_id | CHAR(26) UNIQUE | ULID, exposed in API |
| name | VARCHAR(255) | display name |
| email | VARCHAR(255) UNIQUE NULL | optional (phone-only flow) |
| phone_e164 | VARCHAR(20) UNIQUE NULL | E.164 format |
| phone_verified_at | TIMESTAMP NULL | set by VerifyPhoneAction |
| email_verified_at | TIMESTAMP NULL | |
| password | VARCHAR(255) | hashed |
| preferred_locale | CHAR(5) DEFAULT 'ar' | 'en' or 'ar' |
| created_at, updated_at, deleted_at | TIMESTAMPS | soft delete |

**Relationships**:
- `hasOne(VendorProfile)` 
- `hasOne(CustomerProfile)`
- `hasMany(UserDevice)`
- `hasOne(TwoFactorSecret)`

**Scopes**:
- `scopeVendors()` — whereHas role vendor
- `scopeCustomers()` — whereHas role customer
- `scopePending()` — via vendor profile join

---

### VendorProfile

**Table**: `vendor_profiles`
**Traits**: `HasPublicId`, `SoftDeletes`, `HasTranslations`, `HasMedia` (for logo/cover — Phase 2)
**Translatable**: `['business_name', 'bio', 'address_line', 'rejection_reason']`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) UNIQUE | ULID |
| user_id | BIGINT FK users | UNIQUE |
| business_name | JSON | `{"en": "...", "ar": "..."}` |
| slug | VARCHAR(160) UNIQUE | auto-generated from business_name.en |
| bio | JSON NULL | translatable |
| logo_path | VARCHAR(512) NULL | Phase 2 |
| cover_path | VARCHAR(512) NULL | Phase 2 |
| business_type | ENUM(individual,company,establishment) | |
| commercial_register_no | VARCHAR(50) NULL | |
| tax_id | VARCHAR(50) NULL | |
| national_id | VARCHAR(50) NULL | |
| primary_governorate_id | BIGINT FK governorates | |
| primary_city_id | BIGINT FK cities | |
| address_line | JSON NULL | translatable |
| latitude | DECIMAL(10,7) NULL | geo — decimal OK (not money) |
| longitude | DECIMAL(10,7) NULL | |
| approval_status | ENUM(pending,approved,rejected,suspended) DEFAULT pending | |
| approved_at | TIMESTAMP NULL | |
| approved_by | BIGINT FK users NULL | |
| rejected_at | TIMESTAMP NULL | |
| rejected_by | BIGINT FK users NULL | |
| suspended_at | TIMESTAMP NULL | |
| suspended_by | BIGINT FK users NULL | |
| rejection_reason | JSON NULL | translatable |
| bank_name | VARCHAR(120) NULL | |
| bank_account_holder | VARCHAR(160) NULL | |
| bank_iban | VARCHAR(34) NULL | |
| bank_swift_bic | VARCHAR(11) NULL | |
| bank_branch | VARCHAR(120) NULL | |
| rating_avg | DECIMAL(3,2) DEFAULT 0 | updated by Reviews module (Phase 5) |
| rating_count | UNSIGN INT DEFAULT 0 | |
| response_time_avg_minutes | UNSIGN INT NULL | |
| created_at, updated_at, deleted_at | TIMESTAMPS | |

**Relationships**:
- `belongsTo(User)`
- `hasMany(VendorDocument)`
- `hasMany(VendorApprovedProductType)` + `approvedTypes()` scope (active only)
- `hasMany(VendorBusinessHour)`
- `hasMany(VendorCoverageArea)`

**Scopes**:
- `scopePending()` — where approval_status = pending
- `scopeApproved()` — where approval_status = approved
- `scopeApprovedForType(ProductType $type)` — joins vendor_approved_product_types

**Casts**:
- `approval_status` → `ApprovalStatus` enum
- `business_type` → `BusinessType` enum

---

### VendorDocument

**Table**: `vendor_documents`
**Traits**: `HasPublicId`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) UNIQUE | ULID |
| vendor_profile_id | BIGINT FK vendor_profiles | restrictOnDelete |
| doc_type | ENUM(cr,tax_card,national_id,iban_proof,other) | |
| file_path | VARCHAR(512) | S3 path |
| file_name | VARCHAR(255) | original filename |
| status | ENUM(pending,approved,rejected) DEFAULT pending | |
| reviewed_at | TIMESTAMP NULL | |
| reviewed_by | BIGINT FK users NULL | nullOnDelete |
| review_notes | JSON NULL | translatable |
| created_at, updated_at | TIMESTAMPS | |

**Casts**: `doc_type` → `DocumentType`, `status` → `DocumentStatus` enum

---

### VendorApprovedProductType

**Table**: `vendor_approved_product_types`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| vendor_profile_id | BIGINT FK vendor_profiles | |
| product_type | ENUM(rental,sale,digital) | |
| approved_at | TIMESTAMP | useCurrent() |
| approved_by | BIGINT FK users NULL | |
| revoked_at | TIMESTAMP NULL | NULL = active approval |
| revoked_by | BIGINT FK users NULL | |
| revoke_reason | JSON NULL | |
| created_at, updated_at | TIMESTAMPS | |

**UNIQUE**: `(vendor_profile_id, product_type, revoked_at)`
**Index**: `(product_type)`

**Scopes**:
- `scopeActive()` — where revoked_at IS NULL

---

### VendorBusinessHour

**Table**: `vendor_business_hours`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| vendor_profile_id | BIGINT FK vendor_profiles | |
| day_of_week | TINYINT UNSIGNED | 0=Sunday … 6=Saturday |
| opens_at | TIME NULL | NULL = closed |
| closes_at | TIME NULL | |
| created_at, updated_at | TIMESTAMPS | |

**UNIQUE**: `(vendor_profile_id, day_of_week)`

---

### VendorCoverageArea

**Table**: `vendor_coverage_areas` (migration owned by Geography module)

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| vendor_profile_id | BIGINT FK vendor_profiles | |
| city_id | BIGINT FK cities | no Geography model import |
| delivery_fee_minor | BIGINT UNSIGNED DEFAULT 0 | |
| delivery_fee_currency | CHAR(3) DEFAULT 'EGP' | |
| min_order_minor | BIGINT UNSIGNED DEFAULT 0 | |
| min_order_currency | CHAR(3) DEFAULT 'EGP' | |
| created_at, updated_at | TIMESTAMPS | |

**UNIQUE**: `(vendor_profile_id, city_id)`

**Casts**: `delivery_fee_minor` + `delivery_fee_currency` → `MoneyCast` (`delivery_fee`)
         `min_order_minor` + `min_order_currency` → `MoneyCast` (`min_order`)

---

### CustomerProfile

**Table**: `customer_profiles`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | identified via user.public_id |
| user_id | BIGINT FK users UNIQUE | |
| date_of_birth | DATE NULL | |
| gender | ENUM(male,female,prefer_not_to_say) NULL | |
| how_heard_about_us | VARCHAR(120) NULL | |
| children | JSON NULL | `[{name, dob, gender}]` |
| accepts_marketing | BOOLEAN DEFAULT true | |
| created_at, updated_at | TIMESTAMPS | |

---

### CustomerAddress

**Table**: `customer_addresses`
**Traits**: `HasPublicId`, `SoftDeletes`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) UNIQUE | ULID |
| user_id | BIGINT FK users | |
| city_id | BIGINT FK cities | no Geography model import |
| label | VARCHAR(60) | e.g. "Home", "Work" |
| address_line | VARCHAR(255) | |
| building | VARCHAR(50) NULL | |
| floor | VARCHAR(20) NULL | |
| apartment | VARCHAR(20) NULL | |
| landmark | VARCHAR(255) NULL | |
| latitude | DECIMAL(10,7) NULL | |
| longitude | DECIMAL(10,7) NULL | |
| recipient_name | VARCHAR(120) | |
| recipient_phone_e164 | VARCHAR(20) | |
| is_default | BOOLEAN DEFAULT false | |
| created_at, updated_at, deleted_at | TIMESTAMPS | |

**Index**: `(user_id, deleted_at)`

---

### UserDevice

**Table**: `user_devices`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK users | |
| platform | ENUM(ios,android,web) | |
| fcm_token | VARCHAR(255) | |
| device_id | VARCHAR(190) NULL | |
| last_seen_at | TIMESTAMP NULL | |
| created_at, updated_at | TIMESTAMPS | |

**UNIQUE**: `(user_id, fcm_token)`

---

### TwoFactorSecret (stub model — no Action in Phase 1)

**Table**: `two_factor_secrets`

| Field | Notes |
|---|---|
| user_id | BIGINT FK users UNIQUE |
| secret_encrypted | TEXT encrypted |
| recovery_codes_encrypted | TEXT NULL |
| confirmed_at | TIMESTAMP NULL |

---

## State Transitions

### VendorProfile.approval_status

```
pending → approved    (ApproveVendorProfileAction)
pending → rejected    (RejectVendorProfileAction — admin)
approved → suspended  (SuspendVendorAction — admin, Phase 1 scope per FR-I14)
rejected → pending    (vendor resubmits — deferred to Phase 2)
```

### VendorDocument.status

```
pending → approved    (admin reviews document)
pending → rejected    (admin reviews document)
```

### VendorApprovedProductType

```
(no row) → active row (approved_at set, revoked_at NULL)  → ApproveVendorForTypeAction
active row → revoked (revoked_at set)                     → RevokeVendorTypeAction
```

---

## Domain Events

| Event | Payload | Fired by |
|---|---|---|
| `CustomerRegistered` | `{user_id, public_id, email, locale}` | RegisterCustomerAction |
| `VendorRegistered` | `{user_id, vendor_profile_id, public_id}` | RegisterVendorAction |
| `VendorApproved` | `{vendor_profile_id, approved_by}` | ApproveVendorProfileAction |
| `VendorApprovedForType` | `{vendor_profile_id, product_type, approved_by}` | ApproveVendorForTypeAction |
| `VendorRejected` | `{vendor_profile_id, reason, rejected_by}` | RejectVendorProfileAction |
| `VendorSuspended` | `{vendor_profile_id, suspended_by}` | SuspendVendorAction — triggers RevokeAllVendorTypesOnStatusChange |
| `VendorTypeRevoked` | `{vendor_profile_id, product_type, reason, revoked_by}` | RevokeVendorTypeAction |
| `PhoneVerified` | `{user_id}` | VerifyPhoneAction |

All fire via `DB::afterCommit()` inside their respective transaction.

---

## Spatie Permissions Seeded

```php
// Per product type (12 permissions total)
service.create.rental.own   service.create.sale.own   service.create.digital.own
service.update.rental.own   service.update.sale.own   service.update.digital.own
service.delete.rental.own   service.delete.sale.own   service.delete.digital.own
service.publish.rental.own  service.publish.sale.own  service.publish.digital.own

// Admin permissions (generated by Shield)
approve_vendor_profile
approve_vendor_for_rental
approve_vendor_for_sale
approve_vendor_for_digital
revoke_vendor_type
suspend_vendor
```

Roles: `customer`, `vendor`, `admin`
- `vendor` role has NO service permissions by default (granted per type via ApproveVendorForTypeAction)
- `admin` role gets all Shield-generated permissions
