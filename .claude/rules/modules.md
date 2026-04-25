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
│   └── admin.php
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
| Controller | `Http/Controllers/` (3-line action body MAX) |
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
