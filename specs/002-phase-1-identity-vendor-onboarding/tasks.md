# Tasks: Identity & Vendor Onboarding (Phases 0.2 + 1.0 + 1.1)

**Feature Branch**: `002-identity-vendor-onboarding`
**Input**: `specs/002-phase-1-identity-vendor-onboarding/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅, quickstart.md ✅

## Format: `[ID] [P?] [Story?] Description — file/path`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[US#]**: User story this task belongs to:
  - **US0** = Schema Foundation + MoneyCast (Phase 0.2)
  - **US1** = Customer Registration & Auth (Phase 1.0)
  - **US2** = Vendor Registration & Document Upload (Phase 1.0/1.1)
  - **US3** = Admin Per-Type Vendor Approval (Phase 1.1)
  - **US4** = Vendor Profile Management (Phase 1.1, P2)
- Exact file paths are included in every task description

---

## Phase 0: Schema Foundation + MoneyCast (US0 — Phase 0.2) 🎯 P0 GATE

**Goal**: All 10 Identity tables migrated. `MoneyCast` available in Shared module. ADR-0003 finalized. No models, no actions yet — schema-only gate.

**Independent Test**: `php artisan migrate:fresh && ./vendor/bin/pest tests/Unit/Modules/Shared/MoneyCastTest.php tests/Feature/Modules/Identity/MigrationSmokeTest.php`

**⚠️ BLOCKS**: All subsequent phases depend on schema + MoneyCast.

- [X] T000a [US0] Finalize Phase 0 sections of `ADR-0003-identity-module.md` — document storage strategy (s3-private, NOT MediaLibrary), per-type approval sequencing (profile-approved-first), Spatie permission naming convention, OTP rate-limit decision in `docs/adr/0003-identity-module.md`
- [X] T000b [P] [US0] Create `MoneyCast` implementing `CastsAttributes`: constructor receives `string $minorField, string $currencyField`; `get()` returns `Brick\Money\Money::ofMinor($minor, $currency)` or null; `set()` returns `[$minorField => Money->getMinorAmount()->toInt(), $currencyField => Money->getCurrency()->getCurrencyCode()]`; null-safe both directions in `app/Modules/Shared/Domain/Casts/MoneyCast.php`
- [X] T000c [P] [US0] Create migration `create_vendor_profiles_table` — FK to `users` (UNIQUE), `governorates`, `cities`; `business_name`/`bio`/`address_line` JSON; `approval_status` ENUM; `bank_*` fields; `latitude`/`longitude` DECIMAL(10,7); `SoftDeletes`; `public_id` ULID; utf8mb4 in `app/Modules/Identity/Database/Migrations/2026_01_01_000010_create_vendor_profiles_table.php`
- [X] T000d [P] [US0] Create migration `create_vendor_documents_table` — FK to `vendor_profiles` (restrictOnDelete), `users` (reviewed_by, nullOnDelete); `doc_type` ENUM; `file_path`/`file_name`; `status` ENUM; `review_notes` JSON; `public_id` ULID in `app/Modules/Identity/Database/Migrations/2026_01_01_000011_create_vendor_documents_table.php`
- [X] T000e [P] [US0] Create migration `create_vendor_approved_product_types_table` — FK to `vendor_profiles`, `users` (approved_by, revoked_by); `product_type` ENUM; `revoked_at` nullable; UNIQUE `(vendor_profile_id, product_type, revoked_at)` in `app/Modules/Identity/Database/Migrations/2026_01_01_000012_create_vendor_approved_product_types_table.php`
- [X] T000f [P] [US0] Create migration `create_vendor_business_hours_table` — FK to `vendor_profiles`; `day_of_week` TINYINT (0-6); `opens_at`/`closes_at` TIME nullable; UNIQUE `(vendor_profile_id, day_of_week)` in `app/Modules/Identity/Database/Migrations/2026_01_01_000013_create_vendor_business_hours_table.php`
- [X] T000g [P] [US0] Create migration `create_customer_profiles_table` — FK to `users` (UNIQUE); `date_of_birth`, `gender` ENUM, `children` JSON, `accepts_marketing` BOOLEAN in `app/Modules/Identity/Database/Migrations/2026_01_01_000014_create_customer_profiles_table.php`
- [X] T000h [P] [US0] Create migration `create_customer_addresses_table` — FK to `users`, `cities` (restrictOnDelete); `public_id` ULID; `label`, `address_line`, `building`, `floor`, `apartment`, `landmark`; `latitude`/`longitude`; `is_default` BOOLEAN; `SoftDeletes`; index `(user_id, deleted_at)` in `app/Modules/Identity/Database/Migrations/2026_01_01_000015_create_customer_addresses_table.php`
- [X] T000i [P] [US0] Create migration `create_user_devices_table` — FK to `users`; `platform` ENUM(ios,android,web); `fcm_token`; UNIQUE `(user_id, fcm_token)` in `app/Modules/Identity/Database/Migrations/2026_01_01_000016_create_user_devices_table.php`
- [X] T000j [P] [US0] Create migration `create_two_factor_secrets_table` — FK to `users` (UNIQUE); `secret_encrypted` TEXT; `recovery_codes_encrypted` TEXT nullable; `confirmed_at` nullable in `app/Modules/Identity/Database/Migrations/2026_01_01_000017_create_two_factor_secrets_table.php`
- [X] T000k [P] [US0] Create `MoneyCastTest` — Pest unit test: `(50000, 'EGP')` round-trips through `MoneyCast::get()` → `Money` and `MoneyCast::set(Money)` → `[50000, 'EGP']`; null minor returns null; null Money writes null pair in `tests/Unit/Modules/Shared/MoneyCastTest.php`
- [X] T000l [P] [US0] Create `MigrationSmokeTest` — Pest feature test: `php artisan migrate:fresh` succeeds (no errors); attempting to delete a `cities` row referenced by a `vendor_coverage_areas` row → throws FK constraint exception (verifies `restrictOnDelete()`) in `tests/Feature/Modules/Identity/MigrationSmokeTest.php`

**Checkpoint (Phase 0.2 Exit Gate)**:
- ✅ `php artisan migrate:fresh` succeeds with zero errors (SC-000a)
- ✅ FK constraint test: deleting a city with vendor coverage rejected (SC-000b)
- ✅ MoneyCast round-trip test passes (SC-000c)
- ✅ ADR-0003 §6 finalized

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Wire the IdentityServiceProvider, create factories, seeder, and locale middleware. No business logic yet.

- [X] T001 Complete IdentityServiceProvider: load all 3 route files with `auth:sanctum` + `SetLocaleMiddleware`, register 8 event→listener bindings, register model policies, bind repository contracts in `app/Modules/Identity/Providers/IdentityServiceProvider.php`
- [X] T002 [P] Create `SetLocaleMiddleware` (reads `Accept-Language: en|ar`, defaults to `en`, calls `App::setLocale()`) in `app/Modules/Identity/Http/Middleware/SetLocaleMiddleware.php`
- [X] T003 [P] Create `IdentityRolesSeeder`: create `customer`, `vendor`, `admin` roles; create 12 per-type permissions (`service.create/update/delete/publish.{rental,sale,digital}.own`) + `approve_vendor_profile`, `approve_vendor_for_type`, `revoke_vendor_type`, `suspend_vendor` admin permissions in `database/seeders/IdentityRolesSeeder.php`
- [X] T004 [P] Create or update `UserFactory` supporting `asCustomer()`, `asVendor()`, `asAdmin()` states; `phone_verified_at` nullable; `public_id` ULID in `database/factories/UserFactory.php`
- [X] T005 [P] Create `VendorProfileFactory` (phone-verified user, `approval_status=pending`, translatable `business_name`, `primary_governorate_id=1`, `primary_city_id=1`) in `database/factories/VendorProfileFactory.php`
- [X] T006 [P] Create `CustomerProfileFactory` in `database/factories/CustomerProfileFactory.php`
- [X] T006a [P] Create `HasPublicId` trait: sets `public_id = (string) Str::ulid()` on the `creating` model event; lists `public_id` in `$guarded` exemption — do NOT use Laravel's `HasUlids` (which replaces the primary key with ULID) in `app/Modules/Shared/Domain/Concerns/HasPublicId.php`

**Checkpoint**: `php artisan db:seed --class=IdentityRolesSeeder` succeeds. Roles and permissions exist in DB.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: User model, all enums, all 8 domain events, OTP infrastructure, and ApiResponse envelope. Every user story depends on these.

**⚠️ CRITICAL**: No user story work begins until this phase is complete.

- [X] T007 Create `User` model: `HasRoles` (Spatie), `HasApiTokens` (Sanctum), `SoftDeletes`, `public_id` ULID cast, `phone_e164`, `email`, `preferred_locale`, `phone_verified_at`, `HasFactory` — in `app/Modules/Identity/Domain/Models/User.php`
- [X] T008 [P] Create `ApprovalStatus` enum (cases: `Pending`, `Approved`, `Rejected`, `Suspended`; string-backed: `pending/approved/rejected/suspended`) in `app/Modules/Identity/Domain/Enums/ApprovalStatus.php`
- [X] T009 [P] Create `ProductType` enum (cases: `Rental`, `Sale`, `Digital`; string-backed: `rental/sale/digital`) in `app/Modules/Shared/Domain/Enums/ProductType.php` — Catalog (Phase 2) will import from here; Identity uses it for `vendor_approved_product_types`
- [X] T009a [P] Create `BusinessType` enum (cases: `Individual`, `Company`, `Establishment`; string-backed: `individual/company/establishment`) in `app/Modules/Identity/Domain/Enums/BusinessType.php`; used as model cast on `VendorProfile::business_type` and in `RegisterVendorRequest` validation
- [X] T010 [P] Create `DocumentType` enum (cases: `Cr`, `TaxCard`, `NationalId`, `IbanProof`, `Other`) in `app/Modules/Identity/Domain/Enums/DocumentType.php`
- [X] T011 [P] Create `DocumentStatus` enum (cases: `Pending`, `Approved`, `Rejected`) in `app/Modules/Identity/Domain/Enums/DocumentStatus.php`
- [X] T012 [P] Create `DayOfWeek` enum (cases: `Sunday=0` through `Saturday=6`, int-backed) in `app/Modules/Identity/Domain/Enums/DayOfWeek.php`
- [X] T013 Create 8 domain event classes — each holds a reference to the relevant model and fires after DB commit only:
  - `CustomerRegistered` in `app/Modules/Identity/Domain/Events/CustomerRegistered.php`
  - `PhoneVerified` in `app/Modules/Identity/Domain/Events/PhoneVerified.php`
  - `VendorRegistered` in `app/Modules/Identity/Domain/Events/VendorRegistered.php`
  - `VendorApproved` in `app/Modules/Identity/Domain/Events/VendorApproved.php`
  - `VendorRejected` in `app/Modules/Identity/Domain/Events/VendorRejected.php`
  - `VendorSuspended` in `app/Modules/Identity/Domain/Events/VendorSuspended.php`
  - `VendorApprovedForType` in `app/Modules/Identity/Domain/Events/VendorApprovedForType.php`
  - `VendorTypeRevoked` in `app/Modules/Identity/Domain/Events/VendorTypeRevoked.php`
- [X] T014 [P] Create `OtpGatewayInterface` contract (methods: `send(string $phoneE164, string $code): void`, `verify(string $phoneE164, string $code): bool`) in `app/Modules/Identity/Domain/Contracts/OtpGatewayInterface.php`
- [X] T015 [P] Create `StubOtpGateway` implementing `OtpGatewayInterface`: `send()` is a no-op; `verify()` accepts any 6-digit code (always returns `true` in non-prod) in `app/Modules/Identity/Infrastructure/Gateways/StubOtpGateway.php`
- [X] T016 [P] Create `OtpRateLimiter` service: Redis key `otp_lockout:{phone_e164}`, sliding window 3 SEND attempts per 10 minutes, 60-minute lockout on breach; exposes `recordSendAttempt(string $phone)` (called from SendOtpAction) and `assertNotLockedOut(string $phone)` (called from BOTH SendOtpAction AND VerifyPhoneAction — once locked out, the phone cannot send NOR verify until TTL expires); throws `TooManyRequestsException` with `Retry-After` seconds in `app/Modules/Identity/Application/Services/OtpRateLimiter.php`
- [X] T017 [P] Verify `MoneyCast` exists in `app/Modules/Shared/Domain/Casts/MoneyCast.php` (was modified in Phase 0); confirm it returns `Brick\Money\Money` from `{field}_minor` + `{field}_currency` pair
- [X] T018 [P] Create static `ApiResponse` helper: `success($data, $meta = [], int $status = 200)`, `error($errors, int $status = 422)` — always returns `{ data, meta, errors }` envelope in `app/Modules/Shared/Http/ApiResponse.php`
- [X] T019 Bind `OtpGatewayInterface` → `StubOtpGateway` in `IdentityServiceProvider::register()`; register `RevokeAllVendorTypesOnStatusChange` listener on `VendorSuspended` and `VendorRejected` events

**Checkpoint**: `php artisan tinker` → `new \App\Modules\Identity\Domain\Models\User` resolves. All 8 event classes exist. `php artisan config:clear && php artisan route:list` shows no errors.

---

## Phase 3: User Story 1 — Customer Registration & Authentication (Priority: P1) 🎯 MVP

**Goal**: Customer registers with email + phone + password, verifies phone via OTP (stub, rate-limited), logs in with Sanctum token (Flutter) or SPA cookie (Next.js), receives 200 with profile data in EN or AR.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Identity/CustomerAuthTest.php`

