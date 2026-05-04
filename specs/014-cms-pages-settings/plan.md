# Implementation Plan: CMS Pages + Settings

**Branch**: `014-cms-pages-settings` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Phase**: 6.2 — 1 day, Week 7
**Module**: Shared (Cross-cutting)

---

## Summary

Admin edits Terms/Privacy/About/Contact pages in Filament using TipTap rich-text editor with EN+AR locale tabs, publishes them, and the customer API (`GET /api/v1/cms/pages/{slug}`) returns only published pages in the requested locale. App settings (`app_settings`) and feature flags (`feature_flags`) are configurable via a Filament settings page. This is a 1-day, single-module phase with no financial data, no product-type awareness, and no new packages — all required packages are already in `10_Package_List.md`.

**PRD Alignment**: Admin Journey §6.3 ("manage platform settings"), Software Description §5 (CMS pages). No specific FR number covers CMS in `01_PRD.md`, but it is explicitly in the Phase 6.2 scope per `09_Phasing_Plan.md`.

---

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12
**Primary Dependencies**: Filament v3, `awcodes/filament-tiptap-editor:^3.4`, `filament/spatie-laravel-settings-plugin:^3.2`, `filament/spatie-laravel-translatable-plugin:^3.2`, `spatie/laravel-translatable`
**Storage**: MySQL 8 — 3 new tables: `cms_pages`, `app_settings`, `feature_flags`
**Testing**: Pest (`tests/Feature/Modules/Shared/CmsPageApiTest.php`)
**Target Platform**: Laravel API + Filament admin (backend only, no frontend)
**Project Type**: Modular monolith Laravel API
**Performance Goals**: Standard page response < 200ms p95 (no caching required in Phase 1)
**Constraints**: EN+AR mandatory on all CMS text fields; public API returns single-locale response; no money columns; no product-type awareness
**Scale/Scope**: 4 pages, ~10 settings rows, ~3 feature flags — small but load-bearing for Phase 7.0+ governance

---

## Constitution Check

*GATE: All principles evaluated. Must re-confirm after Phase 1 design.*

| Principle | Verdict | Enforcement in This Plan |
|---|---|---|
| **I. Modular Monolith** | ✅ PASS | All new files live under `app/Modules/Shared/`. Routes registered in `SharedServiceProvider`. No files in `app/` root. No cross-module model imports. |
| **II. Three Product Types** | ✅ N/A | CMS has no product-type dimension. No `match($enum)` needed; no per-type classes. |
| **III. Money Discipline** | ✅ N/A | No money columns in any of the 3 CMS/settings tables. No `Brick\Money` usage. |
| **IV. Bilingual EN+AR** | ✅ PASS | `cms_pages.title`, `body`, `meta_description` are JSON columns with `protected $translatable`. Publish action validates both EN+AR body non-empty. API Resource converts to `App::getLocale()`. Filament form uses TipTap in separate EN/AR tabs. |
| **V. Append-Only Tables** | ✅ N/A | None of the 3 tables (`cms_pages`, `app_settings`, `feature_flags`) are append-only. They are mutable configuration. No ledger tables touched. |
| **VI. ADR Before Code** | ⚠️ ADVISORY | No dedicated ADR for the Shared module. Decision (from `research.md` Decision 1): proceed without a new ADR since cross-cutting tables are explicitly scoped to Shared in the locked `11_DB_Schema.md` (constitution-level document). Create ADR-0002-shared-module.md in Phase 7.2 documentation cleanup. |
| **VII. Test-First Critical Paths** | ✅ PASS | Pest tests written same day: published-only filter, locale resolution (EN + AR), 404 on unpublished, 404 on invalid slug, publish validation. Not a critical financial path, so 60%+ coverage minimum applies. |
| **VIII. Idempotency** | ✅ N/A | No customer-facing mutating endpoints. The only mutation is admin Filament actions (publish/unpublish/settings), which are internal and don't require `Idempotency-Key` headers. |
| **IX. Domain Events `DB::afterCommit`** | ✅ PASS | `PublishCmsPageAction` and `UnpublishCmsPageAction` do not fire domain events in Phase 1. If a `CmsPagePublished` event is added for cache invalidation, it fires via `DB::afterCommit()`. No listeners calling external services inline. |
| **X. Vendor Approval** | ✅ N/A | CMS has no vendor-facing concept. |
| **XI. Document Storage** | ✅ N/A | CMS body is stored as JSON in MySQL. No S3/media uploads for CMS pages in Phase 1. |

**Gate result: PASS** (one advisory item on ADR, not a blocking violation).

---

## ADR Reference

