# Feature Specification: Phase 1 — Identity & Vendor Onboarding

**Feature Branch**: `002-identity-vendor-onboarding`
**Created**: 2026-04-27
**Status**: Draft
**Phase**: Phase 1 (Week 2, Days 6–8)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Customer Registration & Authentication (Priority: P1)

A new customer signs up with email/phone + password, verifies their phone number via OTP, and logs in with a Sanctum token (Flutter) or SPA cookie (Next.js web). They receive a welcome notification.

**Why this priority**: The customer is the primary revenue-generating user; the platform cannot accept bookings without registered customers.

**Independent Test**: A customer can register, verify phone, and receive a 200 response with profile data — deliverable value with no other module needed.

**Acceptance Scenarios**:

1. **Given** an unregistered phone/email, **When** POST /api/v1/register/customer with valid data, **Then** user + customer_profile rows created, OTP sent, 201 returned with public_id
2. **Given** a valid OTP, **When** POST /api/v1/phone/verify, **Then** phone_verified_at populated, PhoneVerified event fired after commit
3. **Given** valid credentials, **When** POST /api/v1/login, **Then** Sanctum token or SPA cookie returned with user resource in EN or AR per Accept-Language
4. **Given** an authenticated user, **When** POST /api/v1/logout, **Then** token revoked, 204
5. **Given** no token, **When** any protected endpoint, **Then** 401

---

### User Story 2 — Vendor Registration & Document Upload (Priority: P1)

A vendor signs up, submits business data (name, business type, governorate/city, bank info), uploads required documents (CR, tax card, national ID, IBAN proof), and submits the profile for admin review. Their approval_status starts as `pending`.

**Why this priority**: No vendors = no catalog = no bookings. Vendor onboarding is the critical supply-side path.

**Independent Test**: Vendor can register, upload 1+ documents, and hit `pending` approval_status — independently verifiable with admin queue visible in Filament.

**Acceptance Scenarios**:

1. **Given** an unregistered user, **When** POST /api/v1/register/vendor with required fields, **Then** user + vendor_profile created with approval_status=pending, VendorRegistered event fired, admin notified
2. **Given** an approved phone-verified vendor, **When** POST /api/v1/vendor/documents with file + type, **Then** document stored via MediaLibrary, vendor_documents row created
3. **Given** a vendor with uploaded docs, **When** GET /api/v1/vendor/profile, **Then** full profile resource returned in request locale (EN/AR)
4. **Given** an unauthenticated request, **When** POST /api/v1/register/vendor, **Then** 422 if duplicate phone/email
5. **Given** a vendor role user, **When** accessing customer-only endpoint, **Then** 403

---

### User Story 3 — Admin: Per-Product-Type Vendor Approval (Priority: P1)

An admin reviews a pending vendor in the Filament Vendor Approval Queue and approves the vendor for specific product types (rental, sale, digital) independently. The vendor receives a notification. Admin can also reject the whole profile or revoke an approved type later.

**Why this priority**: Vendors cannot create services until approved for a type. Admin approval gate is the supply-side quality control.

**Independent Test**: Admin can open a pending vendor, click "Approve for Rental", and the vendor gains `service.create.rental.own` permission — independently testable via Filament + Pest.

**Acceptance Scenarios**:

1. **Given** a pending vendor, **When** admin calls ApproveVendorForTypeAction with product_type=rental, **Then** vendor_approved_product_types row created, Spatie permission granted, VendorApprovedForType event fired after commit
2. **Given** an approved vendor, **When** admin calls RevokeVendorTypeAction, **Then** revoked_at set, permission revoked, VendorTypeRevoked event fired
3. **Given** a pending vendor, **When** admin calls ApproveVendorAction (profile-level), **Then** approval_status=approved, approved_at + approved_by set, VendorApproved event fired
4. **Given** a vendor not approved for digital, **When** checking `service.create.digital.own`, **Then** Gate returns false
5. **Given** a non-admin, **When** calling ApproveVendorForTypeAction, **Then** 403

---

### User Story 4 — Vendor Profile Management (Priority: P2)

An approved vendor can update their business profile (name, bio, coverage areas, business hours, bank info). Changes to sensitive fields (bank info) are logged via audit log.

**Why this priority**: Vendor profile completeness directly affects discovery quality, but it can be partially deferred as long as approval flow works.

**Independent Test**: Approved vendor updates `business_name.en` and the change appears in Filament VendorProfile Resource.

**Acceptance Scenarios**:

1. **Given** an authenticated vendor, **When** PUT /api/v1/vendor/profile with updated fields, **Then** changes persisted, UpdatedVendorProfile event logged in audit_log
2. **Given** a vendor updating their coverage area, **When** POST /api/v1/vendor/coverage-areas, **Then** vendor_coverage_areas row created/updated linked to valid city_id
3. **Given** a vendor setting business hours, **When** PUT /api/v1/vendor/business-hours, **Then** vendor_business_hours rows upserted for each weekday
4. **Given** a vendor adding a customer address, **When** POST /api/v1/customer/addresses (customer role), **Then** customer_addresses row created with soft-delete support