### Tests for US1

- [X] T020 [P] [US1] Create `CustomerAuthTest` — happy-path registration: POST `/api/v1/register/customer` → 201 with `public_id`, `user` row + `customer_profile` row created; duplicate `phone_e164` → 422; duplicate `email` → 422; missing `password_confirmation` → 422 in `tests/Feature/Modules/Identity/CustomerAuthTest.php`
- [X] T021 [P] [US1] Add phone-verify scenarios to `CustomerAuthTest`: POST `/api/v1/phone/verify` with valid OTP → 200 + `phone_verified_at` populated; wrong OTP → 422; 4th OTP send attempt within 10 min → 429 with `Retry-After` header
- [X] T022 [P] [US1] Add login/logout scenarios to `CustomerAuthTest`: token mode (no `X-Requested-With`) → 200 with `token`; SPA cookie mode → 200 with session cookie; wrong password → 422; POST `/api/v1/logout` → 204 token revoked; no token on protected endpoint → 401
- [X] T023 [P] [US1] Add locale test to `CustomerAuthTest`: `Accept-Language: ar` returns Arabic content; `Accept-Language: en` returns English content; missing header defaults to English

### Implementation for US1

- [X] T024 [P] [US1] Create `CustomerProfile` model: `belongsTo` User, `name`, `date_of_birth`, `marketing_opt_in` bool, `HasFactory` in `app/Modules/Identity/Domain/Models/CustomerProfile.php`
- [X] T025 [P] [US1] Create `RegisterCustomerDTO` in `app/Modules/Identity/Application/DTOs/RegisterCustomerDTO.php`
- [X] T026 [US1] Create `RegisterCustomerRequest` (validates: `name`, `phone_e164` E.164 format unique users, `email` unique users, `password` min:8 confirmed, `preferred_locale` in:en,ar) in `app/Modules/Identity/Http/Requests/RegisterCustomerRequest.php`
- [X] T027 [US1] Create `RegisterCustomerAction`: `DB::transaction()` → create `User` with `customer` role + `CustomerProfile`; call `SendOtpAction::execute($user->phone_e164)` (SendOtpAction owns all OTP code generation and Redis storage — do NOT store OTP directly here); `DB::afterCommit()` → `event(new CustomerRegistered($user))` in `app/Modules/Identity/Application/Actions/RegisterCustomerAction.php`
- [X] T028 [US1] Create `SendOtpAction`: check `OtpRateLimiter` (throw 429 if locked), increment attempt counter, generate 6-digit code, store in Redis, call `OtpGatewayInterface::send()` in `app/Modules/Identity/Application/Actions/SendOtpAction.php`
- [X] T029 [US1] Create `VerifyPhoneAction`: check `OtpRateLimiter` lockout, validate code from Redis against submitted code, set `phone_verified_at`, clear Redis OTP key; `DB::afterCommit()` → `event(new PhoneVerified($user))` in `app/Modules/Identity/Application/Actions/VerifyPhoneAction.php`
- [X] T030 [US1] Create `LoginAction`: `Auth::attempt()` → on SPA request return session auth; on mobile return `$user->createToken()->plainTextToken`; locale from `preferred_locale` in `app/Modules/Identity/Application/Actions/LoginAction.php`
- [X] T031 [US1] Create `LogoutAction`: revoke current Sanctum token or invalidate session; return void in `app/Modules/Identity/Application/Actions/LogoutAction.php`
- [X] T032 [P] [US1] Create `CustomerResource` (API Resource): `id` = `public_id`, `name`, `email`, `phone_e164`, `phone_verified_at`, `preferred_locale`, nested `customer_profile` sub-object in `app/Modules/Identity/Http/Resources/CustomerResource.php`
- [X] T033 [US1] Create `CustomerAuthController`: `register()`, `sendOtp()`, `verifyPhone()`, `login()`, `logout()` — max 3 lines each, delegates to Actions in `app/Modules/Identity/Http/Controllers/CustomerAuthController.php`
- [X] T034 [US1] Create `CustomerProfileController`: `show()`, `update()` in `app/Modules/Identity/Http/Controllers/CustomerProfileController.php`
- [X] T035 [US1] Create customer routes: `POST /api/v1/register/customer` (public), `POST /api/v1/phone/otp/send` (public), `POST /api/v1/phone/verify` (public), `POST /api/v1/login` (public), `POST /api/v1/logout` (auth:sanctum), `GET /api/v1/customer/profile` (auth:sanctum + customer role), `PUT /api/v1/customer/profile` (auth:sanctum + customer role) — in `app/Modules/Identity/Routes/customer.php`
- [X] T036 [US1] Confirm `SetLocaleMiddleware` is applied to all API route groups in `IdentityServiceProvider::boot()`

