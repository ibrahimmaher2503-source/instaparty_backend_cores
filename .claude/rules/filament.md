---
description: Rules for Filament v3 resources, pages, and widgets
globs:
  - "app/Modules/*/Filament/**/*.php"
  - "app/Filament/**/*.php"
---

# Filament v3 Rules

## Resources are auto-discovered per module

InstaParty registers Filament resources from each `app/Modules/*/Filament/Resources/` folder. When creating a new Resource, place it under the matching module's Filament directory — do **not** put module resources in the root `app/Filament/`.

## Per-product-type Resources

Services have **three Resources**, never one:

- `RentalServiceResource`
- `SaleServiceResource`
- `DigitalServiceResource`

Each of them:

- Lives in `app/Modules/Catalog/Filament/Resources/`.
- Sets `protected static ?string $navigationGroup = 'Services';`
- Uses a model scope to filter `services` to its own `product_type` only.
- Has a form schema tailored to its detail table — no nullable conditional fields shared across types.

A unified "All Services" admin page is allowed for monitoring (view-only) but it does not replace the three editable Resources.

## Translatable fields

Use `filament/spatie-laravel-translatable-plugin`. Inside the Resource:

```php
use Filament\Resources\Concerns\Translatable;

class RentalServiceResource extends Resource
{
    use Translatable;
    // ...

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }
}
```

Form fields that are translatable get `->translatable()`. Tables show the value in the admin's current locale.

## Permissions (Shield)

After creating any new Resource:

```bash
php artisan shield:generate --all
```

Permissions follow the per-product-type pattern for Service resources:

- `view_any_rental_service`, `create_rental_service`, `update_rental_service`, `delete_rental_service`, `publish_rental_service`
- (same for `sale` and `digital`)

For non-service Resources, default Shield naming applies.

## Forms

- Two language tabs (English, العربية) at the top of every form that edits translatable content.
- Required-field validation enforced on **both** tabs before save (use `->required()` on both `_en` and `_ar` variants when manually splitting, or rely on the translatable plugin's all-locales requirement).
- Use `Forms\Components\Section` to group related fields, never a single tall column.

## Tables

- Default sort by `created_at desc`.
- Show a small flag indicator when a translation is missing for a record (custom column).
- For Service tables, show `product_type` as a badge column with type-colored styling.

## What forbids a Filament resource

- Putting resources directly in `app/Filament/` instead of the module's folder — **fail**.
- A single ServiceResource trying to handle all three types via tabs/conditionals — **fail**.
- Editing translatable fields without language tabs — **fail**.
- Adding a Resource without running `shield:generate --all` afterwards — **flag**.