---

### Edge Cases

- Duplicate phone/email registration → 422 with field-level error in request locale
- Vendor submitting docs after approval (updates) → allowed, new documents version stored
- Revoking an approval type that was never granted → 422
- Customer attempting vendor-only endpoints → 403
- Two-factor auth requested before enrollment → 422 with enrollment instructions
- Coverage area referencing a city_id not in `cities` → 422 FK validation

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-I01**: System MUST allow customers to register with email + phone + password; phone OTP verification required before booking
- **FR-I02**: System MUST allow vendors to register with full business profile; approval_status starts at `pending`
- **FR-I03**: Vendors MUST be able to upload documents (CR, tax card, national ID, IBAN proof) stored via MediaLibrary
- **FR-I04**: Admin MUST be able to approve or reject vendor profiles (overall profile approval)
- **FR-I05**: Admin MUST be able to approve or revoke vendor authorization per product type (rental/sale/digital) independently — maps to PRD §6.3 (admin approves per type), PRD §6.2 step 4, Vendor Journey §1
- **FR-I06**: Per-type approval MUST grant/revoke corresponding Spatie permissions (`service.create.{type}.own`, `service.update.{type}.own`, `service.delete.{type}.own`, `service.publish.{type}.own`)
- **FR-I07**: Auth MUST support Sanctum token mode (mobile) and SPA cookie mode (web) — ADR-0003 §5 references `02_Tech_Decisions.md` §3.1
- **FR-I08**: All registration/profile API responses MUST support EN and AR via Accept-Language header
- **FR-I09**: Domain events MUST fire after DB commit only (`DB::afterCommit`) — not inside transactions
- **FR-I10**: Vendor profile update and type approval/revocation MUST be recorded in audit_logs (spatie/laravel-activitylog)
- **FR-I11**: Customer addresses MUST be stored in `customer_addresses` with soft delete; snapshot to `booking_addresses` at booking time (Phase 3 will consume this)
- **FR-I12**: Vendor coverage areas MUST reference valid `cities.id` from Geography module (cross-module via FK only, no model import)
- **FR-I13**: Phone OTP stub provider in Phase 1 (real SMS in Phase 5); the stub MUST be swappable via the channel adapter pattern

### Key Entities

- **User**: Single users table for all 3 roles; Spatie roles (customer, vendor, admin); ULID public_id
- **VendorProfile**: 1:1 to user; translatable business_name, bio, address_line; approval_status ENUM; FK to governorates + cities
- **VendorDocument**: Belongs to vendor_profile; document_type ENUM; stored via MediaLibrary
- **VendorApprovedProductType**: per-(vendor_profile, product_type) approval log with revocation support; UNIQUE on (vendor_profile_id, product_type, revoked_at)
- **VendorBusinessHour**: Weekly schedule per vendor; day_of_week + open/close times
- **VendorCoverageArea**: Cities the vendor serves; optional delivery fee per city (money columns)
- **CustomerProfile**: 1:1 to user; name, date_of_birth, marketing_opt_in
- **CustomerAddress**: Soft-deletable address book; FK to cities; snapshot-ready

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new vendor can complete the full registration + document upload flow in under 5 minutes via API
- **SC-002**: Admin can approve a vendor for all 3 product types via Filament in under 2 minutes (3 button clicks)
- **SC-003**: API responses for all Identity endpoints return correct locale (EN/AR) based on Accept-Language header — 100% of responses
- **SC-004**: Pest test suite covers happy path, 401, 403, validation, and locale for every Identity endpoint — test run completes in under 60 seconds
- **SC-005**: Vendor without type approval receives 403 when attempting service creation (enforced by Spatie Gate, not just middleware)
- **SC-006**: All approval/revocation actions appear in the audit log with actor, timestamp, and changed values

---

## Assumptions

- Phone OTP is stubbed in Phase 1 (always succeeds with code "000000" in test/dev environments); real SMS gateway wired in Phase 5 (Week 6)
- Admin 2FA (TOTP) is deferred to Phase 6 (Week 7) per ADR-0003 §10 cut-list; `two_factor_secrets` migration exists but EnableTwoFactorAction is not implemented in Phase 1
- Vendor business hours UI in Filament is deferred to Phase 2 (Catalog phase) per ADR-0003 §10; the API endpoint and migration are included in Phase 1
- `vendor_coverage_areas` migration is already in the Geography module (migration `000005`); the Identity module owns the model and API layer
- The Identity module communicates with Geography only via FK constraints and DB lookups; no Eloquent model cross-import
- All Filament resources run `php artisan shield:generate --all` after creation
- `filament/spatie-laravel-activitylog-plugin` is the locked plugin per `10_Package_List.md` §3; the currently installed `rmsramos/activitylog` may need to be swapped — this must be confirmed before Filament activitylog UI work begins
