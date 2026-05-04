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

# Tasks: Admin Customer Management (Phase 6.3)

**Input**: `specs/015-admin-customer-management/`
**Phase**: 6.3 â€” Week 7 | **PRD FR**: FR-22, FR-25, FR-26
**Spec**: spec.md | **Plan**: plan.md | **Data model**: data-model.md

**Tests**: Included â€” spec explicitly requires Pest tests for suspend â†’ can't login and force logout â†’ tokens revoked.

**User Stories** (from spec.md):
- **US1** (P1): Find and Inspect a Customer
- **US2** (P2): Edit Customer Profile Fields
- **US3** (P2): Suspend / Unsuspend a Customer
- **US4** (P3): Force Logout a Customer

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: New domain events and DTO that all later phases depend on.

- [X] T001 [P] Create `CustomerSuspended` event class in `app/Modules/Identity/Domain/Events/CustomerSuspended.php` with `readonly User $customer` and `readonly int $actorId` constructor properties
- [X] T002 [P] Create `CustomerUnsuspended` event class in `app/Modules/Identity/Domain/Events/CustomerUnsuspended.php` â€” same shape as `CustomerSuspended`
- [X] T003 [P] Create `AdminUpdateCustomerDTO` in `app/Modules/Identity/Application/DTOs/AdminUpdateCustomerDTO.php` with `string $name` and `?string $phoneE164` properties, plus a `toArray(): array` method returning only non-null values

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Auth-layer enforcement (middleware + LoginAction patch) that all user stories depend on to be verifiable end-to-end.

**âš ï¸ CRITICAL**: Pest tests for suspension (US3) and force-logout (US4) depend on the middleware being active. Complete this phase before any user story.

- [X] T004 Create `EnsureAccountIsActive` middleware in `app/Modules/Identity/Http/Middleware/EnsureAccountIsActive.php` â€” check `auth()->user()?->status === 'suspended'`; return `response()->json(['errors' => [['code' => 'account_suspended', 'message' => __('identity.account_suspended')]]], 403)` if suspended
- [X] T005 Register `EnsureAccountIsActive` on the `sanctum` middleware group in `app/Modules/Identity/Providers/IdentityServiceProvider.php` â€” add `$this->app['router']->pushMiddlewareToGroup('sanctum', EnsureAccountIsActive::class)` in `boot()`
- [X] T006 Patch `app/Modules/Identity/Application/Actions/LoginAction.php` â€” after credential check, before token creation, add: if `$user->status === 'suspended'` throw `ValidationException::withMessages(['login' => __('identity.account_suspended')])`
- [X] T007 [P] Add EN translation keys to `app/Modules/Identity/Resources/lang/en/identity.php`: `account_suspended`, `customer_already_suspended`, `admin_cannot_self_suspend`, `nav.customers`, `actions.suspend_customer`, `actions.unsuspend_customer`, `actions.force_logout`, `actions.edit_profile`, `tabs.overview`, `tabs.bookings`, `tabs.reviews`, `tabs.wallet`, `tabs.addresses`, `tabs.activity`
- [X] T008 [P] Add AR translation keys to `app/Modules/Identity/Resources/lang/ar/identity.php` â€” Arabic equivalents for all keys added in T007

**Checkpoint**: Middleware active â€” a suspended user with a valid token now gets 403; LoginAction blocks new tokens for suspended accounts.

---

## Phase 3: User Story 1 â€” Find and Inspect a Customer (Priority: P1) ðŸŽ¯ MVP

**Goal**: Admin can search for any customer by name / email / phone and navigate to a tabbed detail page showing their full footprint.

**Independent Test**: Navigate to `/admin/customers`, type a partial email â€” matching customers appear. Open a record and verify all 6 tabs render (even if some show empty state).

### Tests for US1

- [X] T009 [P] [US1] Add test in `tests/Feature/Modules/Identity/CustomerManagementTest.php`: `it('admin can search customers by name email and phone', ...)` â€” create 3 customers with distinct names/emails/phones; assert Filament table search returns the correct one for each query. Group: `identity`, `customer-management`

### Implementation for US1