| ADR | Status | Relevance |
|---|---|---|
| `docs/adr/0001-modular-monolith-pattern.md` | Accepted (2026-04-15) | Defines the module structure used here |
| Shared module ADR | MISSING — create ADR-0002-shared-module.md in Phase 7.2 | Would document cross-cutting table ownership |

---

## Project Structure

### Documentation (this feature)

```text
specs/014-cms-pages-settings/
├── spec.md              ✅ Complete
├── research.md          ✅ Complete (Phase 0)
├── data-model.md        ✅ Complete (Phase 1)
├── contracts/
│   └── api.md           ✅ Complete (Phase 1)
├── quickstart.md        ✅ Complete (Phase 1)
└── tasks.md             ⏳ Phase 2 output (/speckit.tasks — not yet created)
```

### Source Code Layout

```text
app/Modules/Shared/
├── Domain/
│   ├── Enums/
│   │   └── CmsSlug.php                    [NEW — backed enum: terms|privacy|about|contact]
│   └── Models/
│       ├── CmsPage.php                    [NEW — translatable, published scope]
│       ├── AppSetting.php                 [NEW — json cast on value]
│       └── FeatureFlag.php               [NEW — boolean + rollout_pct]
├── Application/
│   └── Actions/
│       ├── PublishCmsPageAction.php       [NEW — validates EN+AR body, DB::transaction]
│       └── UnpublishCmsPageAction.php     [NEW — DB::transaction]
├── Http/
│   ├── Controllers/
│   │   └── CmsPageController.php         [NEW — 3-line body: scope+find+resource]
│   └── Resources/
│       └── CmsPageResource.php           [NEW — API Resource, locale conversion here]
├── Filament/
│   ├── Resources/
│   │   └── CmsPageResource.php           [NEW — Filament Resource with TipTap]
│   └── Pages/
│       └── ManageSettings.php            [NEW — Filament settings page]
├── Routes/
│   └── customer.php                      [NEW or APPEND — GET /api/v1/cms/pages/{slug}]
└── Database/
    ├── Migrations/
    │   ├── xxxx_create_cms_pages_table.php
    │   ├── xxxx_create_app_settings_table.php
    │   └── xxxx_create_feature_flags_table.php
    └── Seeders/
        ├── CmsPagesSeeder.php             [4 draft pages: terms, privacy, about, contact]
        └── AppSettingsSeeder.php          [4 default settings rows]

tests/
└── Feature/
    └── Modules/
        └── Shared/
            └── CmsPageApiTest.php         [NEW — 8 test cases]
```

---

## Implementation Sequence (1 day)

All tasks are sequential within the single day. See `quickstart.md` for the file manifest.

### Block 1 — Schema (Morning)

1. Write migration `create_cms_pages_table`:
   - `utf8mb4` charset + collation
   - `bigIncrements('id')`, `char('public_id', 26)->unique()`
   - `string('slug', 120)->unique()` (not an ENUM — stored as string, enum is PHP-land)
   - `json('title')`, `json('body')`, `json('meta_description')->nullable()`
   - `boolean('is_published')->default(false)`
   - `timestamp('published_at')->nullable()`
   - `foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()`
   - `timestamps()`

2. Write migration `create_app_settings_table`:
   - `bigIncrements('id')`
   - `string('key', 120)->unique()`
   - `json('value')`
   - `string('description', 255)->nullable()`
   - `foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()`
   - `timestamps()`

3. Write migration `create_feature_flags_table`:
   - `bigIncrements('id')`
   - `string('key', 120)->unique()`
   - `boolean('is_enabled')->default(false)`
   - `unsignedTinyInteger('rollout_pct')->default(0)`
   - `string('description', 255)->nullable()`
   - `timestamps()`

**Note**: `cms_pages` has `public_id` (URL-exposed); `app_settings` and `feature_flags` do NOT (key-based access only, never in API URLs).

### Block 2 — Domain Models + Enum (Morning)

4. `CmsSlug` backed enum (cases: `Terms='terms'`, `Privacy='privacy'`, `About='about'`, `Contact='contact'`)
5. `CmsPage` model — `$translatable`, casts, `scopePublished`, `$fillable`
6. `AppSetting` model — `json` cast on `value`, `$fillable`
7. `FeatureFlag` model — boolean + integer casts, `$fillable`

### Block 3 — Actions (Mid-Morning)

8. `PublishCmsPageAction::execute(CmsPage $page): CmsPage`
   - Validate: EN body non-empty, AR body non-empty → throw `ValidationException` if not
   - `DB::transaction`: set `is_published=true`, `published_at=now()`, `updated_by=auth()->id()`, save
   - Return updated `$page`

9. `UnpublishCmsPageAction::execute(CmsPage $page): CmsPage`
   - `DB::transaction`: set `is_published=false`, `updated_by=auth()->id()`, save
   - Return updated `$page`