**Checkpoint**: Customer can register, verify phone (OTP `000000` in dev), login, logout, and fetch profile with correct locale. Run `./vendor/bin/pest tests/Feature/Modules/Identity/CustomerAuthTest.php` — all green.

---

## Phase 4: User Story 2 — Vendor Registration & Document Upload (Priority: P1)

**Goal**: Vendor registers with full business profile (`approval_status=pending`), uploads documents to `s3-private`, retrieves profile in request locale. Admin approval queue shows the vendor.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Identity/VendorRegistrationTest.php tests/Feature/Modules/Identity/VendorDocumentTest.php`

### Tests for US2

- [X] T037 [P] [US2] Create `VendorRegistrationTest`: POST `/api/v1/register/vendor` → 201 with `vendor_profile.approval_status=pending` + `slug` set; missing `business_name.en` → 422; duplicate `phone_e164` → 422; `VendorRegistered` event fired in `tests/Feature/Modules/Identity/VendorRegistrationTest.php`
- [X] T038 [P] [US2] Create `VendorDocumentTest`: upload valid PDF → 201 with `doc_type`, `file_name`, `status=pending`; upload >10 MB → 422; invalid MIME (e.g. `.gif`) → 422; invalid `doc_type` value → 422; unauthenticated → 401 in `tests/Feature/Modules/Identity/VendorDocumentTest.php`
- [X] T039 [P] [US2] Create `VendorProfileTest`: GET `/api/v1/vendor/profile` with `Accept-Language: ar` → `business_name` in Arabic; `Accept-Language: en` → English; unauthenticated → 401; customer role → 403 in `tests/Feature/Modules/Identity/VendorProfileTest.php`
- [X] T040 [P] [US2] Create `VendorCoverageAreaTest`: POST `/api/v1/vendor/coverage-areas` with valid `city_id` → 201; nonexistent `city_id` → 422 in `tests/Feature/Modules/Identity/VendorCoverageAreaTest.php`

### Implementation for US2

- [X] T041 [P] [US2] Create `VendorProfile` model: `belongsTo` User, `$translatable = ['business_name', 'bio', 'address_line']`, `approval_status` cast to `ApprovalStatus`, `SoftDeletes`, `approved_at`, `approved_by`, `rejected_at`, `rejected_by`, `suspended_at`, `suspended_by`, `HasFactory` in `app/Modules/Identity/Domain/Models/VendorProfile.php`
- [X] T042 [P] [US2] Create `VendorDocument` model: `belongsTo` VendorProfile, `doc_type` cast to `DocumentType`, `status` cast to `DocumentStatus`, `file_path`, `file_name` in `app/Modules/Identity/Domain/Models/VendorDocument.php`
- [X] T043 [P] [US2] Create `VendorCoverageArea` model: `belongsTo` VendorProfile, `city_id` (raw FK — no Geography model import), `delivery_fee` via `MoneyCast` (`delivery_fee_minor` + `delivery_fee_currency`), `min_order` via `MoneyCast` in `app/Modules/Identity/Domain/Models/VendorCoverageArea.php`
- [X] T044 Create `RegisterVendorDTO` in `app/Modules/Identity/Application/DTOs/RegisterVendorDTO.php`
- [X] T045 Create `RegisterVendorRequest` (validates: `name`, `phone_e164` unique, `email` optional unique, `password` confirmed, `business_name.en` required string, `business_name.ar` required string, `business_type` in:individual,company, `primary_governorate_id` exists:governorates,id, `primary_city_id` exists:cities,id) in `app/Modules/Identity/Http/Requests/RegisterVendorRequest.php`
- [X] T046 Create `RegisterVendorAction`: `DB::transaction()` → create `User` with `vendor` role + `VendorProfile` (`approval_status=pending`, `slug` = Str::slug(business_name.en)); `DB::afterCommit()` → `event(new VendorRegistered($vendorProfile))` in `app/Modules/Identity/Application/Actions/RegisterVendorAction.php`
- [X] T047 Create `UploadVendorDocumentRequest` (validates: `doc_type` in DocumentType values, `file` mimes:pdf,jpg,jpeg,png max:10240) in `app/Modules/Identity/Http/Requests/UploadVendorDocumentRequest.php`
- [X] T048 Create `UploadVendorDocumentAction`: `DB::transaction()` → `Storage::disk('s3-private')->put("vendors/{$vendorId}/documents/{$uuid}.{$ext}", ...)` → create `VendorDocument` row with `file_path`, `file_name`, `status=pending` in `app/Modules/Identity/Application/Actions/UploadVendorDocumentAction.php`
- [X] T049 [P] [US2] Create `GenerateDocumentSignedUrlAction`: `Storage::disk('s3-private')->temporaryUrl($path, now()->addMinutes(15))` in `app/Modules/Identity/Application/Actions/GenerateDocumentSignedUrlAction.php`
- [X] T050 [P] [US2] Create `VendorProfileResource` (API Resource): `id` = `public_id`, `business_name` (resolved for current locale via `getTranslation()`), `slug`, `approval_status`, `business_type`, `approved_product_types[]`, `primary_city_id`, `primary_governorate_id` in `app/Modules/Identity/Http/Resources/VendorProfileResource.php`
- [X] T051 [P] [US2] Create `VendorDocumentResource` (API Resource): `id` = `public_id`, `doc_type`, `file_name`, `status` (signed URL excluded from list; generated on demand via separate endpoint) in `app/Modules/Identity/Http/Resources/VendorDocumentResource.php`
- [X] T052 Create `VendorRegistrationController`: `register()` in `app/Modules/Identity/Http/Controllers/VendorRegistrationController.php`
- [X] T053 Create `VendorProfileController`: `show()`, `update()` in `app/Modules/Identity/Http/Controllers/VendorProfileController.php`
- [X] T054 Create `VendorDocumentController`: `store()` in `app/Modules/Identity/Http/Controllers/VendorDocumentController.php`
- [X] T055 Create `VendorCoverageAreaController`: `store()` in `app/Modules/Identity/Http/Controllers/VendorCoverageAreaController.php`
- [X] T056 Create vendor routes: `POST /api/v1/register/vendor` (public), `GET /api/v1/vendor/profile` (auth:sanctum + vendor role), `POST /api/v1/vendor/documents` (auth:sanctum + vendor role), `POST /api/v1/vendor/coverage-areas` (auth:sanctum + vendor role) in `app/Modules/Identity/Routes/vendor.php`

**Checkpoint**: Vendor registers, uploads a PDF to S3-private, retrieves profile in Arabic. Admin Filament queue shows the pending vendor. Run `./vendor/bin/pest tests/Feature/Modules/Identity/VendorRegistrationTest.php tests/Feature/Modules/Identity/VendorDocumentTest.php` — all green.

---

## Phase 5: User Story 3 — Admin: Per-Product-Type Vendor Approval (Priority: P1)

**Goal**: Admin approves vendor profile first (overall), then independently grants per-type Spatie permissions. Suspension/rejection auto-revokes all active type approvals and Spatie permissions via `RevokeAllVendorTypesOnStatusChange` listener.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Identity/VendorApprovalTest.php`

