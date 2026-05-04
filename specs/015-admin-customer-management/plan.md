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

# Implementation Plan: Admin Customer Management

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Phase**: 6.3 — Week 7 | **PRD FR coverage**: FR-22, FR-25, FR-26

---

## Summary

Build a purpose-built Filament `CustomerResource` under the Identity module that gives super-admins and customer-managers a full view of any customer's footprint (profile, bookings, reviews, loyalty, addresses, audit activity) plus three administrative actions (suspend, unsuspend, force-logout) backed by dedicated Action classes. Suspension is enforced at two layers: `LoginAction` blocks new tokens, and a new `EnsureAccountIsActive` middleware blocks existing tokens. No schema changes needed — `users.status` already exists.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Filament v3, spatie/laravel-activitylog, spatie/laravel-permission, filament-shield, Laravel Sanctum
**Storage**: MySQL 8 — existing tables only (`users`, `customer_profiles`, `customer_addresses`, `bookings`, `service_reviews`, `vendor_reviews`, `loyalty_ledger`, `loyalty_programs`, `audit_logs`)
**Testing**: Pest (Feature + Unit)
**Target Platform**: Filament admin panel (`/admin`) + Sanctum-guarded API routes
**Project Type**: Modular Laravel monolith — Identity module
**Performance Goals**: Search results appear within 2 seconds; tab data loads within 1 second
**Constraints**: No schema changes; no new packages; auth-layer enforcement must be synchronous (no async jobs)
**Scale/Scope**: Phase 6.3 scope — 1 day, Filament admin only (no new API endpoints)

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Applies | Status | Notes |
|---|---|---|---|
| I. Modular Monolith | ✅ | **PASS** | All code in `app/Modules/Identity/`. Cross-module display-only reads in Filament are a known Phase 1 pragmatism. |
| II. Three Product Types | ✅ | **PASS** | Bookings tab shows `product_type` badge; no type-aware mutations in this feature. |
| III. Money Discipline | ✅ | **PASS** | Booking total displayed via `->money('EGP', divideBy: 100)` — never raw `_minor` values. |
| IV. Bilingual EN+AR | ✅ | **PASS** | Suspension error message must exist in both EN + AR. All Filament labels use translation keys. |
| V. Append-Only Tables | ✅ | **PASS** | `audit_logs` is only written (append) and read. No UPDATE or DELETE on audit rows. |
| VI. Spec-Driven (ADR) | ✅ | **PASS** | No new module. Identity module ADR already accepted. |
| VII. Test-First | ✅ | **PASS** | Pest tests written same day: suspend → 403 login, force-logout → 401 token. |
| VIII. Idempotency | N/A | **SKIP** | No new payment-mutating endpoints. Admin actions via Filament do not require idempotency keys. |
| IX. DB::afterCommit | ✅ | **PASS** | `CustomerSuspended` and `CustomerUnsuspended` events fire `DB::afterCommit`. |
| X. Vendor Approval Gate | N/A | **SKIP** | This feature is about customers, not vendor approval. |
| XI. Document Storage | N/A | **SKIP** | No file uploads in this feature. |

**Gate result: PASS** — proceed to implementation.

---

## Project Structure

### Documentation (this feature)

```text
specs/015-admin-customer-management/
├── plan.md              ← this file
├── spec.md
├── research.md
├── data-model.md
├── quickstart.md
└── checklists/requirements.md
```

### Source Code

```text
app/Modules/Identity/
├── Application/Actions/
│   ├── SuspendCustomerAction.php          (NEW)
│   ├── UnsuspendCustomerAction.php        (NEW)
│   └── ForceLogoutCustomerAction.php      (NEW)
│   └── [PATCH] LoginAction.php            (add suspension check)
├── Application/DTOs/
│   └── AdminUpdateCustomerDTO.php         (NEW — name+phone only)
├── Domain/Events/
│   ├── CustomerSuspended.php              (NEW)
│   └── CustomerUnsuspended.php            (NEW)
├── Http/Middleware/
│   └── EnsureAccountIsActive.php          (NEW)
├── Filament/Resources/
│   ├── CustomerResource.php               (NEW — replaces stub CustomerProfileResource)
│   └── CustomerResource/Pages/
│       ├── ListCustomers.php              (NEW)
│       └── ViewCustomer.php              (NEW — tabbed detail page)
├── Providers/
│   └── [PATCH] IdentityServiceProvider.php  (register middleware)
└── Resources/lang/
    ├── en/identity.php                    (PATCH — new keys)
    └── ar/identity.php                    (PATCH — new keys)

tests/Feature/Modules/Identity/
└── CustomerManagementTest.php             (NEW)
```

---

## Build Sequence

### Step 1 — Domain Events (no dependencies)

Create two simple event classes:

**`CustomerSuspended`**
```php
namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\User;

final class CustomerSuspended
{
    public function __construct(
        public readonly User $customer,
        public readonly int $actorId,
    ) {}
}
```

**`CustomerUnsuspended`** — same shape.

---

### Step 2 — Action Classes

#### `SuspendCustomerAction`

```php
namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\CustomerSuspended;
use App\Modules\Identity\Domain\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SuspendCustomerAction
{
    public function execute(User $customer): User
    {
        if ($customer->id === auth()->id()) {
            throw new DomainException('admin_cannot_self_suspend');
        }

        if ($customer->status === 'suspended') {
            throw new DomainException('customer_already_suspended');
        }

        $actorId = (int) auth()->id();

        return DB::transaction(function () use ($customer, $actorId): User {
            $customer->update(['status' => 'suspended']);
            $customer->tokens()->delete();

            activity()
                ->on($customer)
                ->causedBy(auth()->user())
                ->withProperties(['old' => ['status' => 'active'], 'new' => ['status' => 'suspended']])
                ->log('customer.suspended');

            DB::afterCommit(fn () => event(new CustomerSuspended($customer, $actorId)));

            return $customer;
        });
    }
}
```

#### `UnsuspendCustomerAction`

Mirror of above: sets `status` to `'active'`, fires `CustomerUnsuspended`, logs `'customer.unsuspended'`.

#### `ForceLogoutCustomerAction`

```php
public function execute(User $customer): void
{
    DB::transaction(function () use ($customer): void {
        $customer->tokens()->delete();

        activity()
            ->on($customer)
            ->causedBy(auth()->user())
            ->log('customer.force_logout');
    });
}
```

---

### Step 3 — AdminUpdateCustomerProfileAction

Wraps `UpdateCustomerProfileAction` with an audit log entry:

```php
public function execute(User $customer, AdminUpdateCustomerDTO $dto): User
{
    $old = ['name' => $customer->name, 'phone_e164' => $customer->phone_e164];

    $updated = DB::transaction(function () use ($customer, $dto): User {
        $customer->update($dto->toArray());
        return $customer->refresh();
    });

    activity()
        ->on($customer)
        ->causedBy(auth()->user())
        ->withProperties(['old' => $old, 'new' => $dto->toArray()])
        ->log('customer.profile_updated');

    return $updated;
}
```

---

### Step 4 — EnsureAccountIsActive Middleware

```php
namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status === 'suspended') {
            return response()->json([
                'errors' => [[
                    'code'    => 'account_suspended',
                    'message' => __('identity.account_suspended'),
                ]],
            ], 403);
        }

        return $next($request);
    }
}
```

Register in `IdentityServiceProvider::boot()`:

```php
$this->app['router']->pushMiddlewareToGroup('sanctum', EnsureAccountIsActive::class);
```

---

### Step 5 — Patch LoginAction

After credential verification, before token creation:

```php
if ($user->status === 'suspended') {
    throw ValidationException::withMessages([
        'login' => __('identity.account_suspended'),
    ]);
}
```

---

### Step 6 — CustomerResource (Filament)

**Model**: `User`, scoped by `scopeCustomers()`.
**Navigation group**: "Users"
**Navigation sort**: 30 (below VendorProfileResource)

**List table columns**:
- `name` — searchable, sortable
- `email` — searchable
- `phone_e164` — searchable, label "Phone"
- `status` — badge; `'active'` → `success`, `'suspended'` → `danger`
- `created_at` — label "Joined", dateTime, sortable

**Filters**: SelectFilter on `status` (active / suspended)

**Row actions**:
- `ViewAction::make()` — opens ViewCustomer page
- `Action::make('suspend')` — calls `SuspendCustomerAction`, requires confirmation, `->visible(fn ($r) => $r->status === 'active' && $r->id !== auth()->id())`, guarded by `suspend_customer` permission
- `Action::make('unsuspend')` — calls `UnsuspendCustomerAction`, requires confirmation, `->visible(fn ($r) => $r->status === 'suspended')`, guarded by `suspend_customer` permission
- `Action::make('force_logout')` — calls `ForceLogoutCustomerAction`, requires confirmation, guarded by `force_logout_customer` permission

---

### Step 7 — ViewCustomer (Tabbed Detail Page)

Custom `ViewRecord` page with `Tabs` layout (using Filament Infolist + RelationManagers or custom tab rendering).

**Tab: Overview**
Infolist with:
- `TextEntry::make('name')` (full name)
- `TextEntry::make('email')`
- `TextEntry::make('phone_e164')->label('Phone')`
- `TextEntry::make('status')->badge()`
- `TextEntry::make('preferred_locale')`
- `TextEntry::make('last_login_at')->dateTime()`
- `TextEntry::make('created_at')->label('Joined')->dateTime()`
- `TextEntry::make('customerProfile.date_of_birth')->date()`
- `TextEntry::make('customerProfile.gender')->badge()`
- `IconEntry::make('customerProfile.accepts_marketing')->boolean()`

