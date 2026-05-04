# Tasks: CMS Pages + Settings

**Phase**: 6.2 — 1 day, Week 7
**Input**: `specs/014-cms-pages-settings/` (spec.md, plan.md, data-model.md, contracts/api.md, quickstart.md)
**Module**: `app/Modules/Shared/`
**Tables**: `cms_pages`, `app_settings`, `feature_flags`

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies at that moment)
- **[US#]**: Maps to user story from spec.md
- No test tasks are generated separately — tests are integrated into the US phases per constitution Principle VII

---

## Phase 1: Setup (Shared Module Wiring)

**Purpose**: Verify the Shared module exists and has the correct structure. Add any missing boilerplate before writing feature code.

- [X] T001 Verify `app/Modules/Shared/Providers/SharedServiceProvider.php` exists and is registered in `bootstrap/providers.php` (or `config/app.php`)
- [X] T002 Verify `app/Modules/Shared/Routes/customer.php` exists; create it if missing with the standard API route group header
- [X] T003 Verify `SharedServiceProvider::boot()` loads migrations from `__DIR__ . '/../Database/Migrations'` and registers the customer route file under `api/v1` prefix with `api` + `locale` middleware

**Checkpoint**: Shared module boots cleanly — `php artisan route:list` shows no errors.

---

## Phase 2: Foundational (Migrations + Models + Enum)

**Purpose**: All three tables must exist and all three models must be usable before any user story implementation.

**⚠️ CRITICAL**: No user story work can begin until these migrations are applied and models pass a `php artisan migrate:fresh` with no errors.

- [X] T004 Write migration `app/Modules/Shared/Database/Migrations/xxxx_create_cms_pages_table.php` — columns: `bigIncrements('id')`, `char('public_id',26)->unique()`, `string('slug',120)->unique()`, `json('title')`, `json('body')`, `json('meta_description')->nullable()`, `boolean('is_published')->default(false)`, `timestamp('published_at')->nullable()`, `foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()`, `timestamps()`. Set `$table->charset='utf8mb4'` and `$table->collation='utf8mb4_unicode_ci'`.

- [X] T005 Write migration `app/Modules/Shared/Database/Migrations/xxxx_create_app_settings_table.php` — columns: `bigIncrements('id')`, `string('key',120)->unique()`, `json('value')`, `string('description',255)->nullable()`, `foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()`, `timestamps()`. No `public_id` (key-based access only).

- [X] T006 Write migration `app/Modules/Shared/Database/Migrations/xxxx_create_feature_flags_table.php` — columns: `bigIncrements('id')`, `string('key',120)->unique()`, `boolean('is_enabled')->default(false)`, `unsignedTinyInteger('rollout_pct')->default(0)`, `string('description',255)->nullable()`, `timestamps()`. No `public_id`.

- [X] T007 Run `php artisan migrate` and confirm all three tables are created with no errors.

- [X] T008 Create enum `app/Modules/Shared/Domain/Enums/CmsSlug.php` — backed enum `CmsSlug: string` with cases: `Terms='terms'`, `Privacy='privacy'`, `About='about'`, `Contact='contact'`.

- [X] T009 [P] Create model `app/Modules/Shared/Domain/Models/CmsPage.php` — `$translatable = ['title', 'body', 'meta_description']`, casts `slug => CmsSlug::class`, `is_published => 'boolean'`, `published_at => 'datetime'`, `$fillable` for writable columns, scope `scopePublished(Builder $query)` returning `$query->where('is_published', true)`.

- [X] T010 [P] Create model `app/Modules/Shared/Domain/Models/AppSetting.php` — cast `value => 'json'`, `$fillable = ['key','value','description','updated_by']`.

- [X] T011 [P] Create model `app/Modules/Shared/Domain/Models/FeatureFlag.php` — cast `is_enabled => 'boolean'`, `rollout_pct => 'integer'`, `$fillable = ['key','is_enabled','rollout_pct','description']`.

**Checkpoint**: All three models load without error (`php artisan tinker` → `CmsPage::count()` returns 0). T009/T010/T011 can run in parallel since they are separate files.

---

## Phase 3: User Story 1 — Admin Edits and Publishes a CMS Page (Priority: P1) 🎯 MVP

**Goal**: Admin can open any of the 4 CMS pages in Filament, edit EN+AR body via TipTap, save, and click Publish. The page becomes customer-visible via the API.

**Independent Test**: Create a `CmsPage` factory record with `is_published=false`, call `PublishCmsPageAction->execute($page)`, assert `$page->is_published === true` and `$page->published_at` is not null.

- [X] T012 [US1] Create `app/Modules/Shared/Application/Actions/PublishCmsPageAction.php` — single `execute(CmsPage $page): CmsPage` method. Inside `DB::transaction`: validate EN body non-empty and AR body non-empty (throw `\Illuminate\Validation\ValidationException` if either is blank), then set `is_published=true`, `published_at=now()`, `updated_by=auth()->id()`, save, return `$page`.

- [X] T013 [US1] Create `app/Modules/Shared/Filament/Resources/CmsPageResource.php` with:
  - `navigationGroup = 'Content'`, `navigationLabel = 'CMS Pages'`
  - Table: `TextColumn::make('slug')->badge()`, `TextColumn::make('title')` (current locale), `IconColumn::make('is_published')->boolean()`, `TextColumn::make('published_at')->dateTime()->sortable()`
  - Filter: `TernaryFilter::make('is_published')`
  - Form: `Tabs::make('Translations')` with English tab (`TextInput('title')`, `TiptapEditor::make('body')->profile('default')`, `TextInput('meta_description')`) and Arabic tab (same three fields, all `->translatable()` pointing to respective locale)
  - Row action "Publish" delegating to `PublishCmsPageAction::execute()` with `Notification::make()->title(__('cms.published'))->success()->send()`
  - Default sort: `created_at` desc

- [X] T014 [US1] Write Pest test cases in `tests/Feature/Modules/Shared/CmsPageApiTest.php` (create the file):
  - Test: `it('publishes a cms page and sets published_at')` — creates unpublished page, calls action, asserts DB state
  - Test: `it('rejects publish when english body is empty')` — asserts `ValidationException` thrown
  - Test: `it('rejects publish when arabic body is empty')` — asserts `ValidationException` thrown

**Checkpoint**: `php artisan shield:generate --all` succeeds. Admin can navigate to `/admin` → "Content" → "CMS Pages" and see the resource. Run T014 tests — all green.

---

## Phase 4: User Story 2 — Customer API (Published-Only Guard) + User Story 4 — Unpublish (Priority: P2)

**Goal (US2)**: `GET /api/v1/cms/pages/{slug}` returns 200+content for published pages and 404 for unpublished/missing. Locale is derived from `Accept-Language` header.

**Goal (US4)**: Admin can un-publish a live page and the API immediately returns 404.

**Independent Test (US2)**: `GET /api/v1/cms/pages/terms` with no matching published page → 404. Publish the page → 200 with correct locale field.

**Independent Test (US4)**: Publish a page, call API → 200, unpublish → call API → 404.

- [X] T015 [US2] Create `app/Modules/Shared/Http/Resources/CmsPageResource.php` (API Resource, distinct from Filament resource). In `toArray`: resolve `$locale = app()->getLocale()`, return `['slug' => $this->slug->value, 'title' => $this->getTranslation('title', $locale), 'body' => $this->getTranslation('body', $locale), 'meta_description' => $this->getTranslation('meta_description', $locale) ?: null, 'published_at' => $this->published_at?->toISOString()]`.

- [X] T016 [US2] Create `app/Modules/Shared/Http/Controllers/CmsPageController.php` with method `show(CmsSlug $slug): JsonResponse` — body: `$page = CmsPage::published()->where('slug', $slug->value)->firstOrFail(); return ApiResponse::success(new CmsPageResource($page));`. Add Scribe PHPDoc block per `contracts/api.md`.

- [X] T017 [US2] Add route to `app/Modules/Shared/Routes/customer.php`:
  ```php
  Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
  ```
  Verify `{slug}` resolves via implicit enum binding (Laravel 12 supports `CmsSlug` backed enum route parameters).

- [X] T018 [US4] Create `app/Modules/Shared/Application/Actions/UnpublishCmsPageAction.php` — `execute(CmsPage $page): CmsPage`. Inside `DB::transaction`: set `is_published=false`, `updated_by=auth()->id()`, save, return `$page`.

- [X] T019 [US4] Add "Unpublish" row action to `app/Modules/Shared/Filament/Resources/CmsPageResource.php` — delegates to `UnpublishCmsPageAction::execute()`, visible only when `$record->is_published === true`. Publish action: visible only when `$record->is_published === false`.

- [X] T020 [US2] Add Pest test cases to `tests/Feature/Modules/Shared/CmsPageApiTest.php`:
  - Test: `it('returns 200 with english content for a published page')` — creates published page, GET with `Accept-Language: en`, assert 200 + `data.title` = EN title
  - Test: `it('returns 200 with arabic content for a published page')` — same but `Accept-Language: ar`, assert `data.title` = AR title
  - Test: `it('returns 404 for an unpublished page')` — creates draft page, GET → assert 404
  - Test: `it('returns 404 when slug does not exist')` — GET `/api/v1/cms/pages/unknown` → assert 404

- [X] T021 [US4] Add Pest test case to `tests/Feature/Modules/Shared/CmsPageApiTest.php`:
  - Test: `it('returns 404 after a page is unpublished')` — publishes, asserts 200, unpublishes, asserts 404

**Checkpoint**: Run `./vendor/bin/pest tests/Feature/Modules/Shared/CmsPageApiTest.php` — all 8 test cases pass. API endpoint accessible via `php artisan route:list | grep cms`.

---

## Phase 5: User Story 3 — Admin Configures App Settings + Feature Flags (Priority: P3)

**Goal**: Admin opens Filament Settings page and can edit `app_settings` key-value pairs and toggle `feature_flags` on/off with rollout percentage.

**Independent Test**: Update `app_settings` row `platform.vendor_sla_hours` value via Filament action; assert the DB row reflects the new value and `updated_by` is captured.

- [X] T022 [US3] Create `app/Modules/Shared/Filament/Pages/ManageSettings.php` — custom Filament page:
  - Navigation: `navigationGroup = 'Content'`, `navigationLabel = 'Settings'`, icon `heroicon-o-cog-6-tooth`
  - Section 1 "App Settings": `KeyValue::make` or a `Repeater` over `AppSetting::all()` — keys are read-only, values editable. On save: `updated_by = auth()->id()` on each updated row.
  - Section 2 "Feature Flags": Table-style form over `FeatureFlag::all()` with `Toggle` for `is_enabled` and `TextInput::numeric()` for `rollout_pct` (0–100).
  - Authorization: only `super_admin` or `admin` role (via `filament-shield` permission gate).

**Checkpoint**: Navigate to `/admin` → "Content" → "Settings". Update a setting value, confirm DB row updated. Toggle a feature flag, confirm `is_enabled` flipped in DB.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Seeders, permission generation, final validation.

- [X] T023 [P] Create `database/seeders/CmsPagesSeeder.php` — seeds 4 rows (terms, privacy, about, contact), all `is_published=false`, with placeholder EN+AR title and body content.

- [X] T024 [P] Create `database/seeders/AppSettingsSeeder.php` — seeds 4 default settings rows.

- [X] T025 [P] Create `database/seeders/FeatureFlagsSeeder.php` — seeds 3 feature flag rows.

- [X] T026 Register all three seeders in `database/seeders/DatabaseSeeder.php`.

- [X] T027 Run seeders — confirmed 4+4+3 rows created.

- [X] T028 Run `php artisan shield:generate --all` to generate permissions for `CmsPageResource` and `ManageSettings`.

- [X] T029 Run full test suite `./vendor/bin/pest --bail` — confirm all existing tests still pass (regression check).

- [X] T030 Run `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` — zero errors or warnings on new files.

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational: Migrations + Models + Enum)
    ↓
