# Data Model: Admin Customer Management

**Feature**: Phase 6.3 — Admin Customer Management
**Schema changes**: NONE — read-only from existing tables + status field already on `users`

---

## Tables Read (no schema changes)

### `users` (Identity module)

Key columns consumed by this feature:

| Column | Type | Used for |
|---|---|---|
| `id` | BIGINT PK | Join key |
| `public_id` | CHAR(26) | API/URL exposure |
| `name` | VARCHAR | List display, search |
| `email` | VARCHAR | List display, search |
| `phone_e164` | VARCHAR | List display, search |
| `status` | VARCHAR | `'active'` / `'suspended'` — badge in list + suspension actions |
| `last_login_at` | TIMESTAMP | Overview tab |
| `preferred_locale` | VARCHAR | Read-only display |
| `created_at` | TIMESTAMP | Join date in overview |

**Scope**: `User::scopeCustomers()` — filters to users with `customer` role (already defined on model).

**Mutation** (suspension only):
- `users.status` → updated to `'suspended'` / `'active'` by `SuspendCustomerAction` / `UnsuspendCustomerAction`
- `personal_access_tokens` rows deleted by `SuspendCustomerAction` and `ForceLogoutCustomerAction`

---

### `customer_profiles` (Identity module, 1:1 with `users`)

| Column | Used for |
|---|---|
| `date_of_birth` | Overview tab |
| `gender` | Overview tab |
| `accepts_marketing` | Overview tab (badge) |
| `children` | Overview tab (count) |

**Mutation**: `AdminUpdateCustomerProfileAction` may update `users.name` and `users.phone_e164` only. No `customer_profiles` fields are admin-editable in Phase 6.3.

---

### `customer_addresses` (Identity module, HasMany from `users`)

Read-only display in Addresses tab. Columns shown: `label`, `address_line`, city name (via `city_id` → `cities`), `is_default`, `recipient_name`.

---

### `bookings` (Booking module, HasMany from `users` via `user_id`)

Read-only display in Bookings tab (paginated list). Columns shown: `public_id`, `created_at`, `lifecycle_status`, `payment_status`, `fulfillment_status`, `total_minor` / `total_currency`.

Cross-module access: read via `Booking` model imported **directly** in the Filament resource for display only. This is a known Phase 1 pragmatism — the module boundaries rule is relaxed for read-only Filament display in admin panels (no mutation, no business logic).

---

### `service_reviews` + `vendor_reviews` (Reviews module)

Read-only in Reviews tab. Columns: `rating`, `body` (translatable), `created_at`, `service_id` / `vendor_profile_id` for label display.

Same cross-module pragmatism as bookings above.

---

### `loyalty_ledger` + `loyalty_programs` (Loyalty module)

Read-only in Wallet tab. Aggregate query:

```sql
SELECT
    lp.id,
    lp.vendor_profile_id,
    SUM(ll.points) AS balance
FROM loyalty_ledger ll
JOIN loyalty_programs lp ON lp.id = ll.loyalty_program_id
WHERE ll.user_id = ?
GROUP BY lp.id, lp.vendor_profile_id
```

---

### `audit_logs` (Cross-cutting — backed by `spatie/laravel-activitylog`)

Read-only in Activity tab. Query:

```php
Activity::query()
    ->where('subject_type', User::class)
    ->where('subject_id', $customer->id)
    ->orderByDesc('created_at')
    ->paginate(20);
```

Columns shown: `created_at`, `log_name` / `description`, `causer` (admin name), `properties`.

**Write**: Every admin action (`suspend`, `unsuspend`, `force_logout`, `edit_profile`) writes to `audit_logs` via `activity()->on($user)->causedBy(auth()->user())->log(...)`.

---

## New Domain Events

| Event | Payload | Fired |
|---|---|---|
| `CustomerSuspended` | `User $customer, int $actorId` | `DB::afterCommit` in `SuspendCustomerAction` |
| `CustomerUnsuspended` | `User $customer, int $actorId` | `DB::afterCommit` in `UnsuspendCustomerAction` |

No listeners registered in Phase 6.3. Events are fired for future use (e.g., notification "your account has been suspended").

---

## Shield Permission Set

Run after resource creation:

```bash
php artisan shield:generate --all
```

Expected permissions generated:

| Permission | Holder |
|---|---|
| `view_any_customer` | super-admin, customer-manager |
| `view_customer` | super-admin, customer-manager |
| `update_customer_profile` | super-admin, customer-manager |
| `suspend_customer` | super-admin |
| `force_logout_customer` | super-admin, customer-manager |

`suspend_customer` is **super-admin only** — customer-managers can view and force-logout but cannot suspend.

---

## Middleware: EnsureAccountIsActive

```
app/Modules/Identity/Http/Middleware/EnsureAccountIsActive.php
```

Registered on `sanctum` middleware group in `IdentityServiceProvider`. Fires `403` with bilingual body:

```json
{
  "errors": [{ "code": "account_suspended", "message": "Your account has been suspended. / تم تعليق حسابك." }]
}
```