### Tests for US3

- [X] T057 [P] [US3] Create `VendorApprovalTest`: `ApproveVendorProfileAction` → `approval_status=approved`, `approved_at` + `approved_by` populated, `VendorApproved` event fired; `ApproveVendorForTypeAction` with `product_type=rental` → `vendor_approved_product_types` row created + vendor has `service.create.rental.own` permission; `ApproveVendorForTypeAction` on pending vendor → 422 in `tests/Feature/Modules/Identity/VendorApprovalTest.php`
- [X] T058 [P] [US3] Add suspension/revocation tests: `SuspendVendorAction` on vendor with rental+sale approvals → `approval_status=suspended`, both type rows have `revoked_at` set, vendor no longer has `service.create.rental.own` or `service.create.sale.own`; `RejectVendorProfileAction` → same auto-revocation in `tests/Feature/Modules/Identity/VendorApprovalTest.php`
- [X] T059 [P] [US3] Add authorization tests: non-admin user calling approve-for-type endpoint → 403; `Gate::check('service.create.digital.own', $vendor)` returns `false` for vendor not approved for digital in `tests/Feature/Modules/Identity/VendorApprovalTest.php`
- [X] T060 [P] [US3] Create `FilamentVendorApprovalTest`: `VendorApprovalQueueResource` renders pending vendors; `ApproveVendorProfileAction` Filament action is visible on pending record; non-admin cannot see it in `tests/Feature/Modules/Identity/FilamentVendorApprovalTest.php`