Phase 3 (US1: Actions + Filament CmsPageResource) ─┐
    ↓                                               │ parallel
Phase 4 (US2+US4: API Controller + Route + Tests)  │ if solo: sequential
    ↓                                               │
Phase 5 (US3: ManageSettings Filament page) ────────┘
    ↓
Phase 6 (Polish: Seeders + Shield + Linting)
```

### User Story Dependencies

- **US1 (P1)**: Requires Foundational (T004–T011). Unblocks US2, US3, US4.
- **US2 (P2)**: Requires US1's `CmsPage` model (T009). Unblocks final test run.
- **US4 (P2)**: Requires US1's `CmsPage` model (T009). Co-located with US2 in Phase 4.
- **US3 (P3)**: Requires `AppSetting` and `FeatureFlag` models (T010, T011) — parallel with US1 but ordered after Phase 2.

### Parallel Opportunities

- T009, T010, T011 (three model files) can be written in parallel.
- T023, T024, T025 (three seeders) can be written in parallel.
- T015 (API Resource) and T018 (UnpublishAction) can be written in parallel before being wired in T016 and T019.

---

## Notes

- `[P]` = different files with no cross-task dependencies at that point in execution
- `[US#]` maps directly to user stories in `specs/014-cms-pages-settings/spec.md`
- Tests are written inside their story phase (not deferred) per constitution Principle VII
- Constitution check: all 11 principles PASS per `plan.md`. One advisory ADR note — non-blocking.
- After completing Phase 6, run `/speckit.checklist` to validate against the InstaParty feature checklist template
