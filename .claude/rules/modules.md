---
description: Rules for the modular monolith structure under app/Modules/
globs:
  - "app/Modules/**/*.php"
---

# Module Structure Rules

## Layer layout (mandatory)

Every module follows this exact layout:

```
app/Modules/{Name}/
├── Domain/
│   ├── Models/
│   ├── Enums/
│   ├── Events/
│   ├── States/
│   └── Contracts/
├── Application/
│   ├── Actions/
│   ├── Services/
│   ├── DTOs/
│   └── Listeners/
├── Infrastructure/
│   ├── Repositories/
│   └── Gateways/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Filament/
│   └── Resources/
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   ├── admin.php
│   └── storefront.php     # optional — Blade storefront pages
├── Database/
│   └── Migrations/
├── Resources/
│   └── lang/{en,ar}/
└── Providers/
    └── {Name}ServiceProvider.php
```

## Cross-module communication

- ❌ Never import another module's Eloquent Model directly.
- ✅ Communicate through:
  1. **Domain events** (preferred — broadcast facts).
  2. **Public contracts** in `Domain/Contracts/` — the consuming module depends on the interface, the producing module binds the implementation in its ServiceProvider.
  3. **Repository methods** that return DTOs, never models.

When you find yourself reaching into another module's `Domain/Models/`, that's the signal to introduce a Contract.

## Module ServiceProvider

Each module's ServiceProvider must register, in `boot()`:

- Module migrations: `$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');`
- Module translations: `$this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'module-key');`
- Module routes: load from `Routes/customer.php`, `Routes/vendor.php`, `Routes/admin.php` with proper middleware groups.
- Module event listeners.
- Module policies.

In `register()`:

- Bind module Contracts to their Infrastructure implementations.

## Storefront routes (Blade) — auto-discovered, do NOT register manually

`Routes/storefront.php` is the one route file a ServiceProvider must **not** load.
`routes/web.php` discovers every module's `Routes/storefront.php` by glob and wraps
them all in a single group that supplies:

- the `web` middleware group,
- `SetStorefrontLocaleMiddleware` (Shared),
- the `{locale}` URL prefix, constrained to the supported locales,
- the `storefront.` route-name prefix.

So a module's `storefront.php` declares bare paths only:

```php
// app/Modules/Catalog/Routes/storefront.php
Route::get('services/{publicId}', ServicePageController::class)->name('services.show');
// -> GET /{locale}/services/{publicId}, named storefront.services.show
```

Never re-declare the prefix or middleware inside a module file — you would nest
`{locale}` twice. Adding a new module's storefront routes needs no wiring at all.

**Never type-hint `$locale` in a storefront controller.** The middleware removes it
from the route's parameter bag, because Laravel binds controller arguments
positionally and `{locale}` is the first parameter of every storefront route — a
`__invoke(string $publicId)` would otherwise silently receive `"ar"`. Read the locale
from `app()->getLocale()`. Guarded by `tests/Feature/Storefront/StorefrontRoutingTest.php`.

## Phase 1 modules

Identity, Catalog, Discovery, Booking, Negotiation, Payments, Settlement, Reviews, Communication, Reporting, Shared.

If you propose a new module, name the bounded context it represents and which existing module it cannot live inside.

## Where things go

| Concept | Location |
|---|---|
| Eloquent model | `Domain/Models/` (relationships, casts, scopes ONLY) |
| Enum | `Domain/Enums/` |
| Domain event | `Domain/Events/` |
| State machine | `Domain/States/` (spatie/laravel-model-states) |
| Public interface (cross-module) | `Domain/Contracts/` |
| Application use case | `Application/Actions/{Verb}{Noun}Action.php` |
| Long-running orchestration | `Application/Services/{Name}Service.php` |
| Data carrier between layers | `Application/DTOs/` |
| Event listener | `Application/Listeners/` |
| Repository (Eloquent) | `Infrastructure/Repositories/` |
| External-system adapter (Paymob, Mailchimp, Firebase, Meilisearch) | `Infrastructure/Gateways/` |
| Controller (API) | `Http/Controllers/` (3-line action body MAX) |
| Controller (Blade storefront) | `Http/Controllers/Web/` (same 3-line rule) |
| Livewire component (storefront) | `Http/Livewire/` |
| FormRequest | `Http/Requests/` |
| API Resource | `Http/Resources/` (locale conversion happens here) |
| Module-specific middleware | `Http/Middleware/` |
| Filament Resource | `Filament/Resources/` |

## What forbids a module change

- An Eloquent Model from one module imported into another module's Action — **fail**.
- A new top-level folder under `app/Modules/{Name}/` that isn't in the list above — **fail unless** explicitly justified.
- A module without a ServiceProvider — **fail**.
- Filament resources outside the module's `Filament/Resources/` — **fail**.

## Naming

- Modules: PascalCase singular noun (`Catalog`, not `Catalogs`).
- Actions: `{Verb}{Noun}Action` cross-type, `{Verb}{Type}{Noun}Action` per-type.
- Repositories: `{Noun}Repository`. The interface lives in `Domain/Contracts/{Noun}Repository.php`, the Eloquent implementation in `Infrastructure/Repositories/Eloquent{Noun}Repository.php`.
- Gateways: `{System}Gateway`. Interface in `Domain/Contracts/`, implementation in `Infrastructure/Gateways/`.