- [X] T010 [US1] Create `app/Modules/Identity/Filament/Resources/CustomerResource.php` â€” model: `User::class`, scoped via `getEloquentQuery()` calling `scopeCustomers()`, navigation group: `'Users'`, navigation icon: `heroicon-o-users`, navigation sort: 30
- [X] T011 [US1] Add table definition to `CustomerResource::table()` â€” columns: `name` (searchable, sortable), `email` (searchable), `phone_e164` (label "Phone", searchable), `status` badge (`'active'` â†’ `'success'`, `'suspended'` â†’ `'danger'`), `created_at` (label "Joined", dateTime, sortable, default desc). Add `SelectFilter` on `status`
- [X] T012 [US1] Create `app/Modules/Identity/Filament/Resources/CustomerResource/Pages/ListCustomers.php` â€” standard `ListRecords` page; set `protected static string $resource = CustomerResource::class`
- [X] T013 [US1] Create `app/Modules/Identity/Filament/Resources/CustomerResource/Pages/ViewCustomer.php` â€” custom `ViewRecord` page using Filament `Tabs` layout for the 6-tab detail view
- [X] T014 [US1] Implement **Overview tab** in `ViewCustomer::infolist()` â€” `TextEntry` fields for: `name`, `email`, `phone_e164`, `status` (badge), `preferred_locale`, `last_login_at` (dateTime), `created_at` (label "Joined", dateTime); `TextEntry` for `customerProfile.date_of_birth` (date), `customerProfile.gender` (badge), `IconEntry` for `customerProfile.accepts_marketing` (boolean)
- [X] T015 [US1] Implement **Bookings tab** in `ViewCustomer` â€” `RepeatableEntry` (or embedded `TableWidget`) reading `bookings` relationship; columns: `public_id`, `created_at` (date), `lifecycle_status` (badge), `payment_status` (badge), `fulfillment_status` (badge), `total_minor` (`->money('EGP', divideBy: 100)`). Paginate to 10 per page
- [X] T016 [US1] Implement **Reviews tab** in `ViewCustomer` â€” two `Section`s: "Service Reviews" (`serviceReviews` relationship: `rating`, `body` preview 80 chars, `created_at`) and "Vendor Reviews" (`vendorReviews` relationship: same columns). Empty-state shown when no reviews
- [X] T017 [US1] Implement **Wallet tab** in `ViewCustomer` â€” `RepeatableEntry` using a custom query: sum `loyalty_ledger.points` grouped by `loyalty_program_id` joined with `loyalty_programs` for the current customer. Show: program name (vendor name), point balance. Empty-state when no loyalty participation
- [X] T018 [US1] Implement **Addresses tab** in `ViewCustomer` â€” `RepeatableEntry` on `customerAddresses` relationship; columns: `label`, `city.name` (via relationship), `address_line`, `is_default` (boolean badge). Empty-state when no addresses
- [X] T019 [US1] Implement **Activity tab** in `ViewCustomer` â€” query `\Spatie\Activitylog\Models\Activity` where `subject_type = User::class` AND `subject_id = $record->id`, ordered `created_at DESC`, paginated 20. Show: `created_at`, `description`, `causer.name` (admin who acted), `properties` JSON diff. Empty-state when no audit entries
- [X] T020 [US1] Register `ViewCustomer` page in `CustomerResource::getPages()`: `'index' => Pages\ListCustomers::route('/')`, `'view' => Pages\ViewCustomer::route('/{record}')`

**Checkpoint**: US1 complete â€” admin can find any customer by search and browse all 6 tabs.

---

## Phase 4: User Story 2 â€” Edit Customer Profile Fields (Priority: P2)

**Goal**: Admin can edit customer name (EN + AR) and phone number with audit logging.

**Independent Test**: Open a customer record, click "Edit Profile", change the name, save â€” the new name appears immediately and an `activity_log` row exists for the change.

### Tests for US2

- [X] T021 [P] [US2] Add test: `it('admin can edit customer name and phone', ...)` in `tests/Feature/Modules/Identity/CustomerManagementTest.php` â€” assert `users` row updated + `activity_log` entry with `description = 'customer.profile_updated'`. Group: `identity`, `customer-management`
- [X] T022 [P] [US2] Add test: `it('edit profile fails validation on empty name', ...)` â€” assert 422 / validation message shown; no DB change

