# ADR-0003 — Identity Module

- **Status:** Accepted
- **Date:** 2026-04-26
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-0-foundation, phase-1-identity
- **Related:**
  - [`0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §6.2 (Vendor Journey), §7.5 (Vendor Service Management)
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §3 (Authentication)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §3 (Identity, 8 tables)

---

## 1. السياق / Context

**AR:** InstaParty محتاج system موحد للهوية يخدم 3 أدوار (customer, vendor, admin) بمنطق صلاحيات مختلف لكل دور، **مع pattern للموردين بـ approval per product type** (vendor ممكن يكون معتمد للـ rental بس مش الـ digital). بعض المستخدمين هيكون عندهم أدوار متعددة (مثلاً customer + vendor).

**EN:** InstaParty needs a unified identity system serving 3 roles with **per-product-type vendor approval** (vendor can be approved for rental only, not digital). Some users will hold multiple roles (e.g., customer + vendor).

**Phase:** 0 (migrations) + 1 (models + Actions + Filament)
**Built in:** Week 1 Day 4 (migrations) + Week 2 Days 6–8 (full slice)

---

## 2. المسؤوليات / Responsibilities

### الـ module ده مسؤول عن:

- User registration (customer + vendor flows)
- Authentication (Sanctum SPA cookies + tokens)
- Roles & permissions (Spatie + per-type vendor scopes)
- Vendor profile lifecycle (registration → approval → suspension)
- Per-type vendor approval (`vendor_approved_product_types`)
- Customer profile data (name, DOB, children, marketing prefs)
- Customer address book (`customer_addresses`)
- Phone/email verification flows
- Two-factor auth (TOTP for admins)
- Push device registration (`user_devices`)

### الـ module ده مش مسؤول عن:

- Service catalog → Catalog module
- Booking creation → Booking module
- Wallets → Settlement module
- Chat → Communication module
- Geography lookups (cities, regions) → Geography module

---

## 3. الجداول المملوكة / Tables Owned

| Table | الغرض / Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `users` | Single user table for all 3 roles | Yes | No |
| `vendor_profiles` | 1:1 to user, holds vendor business data | Yes | No |
| `vendor_documents` | CR / tax card / IBAN proof uploads | No | No |
| `vendor_approved_product_types` | Per-type approval log | No | No (revoked_at flag) |
| `vendor_business_hours` | Weekly schedule per vendor | No | No |
| `vendor_coverage_areas` | Cities the vendor serves + delivery fees | No | No |
| `customer_profiles` | 1:1 to user, customer-only data | No | No |
| `user_devices` | FCM tokens for push | No | No |
| `two_factor_secrets` | TOTP secrets, encrypted | No | No |
| `customer_addresses` | Customer address book (Phase-1 ready) | Yes | No |

**Foreign key dependencies (في modules تانية):**

| FK | المرجع / References | Module |
|---|---|---|
| `vendor_profiles.primary_governorate_id` | `governorates.id` | Geography |
| `vendor_profiles.primary_city_id` | `cities.id` | Geography |
| `vendor_coverage_areas.city_id` | `cities.id` | Geography |
| `customer_addresses.city_id` | `cities.id` | Geography |
| `vendor_approved_product_types.approved_by` | `users.id` (admin) | Identity (self) |

> **Migration order:** Geography → Identity. Migrations 4–13 في Phase 0 لازم يجو بعد Geography.

---

## 4. هل الـ module type-aware؟

### ☑ Cross-type — مع استثناء واحد

الـ module نفسه cross-cutting infrastructure. لكن `vendor_approved_product_types` بتسجل approval لكل type على حدة — ده **type-related metadata**، مش type-aware logic.

**ليه ده مش type-aware:**
- مفيش `RentalUser` و `SaleUser`. كل المستخدمين بنفس الـ shape.
- مفيش per-type Form Request للـ vendor signup. واحد بس.
- Authentication flow واحد لكل المنتجات.

**اللي بياخد type في الاعتبار:**
- صلاحيات Spatie: `service.create.rental.own`, `service.create.sale.own`, `service.create.digital.own`
- Filament: Vendor Approval Queue فيها 3 أزرار approval (واحد لكل type)
- لكن دي UI/permission concerns، مش core domain logic

**النتيجة:** مفيش `match($enum)` في Identity Actions. مفيش 3 versions من `RegisterVendorAction`. واحد بس.

---

## 5. Layer Layout

```
app/Modules/Identity/
├── Domain/
│   ├── Models/
│   │   ├── User.php (extends Authenticatable, HasRoles, HasUlids)
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
│   │   ├── VendorApprovalStatus.php (Pending|Approved|Rejected|Suspended)
│   │   ├── BusinessType.php (Individual|Company)
│   │   └── DocumentType.php (Cr|TaxCard|NationalId|IbanProof|Other)
│   ├── Events/
│   │   ├── CustomerRegistered.php
│   │   ├── VendorRegistered.php
│   │   ├── VendorApproved.php
│   │   ├── VendorApprovedForType.php
│   │   ├── VendorRejected.php
│   │   ├── VendorTypeRevoked.php
│   │   └── PhoneVerified.php
│   └── Contracts/
│       ├── UserRepository.php
│       └── VendorProfileRepository.php
├── Application/
│   ├── Actions/
│   │   ├── RegisterCustomerAction.php
│   │   ├── RegisterVendorAction.php
│   │   ├── LoginAction.php
│   │   ├── LogoutAction.php
│   │   ├── VerifyPhoneAction.php
│   │   ├── ApproveVendorAction.php
│   │   ├── ApproveVendorForTypeAction.php
│   │   ├── RevokeVendorTypeAction.php
│   │   ├── EnableTwoFactorAction.php
│   │   └── AddCustomerAddressAction.php
│   ├── Services/
│   │   └── (none — Actions are sufficient)
│   ├── DTOs/
│   │   ├── RegisterCustomerData.php
│   │   ├── RegisterVendorData.php
│   │   └── VendorApprovalData.php
│   └── Listeners/
│       ├── SendWelcomeNotification.php
│       └── NotifyAdminOfPendingVendor.php
├── Infrastructure/
│   └── Repositories/
│       ├── EloquentUserRepository.php
│       └── EloquentVendorProfileRepository.php
├── Http/
│   ├── Controllers/
│   │   ├── Customer/
│   │   │   ├── RegisterController.php
│   │   │   ├── LoginController.php
│   │   │   └── ProfileController.php
│   │   └── Vendor/
│   │       ├── RegisterController.php
│   │       └── ProfileController.php
│   ├── Requests/
│   │   ├── RegisterCustomerRequest.php
│   │   ├── RegisterVendorRequest.php
│   │   ├── LoginRequest.php
│   │   └── UploadVendorDocumentRequest.php
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── VendorProfileResource.php
│   │   └── CustomerProfileResource.php
│   └── Middleware/
│       └── EnsurePhoneVerified.php
├── Filament/
│   └── Resources/
│       ├── UserResource.php
│       ├── VendorProfileResource.php
│       ├── VendorApprovalQueueResource.php
│       └── CustomerProfileResource.php (read-only)
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   └── admin.php
├── Database/
│   ├── Migrations/ (10 files)
│   ├── Factories/
│   └── Seeders/
│       └── AdminUserSeeder.php
├── Resources/
│   └── lang/{en,ar}/identity.php
└── Providers/
    └── IdentityServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 Single `users` table for all roles

**القرار:** جدول واحد بـ Spatie Roles، مش جداول منفصلة (`customers`, `vendors`, `admins`).

**البديل:** Polymorphic users بـ `userable_type`/`userable_id`، أو three independent tables.

**ليه؟**
- User ممكن يكون عنده multiple roles (customer + vendor شائع جداً في marketplaces)
- Three tables = duplicate auth logic 3 مرات
- Polymorphic users = أكثر تعقيد بدون فائدة وقتية في Phase 1

### 6.2 Per-type vendor approval في جدول منفصل

**القرار:** `vendor_approved_product_types` table مع `(vendor_profile_id, product_type, revoked_at)` UNIQUE.

**البديل:** عمود JSON على `vendor_profiles` يحتوي `approved_types: ["rental", "sale"]`.

**ليه؟**
- Audit trail: كل approval/revocation له timestamp + admin user
- لو أتى admin اعتمد vendor للـ digital يوم 1 ثم سحب الموافقة يوم 30، الـ JSON ما يحتفظش بالـ history
- Spatie permission gates أبسط: `Gate::authorize('service.create.rental.own', $vendor)` يقرأ من جدول معروف

**Clarification 2026-04-27 — Approval sequencing & auto-revocation (Ibrahim):**

1. **Per-type approval requires overall profile approval first.** `ApproveVendorForTypeAction` MUST assert `vendor_profile.approval_status === approved` and throw a `422` if the profile is still `pending` or `rejected`. The Filament per-type buttons are only visible/enabled when `approval_status = approved`.

2. **Auto-revocation on suspension or rejection.** When `approval_status` transitions to `suspended` or `rejected`, ALL rows in `vendor_approved_product_types` with `revoked_at IS NULL` for that vendor MUST be auto-revoked (bulk `revoked_at = now()`, `revoked_by = acting admin`) and the corresponding Spatie permissions revoked. This is implemented as a **listener** (`RevokeAllVendorTypesOnStatusChange`) registered on the `VendorSuspended` and `VendorRejected` events — NOT inline in the Action.

   Listener fires via `DB::afterCommit` just like all other domain event listeners. It calls `RevokeVendorTypeAction` for each active type, or bulk-revokes directly for performance if the vendor has 3 active types.

3. **Cascading effect on Catalog (Phase 2 note):** When types are auto-revoked, the vendor's published services of those types must be taken offline. This cascade is the responsibility of a **Catalog listener** on `VendorTypeRevoked` (Phase 2 implementation). The Identity module fires the events — Catalog consumes them.

### 6.3 Vendor document storage: direct S3, not MediaLibrary

**القرار:** `vendor_documents` table stores `file_path` + `file_name` directly. Files stored on `s3-private` disk via `Storage::disk('s3-private')->putFileAs(...)`. Access via signed URLs (15-min TTL). MediaLibrary is NOT used for documents.

**البديل:** `HasMedia` trait on `VendorDocument` with a `documents` collection in MediaLibrary.

**ليه؟**
- Documents are entities with review state (`status`, `reviewed_by`, `reviewed_at`, `review_notes`) — the review workflow lives on the `vendor_documents` table itself.
- MediaLibrary's `media` morph table would require an extra join for every document query with no functional benefit.
- MediaLibrary is reserved for gallery-style use cases: service image galleries (`gallery` collection with thumb/medium/large conversions) and profile avatars/covers.
- Settlement proofs follow the same pattern (explicit columns on `withdrawals`).
- Chat media is in Firebase Storage — entirely separate from app storage.

**Reference:** `docs/specs/02_Tech_Decisions.md` §8 (Per-asset storage strategy).

### 6.4 `customer_addresses` snapshot على `booking_addresses`

**القرار:** عند إنشاء booking، نـ snapshot الـ address لـ `booking_addresses`، مش FK للـ `customer_addresses.id`.

**البديل:** FK مباشر من `bookings.address_id` لـ `customer_addresses`.

**ليه؟**
- Booking history لازم يكون immutable. لو customer غير address بعد ما حجز، الـ booking لازم يحتفظ بالـ original delivery address.
- Soft-deleting `customer_addresses` ما يأثرش على bookings تاريخية.

### 6.5 TOTP secrets في جدول منفصل

**القرار:** `two_factor_secrets` table، مش عمود على `users`.

**البديل:** `two_factor_secret` و `two_factor_recovery_codes` على `users`.

**ليه؟**
- Separation of concerns: secrets encrypted في مكانهم.
- Rotation أسهل: مسح الصف وإعادة إنشاء، بدل update.
- لو في Phase 2 ضفنا backup methods (SMS, hardware key)، الـ schema جاهز للتوسع.

---

## 7. Inter-Module Communication

### Events بنطلقها:

| Event | When | Payload |
|---|---|---|
| `Identity\\CustomerRegistered` | بعد commit للـ customer | `{user_id, email, locale}` |
| `Identity\\VendorRegistered` | بعد commit للـ vendor | `{user_id, vendor_profile_id}` |
| `Identity\\VendorApproved` | overall profile approved | `{vendor_profile_id, approved_by}` |
| `Identity\\VendorApprovedForType` | per-type approval | `{vendor_profile_id, product_type, approved_by}` |
| `Identity\\VendorRejected` | overall profile rejected | `{vendor_profile_id, reason, rejected_by}` — triggers auto-revocation listener |
| `Identity\\VendorSuspended` | vendor suspended | `{vendor_profile_id, reason, suspended_by}` — triggers auto-revocation listener |
| `Identity\\VendorTypeRevoked` | single-type revocation | `{vendor_profile_id, product_type, reason, revoked_by}` |
| `Identity\\PhoneVerified` | OTP confirmed | `{user_id}` |

### Events بنستهلكها:

| Event | Source Module | Action |
|---|---|---|
| (none in Phase 1) | — | Identity is upstream of everything |

### Public Contracts (بنوفرها):

| Contract | الغرض / Purpose | Implementation |
|---|---|---|
| `Identity\\Domain\\Contracts\\UserRepository` | Other modules need user lookups (sender of message, vendor owner) | `EloquentUserRepository` |
| `Identity\\Domain\\Contracts\\VendorProfileRepository` | Catalog needs to check vendor approval status when vendor creates a service | `EloquentVendorProfileRepository` |

### Public Contracts (بنستخدمها):

| Contract | From Module | Why we need it |
|---|---|---|
| (none) | — | Identity stands alone |

---

## 8. Filament Footprint

| Resource | الغرض / Purpose | Translatable? |
|---|---|---|
| `Identity/Filament/Resources/UserResource.php` | List/edit users (basic) | No |
| `Identity/Filament/Resources/VendorProfileResource.php` | Edit vendor profile data | Yes (business_name, bio, address_line) |
| `Identity/Filament/Resources/VendorApprovalQueueResource.php` | Pending approvals + per-type buttons | Yes (rejection_reason) |
| `Identity/Filament/Resources/CustomerProfileResource.php` | Read-only customer view | No |

**Navigation group:** `Vendors` (for VendorProfile + VendorApprovalQueue), `Users` (for UserResource + CustomerProfileResource)

**Permissions generated by Shield:**
- Standard CRUD permissions on all 4 resources
- **Custom:** `approve_vendor_profile`, `approve_vendor_for_rental`, `approve_vendor_for_sale`, `approve_vendor_for_digital`, `revoke_vendor_type`, `suspend_vendor`

---

## 9. Testing Strategy

**Pest groups:** `identity`

**Test files:**

```
tests/Feature/Modules/Identity/
├── RegisterCustomerTest.php
├── RegisterVendorTest.php
├── LoginTest.php
├── PhoneVerificationTest.php
├── ApproveVendorTest.php
├── ApproveVendorForTypeTest.php
├── CustomerAddressTest.php
└── TwoFactorAuthTest.php

tests/Unit/Modules/Identity/
├── VendorApprovalStatusEnumTest.php
└── EloquentVendorProfileRepositoryTest.php
```

**Required coverage:**

- [x] Happy path (register, login, approve)
- [x] Auth: unauthenticated requests → 401
- [x] Authorization: customer can't access vendor endpoints, vendor without approval can't create services
- [x] Validation: per-Form-Request rules
- [x] Idempotency: not applicable to most Identity (registration is intentionally non-idempotent — duplicate phone = 422)
- [x] Locale: registration confirmation responses in EN + AR
- [ ] Type-aware tests: **NOT applicable** (Identity is cross-type)

**Architecture test:**

```php
test('Identity does not import other module models')
    ->expect('App\Modules\Identity')
    ->not->toUse([
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Payments\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
    ]);
```

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md):