### Implementation for US3

- [X] T061 [P] [US3] Create `VendorApprovedProductType` model: `belongsTo` VendorProfile, `product_type` cast to `ProductType`, `approved_at`, `approved_by`, `revoked_at` nullable, `revoked_by` nullable, `revoke_reason` JSON nullable; scope `active()` → `whereNull('revoked_at')` in `app/Modules/Identity/Domain/Models/VendorApprovedProductType.php`
- [X] T062 Create `ApproveVendorProfileAction`: `DB::transaction()` → set `approval_status=approved`, `approved_at=now()`, `approved_by=auth()->id()`; `DB::afterCommit()` → `event(new VendorApproved($vendorProfile))` in `app/Modules/Identity/Application/Actions/ApproveVendorProfileAction.php`
- [X] T063 Create `RejectVendorProfileAction`: `DB::transaction()` → set `approval_status=rejected`, `rejected_at`, `rejected_by`; `DB::afterCommit()` → `event(new VendorRejected($vendorProfile))` (listener handles type revocation) in `app/Modules/Identity/Application/Actions/RejectVendorProfileAction.php`
- [X] T064 Create `ApproveVendorForTypeAction`: guard `approval_status === approved` (throw 422 otherwise); `DB::transaction()` → create `VendorApprovedProductType` row (`approved_at=now()`, `approved_by=auth()->id()`), call `$user->givePermissionTo([...4 per-type permissions...])` via Spatie; `DB::afterCommit()` → `event(new VendorApprovedForType($row))` in `app/Modules/Identity/Application/Actions/ApproveVendorForTypeAction.php`
- [X] T065 Create `RevokeVendorTypeAction`: guard — if no `active()` row exists for `(vendor_profile_id, product_type)` throw 422 `UnprocessableEntityException("Type approval not found or already revoked")`; `DB::transaction()` → set `revoked_at=now()`, `revoked_by` on `VendorApprovedProductType` row, call `$user->revokePermissionTo([...4 per-type permissions...])` via Spatie; `DB::afterCommit()` → `event(new VendorTypeRevoked($row))` in `app/Modules/Identity/Application/Actions/RevokeVendorTypeAction.php`
- [X] T066 Create `SuspendVendorAction`: `DB::transaction()` → set `approval_status=suspended`, `suspended_at=now()`, `suspended_by=auth()->id()`; `DB::afterCommit()` → `event(new VendorSuspended($vendorProfile))` (listener handles type revocation) in `app/Modules/Identity/Application/Actions/SuspendVendorAction.php`
- [X] T066a Add `activity()` audit logging to T062–T066: each approval/rejection/suspension/revocation action MUST call `activity()->on($vendorProfile)->causedBy(auth()->user())->withProperties(['old' => [...], 'new' => [...]])->log('<action_name>')` inside its `DB::transaction()` — satisfies FR-I10 and SC-006; applies to `ApproveVendorProfileAction`, `RejectVendorProfileAction`, `ApproveVendorForTypeAction`, `RevokeVendorTypeAction`, `SuspendVendorAction`
- [X] T067 Create `RevokeAllVendorTypesOnStatusChange` listener: handles both `VendorSuspended` and `VendorRejected`; queries `vendor_approved_product_types` for `active()` rows, calls `RevokeVendorTypeAction` per row; queued listener to run after commit in `app/Modules/Identity/Application/Listeners/RevokeAllVendorTypesOnStatusChange.php`
- [X] T068 Create `VendorApprovalQueueResource` (Filament): scoped to `approval_status=pending`; table columns: `business_name.en`, `business_type`, `created_at`; row actions: `ApproveProfile` (calls `ApproveVendorProfileAction`), `RejectProfile` (with `rejection_reason` modal); group under "Vendor Onboarding" navigation in `app/Modules/Identity/Filament/Resources/VendorApprovalQueueResource.php`
- [X] T069 Create `VendorProfileResource` (Filament): all vendors; table columns: `business_name.en`, `approval_status` badge (warning=pending, success=approved, danger=rejected/suspended), `created_at`; filter by `approval_status`; row actions: `ApproveForType` (modal with `product_type` Select), `RevokeType`, `Suspend`; use `ApproveVendorForTypeAction`, `RevokeVendorTypeAction`, `SuspendVendorAction` — never inline business logic in `->action()` closure in `app/Modules/Identity/Filament/Resources/VendorProfileResource.php`
- [X] T070 Create `VendorDocumentFilamentResource` (Filament): read-only list with signed URL download action per row in `app/Modules/Identity/Filament/Resources/VendorDocumentFilamentResource.php`
- [~] T071 Run `php artisan shield:generate --all` — MANUAL: run locally after migrate — commit the generated permission records; verify `approve_vendor_profile`, `approve_vendor_for_type` permissions exist
- [X] T072 Create admin routes: `GET /api/v1/admin/vendor-profiles` (paginated, filterable), `POST /api/v1/admin/vendor-profiles/{public_id}/approve-for-type` in `app/Modules/Identity/Routes/admin.php`
- [X] T073 Create `AdminVendorApprovalController`: `index()`, `approveForType()` — delegates to `ApproveVendorForTypeAction`; max 3 lines per method in `app/Modules/Identity/Http/Controllers/AdminVendorApprovalController.php`