### Implementation for US2

- [X] T023 [US2] Create `app/Modules/Identity/Application/Actions/AdminUpdateCustomerProfileAction.php` â€” accepts `(User $customer, AdminUpdateCustomerDTO $dto)`. Snapshot old values, call `$customer->update($dto->toArray())` inside `DB::transaction`, then call `activity()->on($customer)->causedBy(auth()->user())->withProperties(['old' => $old, 'new' => $dto->toArray()])->log('customer.profile_updated')` (outside transaction using `DB::afterCommit` or after the transaction block)
- [X] T024 [US2] Add `Action::make('edit_profile')` to `CustomerResource` row actions â€” opens a Filament modal form with `TextInput::make('name')` (required, max 255), `TextInput::make('phone_e164')` (nullable, E.164 format hint). On submit call `AdminUpdateCustomerProfileAction::execute()`. Requires `update_customer_profile` permission. Show `Notification::make()->success()` on save
- [X] T025 [US2] Add `Action::make('edit_profile')` to `ViewCustomer` page header actions (same action, same form) so admin can edit from the detail view as well as the list

**Checkpoint**: US2 complete â€” admin can edit name/phone; audit trail confirmed.

---

## Phase 5: User Story 3 â€” Suspend / Unsuspend a Customer (Priority: P2)

**Goal**: Admin suspends a customer â†’ tokens revoked + login blocked + audit written. Admin unsuspends â†’ login restored.

**Independent Test**: `app(SuspendCustomerAction::class)->execute($customer)` â†’ `$customer->status === 'suspended'` AND `$customer->tokens()->count() === 0` AND login returns 422 AND `activity_log` has the entry.

### Tests for US3

- [X] T026 [P] [US3] Add test: `it('suspended customer cannot log in', ...)` in `tests/Feature/Modules/Identity/CustomerManagementTest.php` â€” suspend customer via action; attempt login via `POST /api/v1/auth/login`; assert 422. Group: `identity`, `customer-management`
- [X] T027 [P] [US3] Add test: `it('suspended customer gets 403 on authenticated endpoint', ...)` â€” create token before suspension; suspend; use old token; assert 403 with `errors.0.code = account_suspended`. Group: `identity`, `customer-management`
- [X] T028 [P] [US3] Add test: `it('admin cannot suspend their own account', ...)` â€” assert `DomainException` thrown. Group: `identity`, `customer-management`
- [X] T029 [P] [US3] Add test: `it('suspension creates an audit log entry', ...)` â€” assert `activity_log` row with `subject_type = User::class`, `description = customer.suspended`. Group: `identity`, `customer-management`
- [X] T030 [P] [US3] Add test: `it('unsuspend restores login ability', ...)` â€” suspend then unsuspend; assert login succeeds and `activity_log` has `customer.unsuspended`. Group: `identity`, `customer-management`

### Implementation for US3

- [X] T031 [US3] Create `app/Modules/Identity/Application/Actions/SuspendCustomerAction.php` â€” guard: `$customer->id === auth()->id()` â†’ throw `DomainException('admin_cannot_self_suspend')`; guard: `$customer->status === 'suspended'` â†’ throw `DomainException('customer_already_suspended')`. Inside `DB::transaction`: `$customer->update(['status' => 'suspended'])`, `$customer->tokens()->delete()`, `activity()->on(...)->log('customer.suspended')`. Fire `CustomerSuspended` event via `DB::afterCommit`
- [X] T032 [US3] Create `app/Modules/Identity/Application/Actions/UnsuspendCustomerAction.php` â€” guard: `$customer->status !== 'suspended'` â†’ throw `DomainException('customer_not_suspended')`. Inside `DB::transaction`: `$customer->update(['status' => 'active'])`, `activity()->on(...)->log('customer.unsuspended')`. Fire `CustomerUnsuspended` event via `DB::afterCommit`
- [X] T033 [US3] Add `Action::make('suspend')` to `CustomerResource` row actions â€” modal confirmation "Are you sure you want to suspend {name}?"; calls `SuspendCustomerAction`; `->visible(fn ($r) => $r->status === 'active' && $r->id !== auth()->id())`; guarded by `suspend_customer` permission; icon `heroicon-o-no-symbol`, color `danger`; show success `Notification` on complete
- [X] T034 [US3] Add `Action::make('unsuspend')` to `CustomerResource` row actions â€” confirmation modal; calls `UnsuspendCustomerAction`; `->visible(fn ($r) => $r->status === 'suspended')`; guarded by `suspend_customer` permission; icon `heroicon-o-check-circle`, color `success`
- [X] T035 [US3] Mirror both `suspend` and `unsuspend` actions on `ViewCustomer` page header actions so admin can act from the detail page

