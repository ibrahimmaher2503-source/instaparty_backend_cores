---
description: Generate per-product-type Filament resource scaffolding (rental + sale + digital)
argument-hint: <entity-name> (usually "Service")
---

# Generate per-type Filament resources

You're about to generate **three** Filament resources for **$ARGUMENTS** (one per product type) in `app/Modules/Catalog/Filament/Resources/`.

## Required reading first

1. `.claude/rules/filament.md` — translatable plugin, Shield, language tabs, navigation group.
2. `.claude/rules/product-types.md` — type-specific form fields, model scopes.
3. `docs/specs/03_Three_Product_Types.md` §4 — exact per-type detail-table columns to render in forms.

## Plan first

List:

- Three Resource classes you'll create (`Rental$ARGUMENTSResource`, `Sale$ARGUMENTSResource`, `Digital$ARGUMENTSResource`).
- The form schema for each (sections, fields per detail table).
- The table columns for each (badges, sortable columns, filters).
- The model scope each Resource uses to filter `services` to its own type.
- The Shield permissions that will be generated.

Wait for confirmation before generating files.

## After confirmation

Generate the three Resources. Each one:

- Uses `use Filament\Resources\Concerns\Translatable;`
- Sets `protected static ?string $navigationGroup = 'Services';`
- Sets a unique `$navigationLabel` per type ('Rentals', 'Sale Items', 'Digital Items').
- Filters on `product_type` via Eloquent scope.
- Uses translatable form fields with `->translatable()`.
- Pulls type-specific fields from the matching detail table (rental → `service_rental_details`, etc.).

After the three files are written:

```bash
php artisan shield:generate --all
php artisan filament:cache-components
```

## Reminders

- Never one Resource with conditional fields per type. Always three Resources.
- Both EN and AR fields must be filled before save.
- Add a per-type colored badge to the table for `product_type`.