**Checkpoint**: Admin opens Filament → Vendor Onboarding → clicks "Approve for Rental" → `vendor_approved_product_types` row created → `tinker: $user->hasPermissionTo('service.create.rental.own')` returns `true`. Suspension auto-revokes. Run `./vendor/bin/pest tests/Feature/Modules/Identity/VendorApprovalTest.php` — all green.

---

## Phase 6: User Story 4 — Vendor Profile Management (Priority: P2)

**Goal**: Approved vendor updates business profile (sensitive field changes logged to audit log), upserts business hours, manages coverage areas. Customer manages soft-deletable address book.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Identity/VendorProfileManagementTest.php`

### Tests for US4

- [X] T074 [P] [US4] Create `VendorProfileManagementTest`: PUT `/api/v1/vendor/profile` → changes persisted + `audit_logs` entry exists for `bank_iban` change; unauthenticated → 401; customer role → 403 in `tests/Feature/Modules/Identity/VendorProfileManagementTest.php`
- [X] T075 [P] [US4] Create `VendorBusinessHoursTest`: PUT `/api/v1/vendor/business-hours` with 7-day array → rows upserted; second call updates existing rows; invalid `opens_at` format → 422 in `tests/Feature/Modules/Identity/VendorBusinessHoursTest.php`
- [X] T076 [P] [US4] Create `CustomerAddressTest`: POST `/api/v1/customer/addresses` → 201 with `public_id`; soft-delete: `DELETE /api/v1/customer/addresses/{id}` → row has `deleted_at`; invalid `city_id` → 422 in `tests/Feature/Modules/Identity/CustomerAddressTest.php`

### Implementation for US4

- [X] T077 [P] [US4] Create `VendorBusinessHour` model: `belongsTo` VendorProfile, `day_of_week` cast to `DayOfWeek`, `opens_at` nullable `time`, `closes_at` nullable `time` in `app/Modules/Identity/Domain/Models/VendorBusinessHour.php`
- [X] T078 [P] [US4] Create `CustomerAddress` model: `belongsTo` User, `city_id` raw FK, `SoftDeletes`, `public_id` ULID, `label`, `address_line`, `building`, `floor`, `apartment`, `landmark`, `is_default` bool in `app/Modules/Identity/Domain/Models/CustomerAddress.php`
- [X] T079 [P] [US4] Create `UserDevice` model: `belongsTo` User, `device_token`, `platform` in `app/Modules/Identity/Domain/Models/UserDevice.php`
- [X] T080 Create `UpdateVendorProfileRequest` (all fields optional: `business_name`, `bio`, `bank_iban`, `bank_name`, `preferred_locale`) in `app/Modules/Identity/Http/Requests/UpdateVendorProfileRequest.php`
- [X] T081 Create `UpdateVendorProfileAction`: `DB::transaction()` → partial `fill()` + `save()`; log sensitive field changes (`bank_iban`, `bank_name`, `bank_account_name`) via `activity()->on($vendor)->withProperties([...])->log('updated_vendor_profile')`; `DB::afterCommit()` → optional webhook/event in `app/Modules/Identity/Application/Actions/UpdateVendorProfileAction.php`
- [X] T082 Create `UpsertVendorBusinessHoursAction`: `DB::transaction()` → `upsert()` all submitted day rows keyed on `(vendor_profile_id, day_of_week)` in `app/Modules/Identity/Application/Actions/UpsertVendorBusinessHoursAction.php`
- [X] T083 Create `AddCustomerAddressAction`: `DB::transaction()` → create `CustomerAddress`; if `is_default=true` unset any other default for this user in `app/Modules/Identity/Application/Actions/AddCustomerAddressAction.php`
- [X] T084 Create `VendorBusinessHourController`: `update()` for PUT `/api/v1/vendor/business-hours` in `app/Modules/Identity/Http/Controllers/VendorBusinessHourController.php`
- [X] T085 Create `CustomerAddressController`: `store()`, `destroy()` in `app/Modules/Identity/Http/Controllers/CustomerAddressController.php`
- [X] T086 Add routes: `PUT /api/v1/vendor/profile` to `vendor.php`, `PUT /api/v1/vendor/business-hours` to `vendor.php`, `POST /api/v1/customer/addresses` + `DELETE /api/v1/customer/addresses/{public_id}` to `customer.php` in `app/Modules/Identity/Routes/`

**Checkpoint**: Vendor updates `bank_iban` → audit log records the change. Business hours upserted for all 7 days. Customer creates and soft-deletes an address. Run `./vendor/bin/pest tests/Feature/Modules/Identity/VendorProfileManagementTest.php` — all green.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T087 [P] Create EN translation file: validation messages, field labels, status labels in `app/Modules/Identity/Resources/lang/en/identity.php`
- [X] T088 [P] Create AR translation file matching all EN keys in `app/Modules/Identity/Resources/lang/ar/identity.php`
- [X] T089 [P] Create `VendorDocumentFactory` in `database/factories/VendorDocumentFactory.php`
- [X] T090 [P] Create `VendorApprovedProductTypeFactory` in `database/factories/VendorApprovedProductTypeFactory.php`
- [X] T091 [P] Create `CustomerAddressFactory` in `database/factories/CustomerAddressFactory.php`
- [X] T092 Create `ArchTest` for Identity module: assert models in `Domain/Models` contain no public methods beyond relationships/scopes/casts; assert controllers have no more than 3 lines in action methods; assert Actions have a single `execute()` method in `tests/Unit/Modules/Identity/ArchTest.php`
- [X] T093 Run `./vendor/bin/pint` — fix all code style violations
- [X] T094 Run `./vendor/bin/phpstan analyse app/Modules/Identity/ --level=8` — fix all type errors
- [X] T095 Run `./vendor/bin/pest --group=identity` — confirm full suite is green
- [X] T096 Run quickstart.md manual verification: register vendor via curl, approve in Filament, verify Spatie permission via tinker — verified 2026-04-28: vendor `01KQ8EQ1B70GW6XMFMH4HCZ6MJ` registered (201), profile approved via Filament Approval Queue, `service.create.rental.own=true` / `sale=false` / `digital=false`, 1 active type row, audit log captured both events

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 0 — Schema + MoneyCast (US0)**: No dependencies — start immediately. **BLOCKS all subsequent phases.**
- **Setup (Phase 1)**: Depends on Phase 0 (migrations must exist before factories can reference tables)
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **US1 Customer Auth (Phase 3)**: Depends on Foundational — no dependency on other stories
- **US2 Vendor Registration (Phase 4)**: Depends on Foundational — no dependency on US1 (login is shared via US1's `LoginAction` and routes)
- **US3 Admin Approval (Phase 5)**: Depends on US2 — needs `VendorProfile` (T041) and `VendorApprovedProductType` (T061) models
- **US4 Vendor Profile Mgmt (Phase 6)**: Depends on US2 — needs `VendorProfile` (T041); P2 priority, deferrable if behind schedule
- **Polish (Phase 7)**: Depends on all story phases

### User Story Dependencies

- **US0**: Independent — schema + MoneyCast only; no models, no actions
- **US1**: Independent after Foundational
- **US2**: Independent after Foundational (reuses `User` model from T007; `LoginAction` from US1 serves vendor login via shared route)
- **US3**: Requires T041 (`VendorProfile`) + T061 (`VendorApprovedProductType`) — start US3 only after US2 models exist
- **US4**: Requires T041 (`VendorProfile`) — can overlap with US3 implementation (different Actions and models)

### Within Each Story

- Enums → Models → DTOs/Requests → Actions → API Resources → Controllers → Routes → Tests
- Factories can be created alongside their model (same session, different file)
- Filament resources in US3 can be created in parallel with API controllers (T068-T070 ∥ T072-T073)

---

## Parallel Example: US1 & US2 (with two developers after Phase 2)

```bash
# Developer A — US1 (T024–T036):
Task: "CustomerProfile model — app/Modules/Identity/Domain/Models/CustomerProfile.php"
Task: "RegisterCustomerAction — app/Modules/Identity/Application/Actions/RegisterCustomerAction.php"
Task: "CustomerAuthController — app/Modules/Identity/Http/Controllers/CustomerAuthController.php"