**Checkpoint**: US3 complete â€” suspend works end-to-end; Pest tests green.

---

## Phase 6: User Story 4 â€” Force Logout a Customer (Priority: P3)

**Goal**: Admin revokes all tokens for a customer without changing account status; customer's next API call returns 401.

**Independent Test**: Create a token for customer; admin calls `ForceLogoutCustomerAction`; `$customer->tokens()->count() === 0`; using the old token returns 401.

### Tests for US4

- [X] T036 [P] [US4] Add test: `it('force logout revokes all tokens', ...)` in `tests/Feature/Modules/Identity/CustomerManagementTest.php` â€” create token; force-logout; assert `tokens()->count() === 0`; using old token returns 401. Group: `identity`, `customer-management`
- [X] T037 [P] [US4] Add test: `it('force logout on customer with no tokens completes silently', ...)` â€” no tokens; action completes without error; success notification. Group: `identity`, `customer-management`
- [X] T038 [P] [US4] Add test: `it('force logout creates audit log entry', ...)` â€” assert `activity_log` row with `description = customer.force_logout`. Group: `identity`, `customer-management`

### Implementation for US4

- [X] T039 [US4] Create `app/Modules/Identity/Application/Actions/ForceLogoutCustomerAction.php` â€” inside `DB::transaction`: `$customer->tokens()->delete()`, `activity()->on($customer)->causedBy(auth()->user())->log('customer.force_logout')`. Return `void`
- [X] T040 [US4] Add `Action::make('force_logout')` to `CustomerResource` row actions â€” confirmation modal "This will revoke all active sessions for {name}."; calls `ForceLogoutCustomerAction`; guarded by `force_logout_customer` permission; icon `heroicon-o-arrow-right-on-rectangle`, color `warning`; success `Notification` on complete
- [X] T041 [US4] Mirror `force_logout` action on `ViewCustomer` page header actions

**Checkpoint**: US4 complete â€” force logout works; Pest tests green.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Shield permissions, final wiring, code quality.

- [X] T042 Run `php artisan shield:generate --all` and verify the following permissions exist in the `permissions` table: `view_any_customer`, `view_customer`, `update_customer_profile`, `suspend_customer`, `force_logout_customer`
- [ ] T043 Assign permissions to roles in the database seeder or via `shield:install`: `super-admin` gets all 5; `customer-manager` gets `view_any_customer`, `view_customer`, `update_customer_profile`, `force_logout_customer` (NOT `suspend_customer`)
- [X] T044 Run `php artisan filament:cache-components` and verify no errors
- [X] T045 [P] Run `./vendor/bin/pint app/Modules/Identity/` â€” fix any formatting issues in all new files
- [X] T046 [P] Run `./vendor/bin/phpstan analyse app/Modules/Identity/` â€” resolve any type errors
- [X] T047 Run `./vendor/bin/pest --filter=CustomerManagementTest --bail` â€” all tests must pass green
- [ ] T048 Manual QA â€” open Filament admin, navigate to Customers, verify: search by name works, search by email works, search by phone works, all 6 tabs load, suspend/unsuspend toggle works, force-logout action executes, edit profile saves, no JS console errors in EN locale
- [ ] T049 Manual QA â€” switch Filament locale to AR (Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©), verify all labels and notifications render in Arabic, suspension error message appears in Arabic

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies â€” start immediately; T001, T002, T003 all parallel
- **Phase 2 (Foundational)**: Depends on Phase 1 â€” BLOCKS Phase 3+ (middleware must be active for E2E tests to pass)
- **Phase 3 (US1)**: Depends on Phase 2 â€” CustomerResource skeleton needed before action buttons in US2/US3/US4
- **Phase 4 (US2)**: Depends on Phase 3 (CustomerResource must exist to add actions to it)
- **Phase 5 (US3)**: Depends on Phase 2 (middleware) + Phase 3 (CustomerResource) â€” independent of Phase 4
- **Phase 6 (US4)**: Depends on Phase 3 (CustomerResource) â€” independent of Phase 4 and Phase 5
- **Phase 7 (Polish)**: Depends on all user story phases