- [ ] 2FA setup → defer to W7 (Phase 6 — Hardening)
- [ ] Customer address book UI in Filament → defer to Phase 1.5 (mobile/web)
- [ ] Vendor business hours UI → defer to W3 (Catalog phase) — schema is in place

---

## 11. Open Questions

- [ ] هل بنطلب OTP عند كل login، ولا بس عند phone change؟ → افتراضياً: عند login لو phone مش verified بعد. تأكيد لاحقاً.
- [ ] Vendor suspension flow: هل suspended vendor تظهر خدماتهم للعملاء (مع disable booking) ولا تختفي تماماً؟ → افتراضياً: تختفي.

---

## 12. Implementation Checklist

- [x] Migrations في `Identity/Database/Migrations/` بالترتيب الصح (Phase 0 Day 4)
- [ ] Models تحت `Domain/Models/` (Phase 1 Day 6)
- [ ] Form Requests + Actions (Phase 1 Days 6-7)
- [ ] API Resources مع locale conversion
- [ ] Filament Resources (Phase 1 Day 8)
- [ ] Service Provider مسجل في `bootstrap/providers.php`
- [ ] Pest tests كاملة
- [ ] `php artisan shield:generate --all`
- [ ] Translations في `Resources/lang/en/identity.php` و `ar/identity.php`
- [ ] Architecture test passes
- [ ] هذا الـ ADR محدّث في [`README.md`](README.md)