### Block 4 — API Endpoint (Mid-Morning)

10. `CmsPageResource` (API) — `toArray` returns locale-resolved fields (see `contracts/api.md`)
11. `CmsPageController::show(CmsSlug $slug)` — 3 lines: scope + find + resource
12. Route in `Routes/customer.php`:
    ```php
    Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
    ```
    Where `{slug}` is resolved as the `CmsSlug` enum via implicit route binding.
13. Register route in `SharedServiceProvider::boot()` under `api/v1` prefix + `api` + `locale` middleware

### Block 5 — Filament CmsPageResource (Afternoon)

14. `Filament/Resources/CmsPageResource.php`:
    - `navigationGroup = 'Content'`
    - Table columns: `slug` (badge), `title` (current locale), `is_published` (boolean icon), `published_at` (date)
    - Filters: `TernaryFilter` on `is_published`
    - Form: `Tabs::make` with EN tab and AR tab, each containing `TextInput('title')` + `TiptapEditor::make('body')->profile('default')` + `TextInput('meta_description')`
    - Row actions: custom "Publish" and "Unpublish" actions delegating to `PublishCmsPageAction` / `UnpublishCmsPageAction`, with `Notification::make()` feedback
    - `shield:generate --all` after creation

### Block 6 — Filament ManageSettings Page (Afternoon)

15. `Filament/Pages/ManageSettings.php`:
    - Navigation: group `'Content'`, label `'Settings'`
    - Two sections:
      - `AppSettings` — `KeyValue` component or `Repeater` over `app_settings` rows (key read-only, value editable)
      - `FeatureFlags` — table of flags with `ToggleColumn` for `is_enabled` and inline `TextInputColumn` for `rollout_pct`
    - On save: `updated_by = auth()->id()`

### Block 7 — Seeders (Afternoon)

16. `CmsPagesSeeder`: 4 rows, one per slug, all `is_published=false`, placeholder EN+AR content
17. `AppSettingsSeeder`: 4 rows per Phase 1 defaults (see `data-model.md`)
18. Add both seeders to `DatabaseSeeder`

### Block 8 — Pest Tests (End of Day)

19. `tests/Feature/Modules/Shared/CmsPageApiTest.php` — 8 test cases (see `quickstart.md` test checklist)

---

## Tables Touched (Phase 6.2 only)

| Table | Action | Schema Doc Reference |
|---|---|---|
| `cms_pages` | CREATE | `11_DB_Schema.md` §14 (Cross-cutting) |
| `app_settings` | CREATE | `11_DB_Schema.md` §14 (Cross-cutting) |
| `feature_flags` | CREATE | `11_DB_Schema.md` §14 (Cross-cutting) |

No existing tables modified.

---

## Bilingual Coverage

| Element | EN | AR |
|---|---|---|
| `cms_pages.title` | Required | Required |
| `cms_pages.body` | Required (TipTap HTML) | Required (TipTap HTML) |
| `cms_pages.meta_description` | Optional | Optional |
| Filament form tabs | ✅ English tab | ✅ العربية tab |
| API Resource output | `getTranslation('title', 'en')` | `getTranslation('title', 'ar')` |
| Seeder content | Placeholder EN text | Placeholder AR text |

---

## API Endpoints

| Method | Path | Auth | Phase |
|---|---|---|---|
| GET | `/api/v1/cms/pages/{slug}` | None (public) | 6.2 |

Full contract in `contracts/api.md`. Add to `.specify/memory/api-registry.md` after implementation.

---

## Idempotency Keys

None required. The only API endpoint is read-only (`GET`). Admin Filament mutations are idempotent by nature (set a field to a value repeatedly is safe).

---

## Domain Events

None fired in Phase 1. If cache invalidation is needed in Phase 7.0, `CmsPagePublished` can be added at that time, firing via `DB::afterCommit()` per Principle IX.

---

## Architecture Tests

No new architecture tests required for this phase. The existing cross-module import test (`NoCrossModuleModelImportsTest.php`) will automatically validate that `CmsPage`, `AppSetting`, and `FeatureFlag` are not imported across module boundaries.

---

## Cut-List

**None** — per `09_Phasing_Plan.md` Phase 6.2, no cut-list is defined. The 1-day scope is tight; the work was pre-scoped to be achievable in a single day.

---

## Exit Criteria (from spec.md)

- ✅ Admin edits Terms in EN+AR via TipTap in Filament
- ✅ Customer API `GET /api/v1/cms/pages/{slug}` serves correct locale
- ✅ Unpublished pages return 404
- ✅ Settings panel (app_settings + feature_flags) works in Filament
- ✅ All Pest tests pass

---

## Complexity Tracking

No constitution violations. No complexity justification needed.
