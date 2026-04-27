# Contract: Module ServiceProvider

**Applies to**: Every module under `app/Modules/{Name}/`
**Enforced by**: `pestphp/pest-plugin-arch` architecture tests (Phase 7)

---

## Required structure

Every module MUST have exactly one ServiceProvider at:
```
app/Modules/{Name}/Providers/{Name}ServiceProvider.php
```

It MUST extend `Illuminate\Support\ServiceProvider`.

---

## Required `boot()` registrations

```php
public function boot(): void
{
    // 1. Module migrations
    $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

    // 2. Module translations (if any lang files exist)
    $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'module-key');

    // 3. Module routes (if the module has HTTP routes)
    Route::middleware('api')
        ->prefix('api/v1')
        ->group(__DIR__ . '/../Routes/customer.php');

    Route::middleware(['web', 'auth:sanctum'])
        ->group(__DIR__ . '/../Routes/admin.php');

    // 4. Module event listeners
    // Event::listen(SomeEvent::class, SomeListener::class);

    // 5. Module policies
    // Gate::policy(SomeModel::class, SomePolicy::class);
}
```

---

## Required `register()` bindings

```php
public function register(): void
{
    // Bind module Contracts to their Infrastructure implementations
    // $this->app->bind(
    //     \App\Modules\{Name}\Domain\Contracts\SomeRepository::class,
    //     \App\Modules\{Name}\Infrastructure\Repositories\EloquentSomeRepository::class,
    // );
}
```

---

## Registration in bootstrap/providers.php

Every module ServiceProvider MUST be listed in `bootstrap/providers.php`:

```php
return [
    App\Modules\Shared\Providers\SharedServiceProvider::class,
    App\Modules\Geography\Providers\GeographyServiceProvider::class,
    App\Modules\Identity\Providers\IdentityServiceProvider::class,
    // ... other modules
];
```

**Order matters**: modules that depend on another module's migrations must appear AFTER that module.

---

## Phase 0 minimal ServiceProvider (migrations only)

For modules where Phase 0 only applies migrations (no routes, no listeners):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        // Phase 1: bind repository contracts
    }
}
```

---

## Cross-module communication rules

- A module MUST NOT import another module's Eloquent Model directly.
- Cross-module communication goes through: domain events, public Contracts in `Domain/Contracts/`, or Repository DTOs.
- If you find yourself `use App\Modules\OtherModule\Domain\Models\SomeModel` inside an Action or Listener, introduce a Contract instead.