# Developer B — US2 (T041–T056) simultaneously:
Task: "VendorProfile model — app/Modules/Identity/Domain/Models/VendorProfile.php"
Task: "RegisterVendorAction — app/Modules/Identity/Application/Actions/RegisterVendorAction.php"
Task: "VendorRegistrationController — app/Modules/Identity/Http/Controllers/VendorRegistrationController.php"
```

---

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Phase 0: Schema + MoneyCast (T000a–T000l) — **gate before all else**
2. Complete Phase 1: Setup (T001–T006a)
3. Complete Phase 2: Foundational (T007–T019, including T009a)
4. Complete Phase 3: US1 Customer Auth (T020–T036)
5. **STOP and VALIDATE**: `./vendor/bin/pest tests/Feature/Modules/Identity/CustomerAuthTest.php`
6. Proceed to US2

### Incremental Delivery

1. Phase 0 (US0) → 10 migrations applied + MoneyCast ready (Phase 0.2 exit gate)
2. Setup + Foundational → roles, events, OTP infrastructure ready
3. US1 → customer registration, phone verify, login (independently testable) — Phase 1.0 exit gate
4. US2 → vendor registration, document upload, profile in locale (admin queue visible in Filament)
5. US3 → admin approval + per-type Spatie permissions + auto-revocation — Phase 1.1 exit gate
6. US4 → vendor profile update + business hours + customer addresses

### Phase 0 Reuse

- Geography migrations (`cities`, `governorates`) already exist — reference by FK only; no Eloquent import
- `app/Modules/Shared/Domain/Casts/MoneyCast.php` — verify at T017 before re-creating
- `database/factories/UserFactory.php` — update at T004, do not replace
- `app/Providers/Filament/AdminPanelProvider.php` — already scans `app/Modules/*/Filament/Resources/` recursively; no changes needed for Filament auto-discovery

---

## Notes

- `[P]` = different files, no incomplete dependencies — safe to launch simultaneously
- `[US#]` maps each task to its user story for traceability and independent delivery
- **Vendor document storage**: `Storage::disk('s3-private')` ONLY — NOT MediaLibrary (FR-I03, ADR-0003 §6.3)
- **ProductType enum**: lives in `app/Modules/Shared/Domain/Enums/ProductType.php` — Catalog (Phase 2) imports from Shared
- **OTP stub**: code `000000` always valid in non-prod; real SMS gateway wired in Phase 5
- **Shield**: run `php artisan shield:generate --all` at T071 after all 3 Filament resources exist (T068–T070)
- **`vendor_approved_product_types` UNIQUE**: `(vendor_profile_id, product_type, revoked_at)` — NULL `revoked_at` = active; T061 `VendorApprovedProductType` model must enforce this in its factory and tests
- **Domain events**: ALWAYS via `DB::afterCommit()` inside `DB::transaction()` — never directly inside the transaction callback
- **Cross-module FK**: `vendor_coverage_areas.city_id` → `cities.id` via raw FK only; no Geography model import in Identity

---

## Task Count Summary

| Phase | Tasks | Stories |
|---|---|---|
| Phase 0: Schema + MoneyCast | T000a–T000l (12 tasks) | US0 (P0) |
| Phase 1: Setup | T001–T006, T006a | — |
| Phase 2: Foundational | T007–T019, T009a | — |
| Phase 3: US1 Customer Auth | T020–T036 | US1 (P1) |
| Phase 4: US2 Vendor Registration | T037–T056 | US2 (P1) |
| Phase 5: US3 Admin Approval | T057–T066a, T067–T073 | US3 (P1) |
| Phase 6: US4 Profile Mgmt | T074–T086 | US4 (P2) |
| Phase 7: Polish | T087–T096 | — |
| **Total** | **111 tasks** | 5 stories |

**Parallel opportunities**: 58 tasks marked [P] across all phases (47 prior + 11 new in Phase 0).
**MVP scope**: Complete Phases 0–3 (US0 Schema → US1 Customer Auth) for first independently deliverable increment.
**Current state (2026-04-28)**: Phases 0–6 mostly complete (T000a–T095 marked [X]); only T096 (manual quickstart verification) remains open.