### User Story Dependencies

- **US1 (P1)**: Depends on Foundational (Phase 2) â€” independent of all other stories
- **US2 (P2)**: Depends on US1 completion (CustomerResource must exist) â€” independent of US3, US4
- **US3 (P2)**: Depends on Foundational (Phase 2) + US1 (CustomerResource) â€” independent of US2
- **US4 (P3)**: Depends on US1 (CustomerResource) â€” independent of US2, US3

### Within Each Phase

- Domain events â†’ DTOs â†’ Actions â†’ Filament wiring
- Tests written before or alongside the Action implementation (spec requires tests)
- `shield:generate` after all Filament Resources are complete

### Parallel Opportunities

- T001, T002, T003 (Phase 1 events + DTO) can run together
- T007, T008 (EN + AR translations) can run together
- Within US3 tests: T026, T027, T028, T029, T030 can all be written in parallel (different `it()` blocks in the same file)
- T045, T046 (Pint + PHPStan) can run in parallel

---

## Parallel Examples

```bash
# Phase 1 â€” run together:
Task: T001 â€” Create CustomerSuspended event
Task: T002 â€” Create CustomerUnsuspended event
Task: T003 â€” Create AdminUpdateCustomerDTO

# Phase 2 translations â€” run together:
Task: T007 â€” EN translation keys
Task: T008 â€” AR translation keys

# US3 Pest tests â€” write together before implementing actions:
Task: T026 â€” Test: suspended customer cannot log in
Task: T027 â€” Test: suspended customer gets 403
Task: T028 â€” Test: admin cannot self-suspend
Task: T029 â€” Test: suspension audit log
Task: T030 â€” Test: unsuspend restores login

# Polish â€” run together:
Task: T045 â€” Pint format check
Task: T046 â€” PHPStan analyse
```

---

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Phase 1: Setup (T001â€“T003)
2. Complete Phase 2: Foundational (T004â€“T008) â€” middleware active
3. Complete Phase 3: US1 (T009â€“T020) â€” CustomerResource with all 6 tabs
4. **STOP and VALIDATE**: Admin can search and browse any customer's footprint
5. This alone satisfies exit criterion: "Admin sees customer's full footprint"

### Full Day-1 Delivery (All 4 Stories)

1. Phase 1 (â‰ˆ15 min)
2. Phase 2 (â‰ˆ30 min)
3. Phase 3 US1 (â‰ˆ90 min) â€” bulk of the Filament work
4. Phase 4 US2 (â‰ˆ30 min) â€” edit action
5. Phase 5 US3 (â‰ˆ45 min) â€” suspend/unsuspend + tests
6. Phase 6 US4 (â‰ˆ20 min) â€” force-logout + tests
7. Phase 7 Polish (â‰ˆ30 min) â€” shield, pint, phpstan, manual QA

---

## Notes

- `[P]` tasks touch different files â€” safe to parallelize
- `[US?]` labels map to spec.md user story priorities
- Tests required by spec â€” write them before or alongside Actions (not deferred)
- Run `shield:generate --all` ONCE after all Filament Resources are finalized (T042)
- `CustomerProfileResource` (existing stub) is NOT removed â€” it remains under "Identity" group as a simple list; `CustomerResource` is new under "Users" group with the full feature
- Cross-module display reads (`bookings`, `service_reviews`, `vendor_reviews`, `loyalty_ledger`) are Phase 1 pragmatism â€” no business logic, admin-only, read-only
