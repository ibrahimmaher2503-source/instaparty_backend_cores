# Research: CMS Pages + Settings (Phase 6.2)

> Phase 0 output for `/speckit.plan` — resolves all NEEDS CLARIFICATION items before design.

---

## Decision 1 — Module Ownership: Shared Module

**Question**: Which module owns `cms_pages`, `app_settings`, and `feature_flags`?

**Decision**: `app/Modules/Shared/`

**Rationale**: Per `docs/specs/11_DB_Schema.md` §14 ("Cross-cutting (6)"), these three tables live in the same ownership bucket as `audit_logs`, `event_outbox`, `analytics_events`, and `customer_addresses`. The `schema-cheatsheet.md` confirms cross-cutting tables belong to Shared. The Shared module was established in Phase 0.0 to host `MoneyCast`, shared contracts, and utility classes. CMS and Settings fit naturally.

**Alternatives Considered**:
- New `CMS` module — rejected. Phase 1 scope is too small (3 tables, 1 day). A dedicated module would add ServiceProvider boilerplate with no cross-module benefit.
- `Reporting` module — rejected. Reporting owns analytics; CMS is content management, not reporting.

**ADR Status**: No dedicated ADR for the Shared module exists in `docs/adr/`. Decision: proceed without creating a new ADR since cross-cutting tables were explicitly described as "Shared" scope in the locked `11_DB_Schema.md`, which itself is a constitution-level document. An ADR-0002-shared-module.md should be created in Phase 7.2 documentation as cleanup.

---

## Decision 2 — Settings Plugin: Key-Value vs Typed DTO

**Question**: Should `app_settings` use `filament/spatie-laravel-settings-plugin`'s typed PHP DTO class pattern, or a raw key-value approach over the `app_settings` table?

**Decision**: Raw key-value Filament page over the `app_settings` table directly.

**Rationale**: `spatie/laravel-settings` (the underlying package) stores settings in PHP-class-typed DTOs and its own migrations — this conflicts with the locked `app_settings` table in `11_DB_Schema.md`. The locked schema uses a generic `(key VARCHAR, value JSON)` table. Using the plugin's typed pattern would either require abandoning the locked table or mapping a parallel table. Instead, we implement a Filament `ManageSettings` custom page that reads/writes `app_settings` rows directly, and a separate `ManageFeatureFlags` panel. The `filament/spatie-laravel-settings-plugin` is still used for the admin settings page layout primitives.

**Alternatives Considered**:
- Full `spatie/laravel-settings` typed DTOs — rejected. Would require additional migration table not in the locked schema.
- Custom settings blade page — rejected. The plugin is already installed and provides the Filament admin page scaffolding we need.

---

## Decision 3 — TipTap with Translatable Tabs

**Question**: Does `awcodes/filament-tiptap-editor` work inside `filament/spatie-laravel-translatable-plugin` locale tabs?

**Decision**: Yes — place `TiptapEditor::make('body')` inside each locale's `Tabs\Tab` schema separately. One `TiptapEditor` component per locale tab (EN and AR).

**Rationale**: The translatable plugin renders a locale switcher or per-locale tab form. When the form uses `Tabs::make` with per-locale schemas, each tab gets its own independent `TiptapEditor` instance bound to the correct locale key. TipTap operates on HTML strings; JSON storage via `spatie/laravel-translatable` handles the `{"en":"...", "ar":"..."}` wrapping transparently.

**Pattern**:
```php
Tabs::make('Translations')
    ->tabs([
        Tabs\Tab::make('English')->schema([
            TextInput::make('title')->translatable('en')->required(),
            TiptapEditor::make('body')->translatable('en')->required(),
            TextInput::make('meta_description')->translatable('en'),
        ]),
        Tabs\Tab::make('العربية')->schema([
            TextInput::make('title')->translatable('ar')->required(),
            TiptapEditor::make('body')->translatable('ar')->required(),
            TextInput::make('meta_description')->translatable('ar'),
        ]),
    ])
```

**Note**: Use `->profile('default')` on TipTap for CMS pages per `filament-components.md` §6 instruction. Do NOT use TipTap for service descriptions (per the same rule — those use Textarea).

---

## Decision 4 — Published-Only API Guard

**Question**: How to guard the public CMS API to only return published pages?

**Decision**: Eloquent scope `scopePublished()` on `CmsPage` + controller guard.

**Rationale**: The controller calls `CmsPage::published()->where('slug', $slug)->firstOrFail()`. Laravel throws `ModelNotFoundException` on `firstOrFail()`, which renders as 404 via the API exception handler. No additional middleware is needed.

**Scope definition**:
```php
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_published', true);
}
```

---

## Decision 5 — Phase 1 Slug Enum

**Question**: Should valid slugs be validated at the route level or model level?

**Decision**: `CmsSlug` PHP-backed enum in `app/Modules/Shared/Domain/Enums/CmsSlug.php` with cases `Terms`, `Privacy`, `About`, `Contact`. Route binding uses the enum for automatic validation.

**Rationale**: Enum at the route level means invalid slugs never reach the controller — Laravel 12 supports route model binding with enums via `Route::get('/{slug}', ...)` where `$slug` is typed as `CmsSlug`. The Eloquent model stores slug as a string column; the enum is the PHP-land contract for Phase 1 valid values.

**Alternative**: No enum, 404 from `firstOrFail()` handles unknown slugs — acceptable but less explicit. Enum is preferred per the "fail fast" principle.

---

## Decision 6 — Seeder Strategy

**Question**: Should the 4 CMS pages (terms, privacy, about, contact) be seeded as published or unpublished?

**Decision**: Seeded as **unpublished drafts** with placeholder EN+AR content.

**Rationale**: Avoids serving empty/placeholder legal text to customers. Admin must explicitly review and publish each page. The seeder creates the rows so the Filament resource shows them immediately, but `is_published = false` until intentional admin action.

---

## Resolved: No Unknowns Remain

All NEEDS CLARIFICATION items from `spec.md` are resolved above. Plan can proceed to Phase 1 design.
