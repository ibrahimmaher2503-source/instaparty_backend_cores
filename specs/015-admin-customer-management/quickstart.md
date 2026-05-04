# Quickstart: Admin Customer Management

**Feature**: Phase 6.3
**Estimated time**: 1 day

---

## Prerequisites

- Phase 6.0 complete (`audit_logs` wired via `spatie/laravel-activitylog`)
- Phase 6.2 complete (app boots cleanly with all providers registered)
- `filament-shield` installed and permissions seeded

---

## What Gets Built

```
app/Modules/Identity/
├── Application/Actions/
│   ├── SuspendCustomerAction.php          (new)
│   ├── UnsuspendCustomerAction.php        (new)
│   └── ForceLogoutCustomerAction.php      (new)
├── Domain/Events/
│   ├── CustomerSuspended.php              (new)
│   └── CustomerUnsuspended.php            (new)
├── Http/Middleware/
│   └── EnsureAccountIsActive.php          (new)
└── Filament/Resources/
    ├── CustomerResource.php               (new — replaces stub)
    └── CustomerResource/Pages/
        ├── ListCustomers.php
        └── ViewCustomer.php
```

**Patched files**:
- `Application/Actions/LoginAction.php` — add suspension check
- `Providers/IdentityServiceProvider.php` — register `EnsureAccountIsActive` middleware
- `Resources/lang/en/identity.php` + `ar/identity.php` — new translation keys

---

## Build Order

1. **Domain Events** — `CustomerSuspended`, `CustomerUnsuspended` (simple event classes, no listeners yet)
2. **Actions** — `SuspendCustomerAction`, `UnsuspendCustomerAction`, `ForceLogoutCustomerAction`
3. **Middleware** — `EnsureAccountIsActive` + register in ServiceProvider
4. **Patch LoginAction** — add `status === suspended` check
5. **CustomerResource** — Filament resource with list + view (tabbed detail)
6. **Translations** — EN + AR keys for all new UI strings
7. **`shield:generate --all`**
8. **Pest tests** — suspend → can't login; force logout → tokens gone

---

## Key Commands

```bash
# After building
php artisan shield:generate --all
php artisan filament:cache-components
./vendor/bin/pest --filter=CustomerManagementTest
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

---

## Validation Checklist

- [ ] Suspended customer receives 403 on any authenticated API endpoint
- [ ] Suspended customer receives 403 on login attempt
- [ ] Force logout deletes all tokens; next request → 401
- [ ] Admin cannot suspend their own account
- [ ] Every action has an audit log entry
- [ ] Both EN and AR error messages display correctly
- [ ] Search by name / email / phone finds customers in Filament list
- [ ] All 6 tabs load without errors (including empty-state tabs)
- [ ] `shield:generate` ran; permissions exist in DB