**Tab: Bookings**
`RepeatableEntry` or a `TableWidget` showing `bookings` with `lifecycle_status`, `payment_status`, `fulfillment_status`, `total_minor`/`total_currency` (money column).

**Tab: Reviews**
Inline table of `serviceReviews` + `vendorReviews` — rating badge, body preview, date.

**Tab: Wallet**
Aggregate loyalty balance per vendor program (derived query as described in data-model.md).

**Tab: Addresses**
`RepeatableEntry` on `customerAddresses` — label, city name, address_line, is_default badge.

**Tab: Activity**
Paginated `Activity` log entries where `subject_type = User::class` AND `subject_id = $record->id`. Columns: created_at, log_name/description, causer name, properties diff.

---

### Step 8 — Translation Keys

**`en/identity.php`** additions:
```php
'account_suspended'            => 'Your account has been suspended. Contact support for assistance.',
'customer_already_suspended'   => 'This account is already suspended.',
'admin_cannot_self_suspend'    => 'You cannot suspend your own account.',
'nav.customers'                => 'Customers',
'actions.suspend_customer'     => 'Suspend Customer',
'actions.unsuspend_customer'   => 'Unsuspend Customer',
'actions.force_logout'         => 'Force Logout',
'actions.edit_profile'         => 'Edit Profile',
'tabs.overview'                => 'Overview',
'tabs.bookings'                => 'Bookings',
'tabs.reviews'                 => 'Reviews',
'tabs.wallet'                  => 'Wallet',
'tabs.addresses'               => 'Addresses',
'tabs.activity'                => 'Activity',
```

**`ar/identity.php`** additions — AR equivalents for all keys above.

---

## Pest Test Plan

**File**: `tests/Feature/Modules/Identity/CustomerManagementTest.php`

### Test: Suspend → Can't Login

```php
it('suspended customer cannot log in', function () {
    $customer = User::factory()->create(['status' => 'active']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);

    $customer->refresh();
    expect($customer->status)->toBe('suspended');

    // Attempt login
    $response = $this->postJson('/api/v1/auth/login', [
        'login'    => $customer->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['login']);
})->group('identity', 'customer-management');
```

### Test: Force Logout → Tokens Revoked

```php
it('force logout revokes all tokens', function () {
    $customer = User::factory()->create(['status' => 'active']);
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    actingAs($admin);

    app(ForceLogoutCustomerAction::class)->execute($customer);

    expect($customer->tokens()->count())->toBe(0);

    $this->withToken($token)
        ->getJson('/api/v1/customer/profile')
        ->assertUnauthorized();
})->group('identity', 'customer-management');
```

### Test: Suspended Customer Blocked on API

```php
it('suspended customer gets 403 on authenticated endpoint', function () {
    $customer = User::factory()->create(['status' => 'suspended']);
    $customer->assignRole('customer');
    $token = $customer->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/customer/profile')
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'account_suspended');
})->group('identity', 'customer-management');
```

### Test: Admin Cannot Self-Suspend

```php
it('admin cannot suspend their own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    actingAs($admin);

    expect(fn () => app(SuspendCustomerAction::class)->execute($admin))
        ->toThrow(DomainException::class, 'admin_cannot_self_suspend');
})->group('identity', 'customer-management');
```

### Test: Audit Log Written on Suspend

```php
it('suspension creates an audit log entry', function () {
    $customer = User::factory()->create(['status' => 'active']);
    $customer->assignRole('customer');

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    actingAs($admin);

    app(SuspendCustomerAction::class)->execute($customer);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id'   => $customer->id,
        'description'  => 'customer.suspended',
    ]);
})->group('identity', 'customer-management');
```

---

## Cut-List (if behind schedule)

| Item | Defer to | Notes |
|---|---|---|
| Wallet tab (loyalty balance query) | Phase 1.5 | Display "N/A — Loyalty Phase 5" temporarily |
| Activity tab pagination (>100 entries) | Phase 1.5 | Show latest 20 without pagination for now |
| AR translations for new keys | Phase 1.5 | EN only with EN fallback is functional |

Core deliverable (list + suspend + force-logout + tests) MUST ship.

---

## Exit Criteria

- ✅ Admin finds any customer in <5 seconds via search (name, email, phone)
- ✅ Admin sees customer's full footprint across all 6 tabs
- ✅ Suspend works end-to-end: action → status change → token revocation → login blocked → audit log entry
- ✅ Force logout works: tokens gone → next API call returns 401
- ✅ `shield:generate --all` run; permissions assigned to correct roles
- ✅ All Pest tests green
- ✅ EN + AR suspension messages tested
