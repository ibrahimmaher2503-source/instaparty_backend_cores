# Tasks: Phase 0 — Foundation

**Input**: Design documents from `specs/001-phase-0-foundation/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Included — CLAUDE.md requires tests written with each module, not deferred.

**Organization**: Tasks grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[US#]**: User story this task belongs to (maps to spec.md)
- Exact file paths included in every description

## Path Conventions

Modular monolith — all domain code under `app/Modules/{Name}/`. Framework code in standard Laravel paths (`database/migrations/`, `database/seeders/`). Tests under `tests/Feature/Modules/` and `tests/Unit/Modules/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization — Laravel scaffold, locked packages, config files. Nothing in a user story can start until this phase is complete.

- [X] T001 Create Laravel 12 project via `composer create-project laravel/laravel .`, then drop `CLAUDE.md`, `docs/specs/`, and `.claude/` into the repo root; make `.claude/hooks/*.sh` executable
- [X] T002 Install Day-1 foundation packages: `laravel/sanctum:^4.0 laravel/scout:^10.10 laravel/reverb:^1.0 predis/predis:^2.2 spatie/laravel-permission:^6.10 spatie/laravel-translatable:^6.8 spatie/laravel-medialibrary:^11.9 spatie/laravel-tags:^4.6 brick/money:^0.10 spatie/laravel-data:^4.13 spatie/laravel-model-states:^2.7 spatie/laravel-activitylog:^4.9 spatie/laravel-backup:^9.2 pragmarx/google2fa:^8.0 bacon/bacon-qr-code:^3.0 guzzlehttp/guzzle:^7.9` — then install dev tools: `pestphp/pest:^3.5 pestphp/pest-plugin-laravel:^3.0 pestphp/pest-plugin-arch:^3.0 laravel/pint:^1.18 larastan/larastan:^3.0 nunomaduro/collision:^8.5 fakerphp/faker:^1.23 mockery/mockery:^1.6 spatie/laravel-ignition:^2.8 laravel/telescope:^5.2 driftingly/rector-laravel:^2.0`
- [X] T003 [P] Create `phpstan.neon` at repo root: include Larastan extension, set `level: 8`, paths `[app, database, tests]`, and exclude generated Filament files
- [X] T004 [P] Create `pint.json` at repo root with Laravel preset: `{"preset": "laravel"}`
- [X] T005 [P] Create `docker-compose.yml` with six services (app, mysql8, redis, meilisearch, mailpit, minio), named volumes for mysql and minio persistence, and all connection ports documented; create `.env.example` with every required variable including: `DB_*`, `REDIS_*`, `MAIL_*`, `APP_KEY`, `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=en`, `APP_TIMEZONE=UTC`, `SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1`, `SESSION_DOMAIN=`, `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE=false`, `SCOUT_DRIVER=meilisearch`, `SCOUT_QUEUE=true`, `MEILISEARCH_HOST=http://meilisearch:7700`, `MEILISEARCH_KEY=`, `FILESYSTEM_DISK=s3`, `AWS_ENDPOINT=http://minio:9000`, `AWS_USE_PATH_STYLE_ENDPOINT=true`, `AWS_BUCKET=instaparty-dev` — never commit real credentials ⚠️ `.env.example` write blocked by settings.json deny rule; developer must manually add InstaParty-specific vars from tasks.md T005 description to the default Laravel .env.example

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before any user story work begins: users table extended, MoneyCast, Filament installed and configured, roles seeder, dev-env command.

**CRITICAL**: No user story work can begin until this phase is complete.

- [X] T006 Extend the framework's users migration at `database/migrations/0001_01_01_000000_create_users_table.php` — add `public_id CHAR(26) UNIQUE`, `phone_e164 VARCHAR(20) UNIQUE NOT NULL`, `phone_verified_at TIMESTAMP NULL`, `preferred_locale ENUM('ar','en') DEFAULT 'ar'`, `timezone VARCHAR(64) DEFAULT 'Africa/Cairo'`, `numeral_system ENUM('western','eastern') DEFAULT 'western'`, `status ENUM('active','suspended','banned') DEFAULT 'active'`, `last_login_at TIMESTAMP NULL`, `last_login_ip VARCHAR(45) NULL`, `deleted_at TIMESTAMP NULL`; add indexes `(status, deleted_at)` and UNIQUE `phone_e164`
- [X] T007 [P] Create `app/Modules/Shared/Domain/Casts/MoneyCast.php` implementing `CastsAttributes<\Brick\Money\Money, \Brick\Money\Money>`: `get()` returns `Money::ofMinor($attributes["{$key}_minor"], $attributes["{$key}_currency"])`, `set()` accepts `Money|int`, returns `["{$key}_minor" => int, "{$key}_currency" => string]`, throws `InvalidArgumentException` on float input — see `specs/001-phase-0-foundation/contracts/money-cast.md` for full contract
- [X] T008 Create `app/Modules/Shared/Providers/SharedServiceProvider.php` extending `ServiceProvider` with empty `boot()` and `register()` (bindings will grow in future phases); register it first in `bootstrap/providers.php`
- [X] T009 Install Filament v3 + all 10 curated plugins: `filament/filament:^3.2 bezhansalleh/filament-shield:^3.3 filament/spatie-laravel-translatable-plugin:^3.2 filament/spatie-laravel-media-library-plugin:^3.2 filament/spatie-laravel-tags-plugin:^3.2 filament/spatie-laravel-settings-plugin:^3.2 filament/spatie-laravel-activitylog-plugin:^3.2 awcodes/filament-tiptap-editor:^3.4 bezhansalleh/filament-language-switch:^3.1 pxlrbt/filament-excel:^2.4 saade/filament-fullcalendar:^3.2`; then run `php artisan filament:install --panels` ⚠️ `filament/spatie-laravel-activitylog-plugin` does NOT exist as a Filament package — skipped; LanguageSwitch uses `LanguageSwitch::configureUsing()` in AppServiceProvider (not a panel plugin)
- [X] T010 Configure `app/Providers/Filament/AdminPanelProvider.php`: set panel path to `admin`, add `->discoverResources(in: app_path('Modules'), for: 'App\\Modules')` for module discovery, register `FilamentShieldPlugin::make()`, set default locale to `'ar'` in `config/app.php`; configure navigation groups; LanguageSwitch configured in `AppServiceProvider::boot()`
- [X] T010a [P] Configure Sanctum for SPA + token authentication: published config, guard `['web']` confirmed, `config/cors.php` `supports_credentials: true`, session config uses env vars for domain/same_site/secure
- [X] T011 [P] Create `database/seeders/RolesAndPermissionsSeeder.php`: creates roles `admin`, `vendor`, `customer` via `spatie/laravel-permission`; seeds all per-product-type permissions (`service.create.{rental|sale|digital}.own`, `service.update.{rental|sale|digital}.own`, `service.delete.{rental|sale|digital}.own`, `service.publish.{rental|sale|digital}.own`, `booking.respond.own`, `wallet.withdraw.own`) and admin permissions (`vendor.approve`, `vendor.approve.{rental|sale|digital}`, `service.moderate`, `commission.manage`, `withdrawal.approve`, `audit.view`, `report.view`)
- [X] T012 [P] Create `database/seeders/AdminUserSeeder.php`: creates one user with email `admin@instaparty.local`, assigns `admin` role; password from `config('app.admin_password', 'password')` — document in `.env.example`
- [X] T013 [P] Create `app/Console/Commands/SetupDevEnvironmentCommand.php` (`php artisan app:setup-dev-env`): creates MinIO bucket `instaparty-dev` if absent (using AWS SDK via the S3 disk config), pings Meilisearch health endpoint, reports success or failure for each service — idempotent (safe to run multiple times)

**Checkpoint**: Foundation ready — Filament boots at `/admin`, MoneyCast is importable, seeders exist.

---

## Phase 3: User Story 1 + User Story 3 — Developer Boots App & Bilingual Geography Filament (Priority: P1)

**Goal (US1)**: Developer runs `docker compose up` + `migrate` + `seed` and the app boots with Egypt geography data accessible in Filament.
**Goal (US3)**: Admin can CRUD governorates, regions, and cities in Filament with full EN/AR bilingual support.

**Independent Test**: `php artisan migrate --seed` completes without errors; visit `/admin`, log in, browse Geography in EN, switch to AR — all names render correctly.

### Geography Migrations (must be in dependency order)

- [X] T014a Create `app/Modules/Geography/Database/Migrations/2026_01_01_000000_create_countries_table.php`: `declare(strict_types=1)`, anonymous class, utf8mb4 charset+collation, `bigIncrements('id')`, `char('public_id', 26)->unique()`, `char('iso2', 2)->unique()` (e.g. `'EG'`, `'SA'`, `'AE'`), `char('iso3', 3)->unique()` (e.g. `'EGY'`, `'SAU'`), `json('name')` (translatable EN+AR), `char('default_currency', 3)` (e.g. `'EGP'`, `'SAR'`), `char('default_locale', 5)->default('ar')`, `string('default_timezone', 64)->default('Africa/Cairo')`, `string('phone_code', 8)` (e.g. `'+20'`, `'+966'`), `boolean('is_active')->default(true)`, `unsignedInteger('sort_order')->default(0)`, `timestamps()`; seed Egypt as the first row in `EgyptGeographySeeder` (T023)
- [X] T014 Create `app/Modules/Geography/Database/Migrations/2026_01_01_000001_create_governorates_table.php`: `declare(strict_types=1)`, anonymous class, utf8mb4 charset+collation, `bigIncrements('id')`, `char('public_id', 26)->unique()`, `json('name')`, `varchar('code', 20)->unique()`, `foreignId('country_id')->constrained('countries')->restrictOnDelete()` (**not** `country_code CHAR(2)` — locked four-level hierarchy per Tech Decisions §1.1), `boolean('is_active')->default(true)`, `unsignedInteger('sort_order')->default(0)`, `timestamps()`
- [X] T015 Create `app/Modules/Geography/Database/Migrations/2026_01_01_000002_create_regions_table.php`: same conventions; `foreignId('governorate_id')->constrained()->restrictOnDelete()`, `json('name')`, `boolean('is_active')`, `unsignedInteger('sort_order')`, `timestamps()`; index `(governorate_id, is_active)`
- [X] T016 Create `app/Modules/Geography/Database/Migrations/2026_01_01_000003_create_cities_table.php`: `foreignId('region_id')->constrained()->restrictOnDelete()`, `foreignId('governorate_id')->constrained()->restrictOnDelete()` (denorm), `json('name')`, `decimal('latitude', 10, 7)->nullable()`, `decimal('longitude', 10, 7)->nullable()`, `boolean('is_active')`, `unsignedInteger('sort_order')`, `timestamps()`; indexes `(governorate_id, is_active)` and `(region_id, is_active)`

### Geography Models

- [X] T017 [P] [US1] [US3] Create `app/Modules/Geography/Domain/Models/Governorate.php`: use `HasUlids` trait; override `uniqueIds(): array { return ['public_id']; }` and `getRouteKeyName(): string { return 'public_id'; }` so Laravel maps to `public_id` not `id`; `protected $translatable = ['name']`; `use HasTranslations`; `scopeActive()`; `hasMany(Region::class)`; `hasMany(City::class)`
- [X] T018 [P] [US1] [US3] Create `app/Modules/Geography/Domain/Models/Region.php`: `HasUlids`; override `uniqueIds()` returning `['public_id']` and `getRouteKeyName()` returning `'public_id'`; `HasTranslations`; `$translatable = ['name']`; `scopeActive()`; `scopeForGovernorate(int $id)`; `belongsTo(Governorate::class)`; `hasMany(City::class)`
- [X] T019 [P] [US1] [US3] Create `app/Modules/Geography/Domain/Models/City.php`: `HasUlids`; override `uniqueIds()` returning `['public_id']` and `getRouteKeyName()` returning `'public_id'`; `HasTranslations`; `$translatable = ['name']`; `scopeActive()`; `scopeForGovernorate(int $id)`; `scopeForRegion(int $id)`; `belongsTo(Region::class)`; `belongsTo(Governorate::class)`

### Geography Factories

- [X] T020 [P] [US1] Create `app/Modules/Geography/Database/Factories/GovernorateFactory.php`: generates fake EN + AR names using `fake()->word()` for EN and a transliterated AR placeholder; code as `EG-` + fake suffix; `country_id` defaults to the Egypt country record (created in `EgyptGeographySeeder` T023) or creates a stub Country if none exists; `is_active = true`
- [X] T021 [P] [US1] Create `app/Modules/Geography/Database/Factories/RegionFactory.php`: creates a `Governorate` if none provided; generates fake EN + AR names
- [X] T022 [P] [US1] Create `app/Modules/Geography/Database/Factories/CityFactory.php`: creates a `Region` (and its `Governorate`) if none provided; sets `governorate_id` from the region's governorate; optional lat/lon within Egypt bounding box

### Geography Seeder

- [X] T023 [US1] Create `app/Modules/Geography/Database/Seeders/EgyptGeographySeeder.php`: seeds at minimum 5 real Egypt governorates (Cairo القاهرة, Giza الجيزة, Alexandria الإسكندرية, Sharqia الشرقية, Qalyubia القليوبية) each with 2+ regions and 3+ cities, with accurate EN and AR names; wraps all inserts in `DB::transaction()`

### Geography Filament Resources

- [X] T024 [US3] Create `app/Modules/Geography/Filament/Resources/GovernorateResource.php`: `use Translatable`, `getTranslatableLocales() = ['en', 'ar']`, `navigationGroup = 'Geography'`, table columns (code badge, name TextColumn, is_active IconColumn, sort_order), form with EN/AR translation tabs via plugin (name TextInput required in both locales), code TextInput, country_code, is_active Toggle, sort_order numeric; filters: TernaryFilter for is_active; actions: EditAction, DeleteAction with confirmation; `shield` permissions
- [X] T025 [US3] Create `app/Modules/Geography/Filament/Resources/RegionResource.php`: same pattern; table includes governorate name column (relationship); form has `governorate_id` Select (searchable, preloaded) + translatable name tabs; filter by governorate
- [X] T026 [US3] Create `app/Modules/Geography/Filament/Resources/CityResource.php`: form has `region_id` Select with `->afterStateUpdated()` callback that sets `governorate_id` from the selected region's FK (auto-populate denorm); latitude/longitude TextInputs (nullable, numeric); is_active Toggle; table columns: name, region (relationship), governorate (relationship), is_active icon, lat/lon; filter by governorate + region
- [X] T026a [P] [US3] Create `app/Modules/Geography/Domain/Contracts/GeographyRepository.php` (interface): declare `findCityById(int $id): ?City`, `findCityByPublicId(string $publicId): ?City`, `listCitiesByGovernorate(int $governorateId): Collection`, `searchCities(string $query): Collection`; create `app/Modules/Geography/Infrastructure/Repositories/EloquentGeographyRepository.php` implementing the interface using Eloquent queries; bind in `GeographyServiceProvider::register()` via `$this->app->bind(GeographyRepository::class, EloquentGeographyRepository::class)`; add corresponding tests to the T039–T041 group (see T041a below)
- [X] T041a [P] [US3] Create `tests/Feature/Modules/Geography/GeographyRepositoryTest.php`: test `findCityByPublicId()` returns correct City; test `listCitiesByGovernorate()` returns only cities for that governorate; test `searchCities()` returns matching results; test each returns `null`/empty on no match — group `geography`

### Geography ServiceProvider

- [X] T027 [US1] [US3] Create `app/Modules/Geography/Providers/GeographyServiceProvider.php`: `boot()` calls `$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations')` and `$this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'geography')`; register in `bootstrap/providers.php` after `SharedServiceProvider`
- [X] T028 [US3] Run `php artisan shield:generate --all` to generate Filament Shield permissions for all Geography Resources; commit the generated policy files

### Identity Migrations (schema only — models/resources in Phase 1)

- [X] T029 [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000010_create_vendor_profiles_table.php`: all columns from `11_DB_Schema.md §3` — `public_id CHAR(26) UNIQUE` (model must override `uniqueIds()` returning `['public_id']` and `getRouteKeyName()` returning `'public_id'`), `user_id FK→users restrictOnDelete UNIQUE`, `business_name JSON`, `slug VARCHAR(160) UNIQUE`, `bio JSON NULL`, `logo_path`, `cover_path`, `business_type ENUM`, `commercial_register_no NULL`, `tax_id NULL`, `national_id NULL`, `primary_governorate_id FK→governorates restrictOnDelete`, `primary_city_id FK→cities restrictOnDelete`, `address_line JSON NULL`, `latitude/longitude DECIMAL NULL`, `approval_status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending'`, `approved_at NULL`, `approved_by FK→users NULL`, `rejection_reason JSON NULL`, explicit bank fields: `bank_name VARCHAR(120) NULL`, `bank_account_holder VARCHAR(160) NULL`, `bank_iban VARCHAR(34) NULL` (ISO 13616), `bank_swift_bic VARCHAR(11) NULL` (ISO 9362), `bank_branch VARCHAR(120) NULL`, `rating_avg DECIMAL(3,2) DEFAULT 0`, `rating_count UINT DEFAULT 0`, `response_time_avg_minutes UINT NULL`, soft deletes; indexes `(approval_status, deleted_at)` and `(primary_city_id)`
- [X] T030 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000011_create_vendor_documents_table.php`: `vendor_profile_id FK restrictOnDelete`, `doc_type ENUM('cr','tax_card','national_id','iban_proof','other')`, `file_path`, `file_name`, `status ENUM('pending','approved','rejected') DEFAULT 'pending'`, `reviewed_at NULL`, `reviewed_by FK→users NULL`, `review_notes JSON NULL`, `timestamps()`
- [X] T031 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000012_create_vendor_approved_product_types_table.php`: `vendor_profile_id FK restrictOnDelete`, `product_type ENUM('rental','sale','digital')`, `approved_at`, `approved_by FK→users`, `revoked_at NULL`, `revoked_by FK→users NULL`, `revoke_reason JSON NULL`, `timestamps()`; UNIQUE `(vendor_profile_id, product_type, revoked_at)`
- [X] T032 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000013_create_vendor_business_hours_table.php`: `vendor_profile_id FK restrictOnDelete`, `day_of_week TINYINT UNSIGNED`, `opens_at TIME NULL`, `closes_at TIME NULL`, `timestamps()`; UNIQUE `(vendor_profile_id, day_of_week)`
- [X] T033 [P] [US1] Create `app/Modules/Geography/Database/Migrations/2026_01_01_000005_create_vendor_coverage_areas_table.php` (**Geography module owns this schema per Tech Decisions §1.1 — not Identity**): `vendor_profile_id FK→vendor_profiles restrictOnDelete`, `city_id FK→cities restrictOnDelete`, `delivery_fee_minor BIGINT UNSIGNED DEFAULT 0`, `delivery_fee_currency CHAR(3)`, `min_order_minor BIGINT UNSIGNED DEFAULT 0`, `min_order_currency CHAR(3)`, `timestamps()`; UNIQUE `(vendor_profile_id, city_id)`; Identity's `VendorProfile` model declares `belongsToMany(City::class, 'vendor_coverage_areas')` — schema lives in Geography, relationship pointer lives in Identity
- [X] T034 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000015_create_customer_profiles_table.php`: `user_id FK→users restrictOnDelete UNIQUE`, `date_of_birth DATE NULL`, `gender ENUM NULL`, `how_heard_about_us VARCHAR(120) NULL`, `children JSON NULL`, `accepts_marketing BOOLEAN DEFAULT true`, `timestamps()`
- [X] T035 [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000016_create_customer_addresses_table.php`: `public_id CHAR(26) UNIQUE` (model must override `uniqueIds()` returning `['public_id']` and `getRouteKeyName()` returning `'public_id'`), `user_id FK→users restrictOnDelete`, `city_id FK→cities restrictOnDelete`, `label VARCHAR(60)`, `address_line VARCHAR(255)`, `building/floor/apartment/landmark NULL`, `latitude/longitude DECIMAL NULL`, `recipient_name VARCHAR(120)`, `recipient_phone_e164 VARCHAR(20)`, `is_default BOOLEAN DEFAULT false`, `timestamps()`, `softDeletes()`; index `(user_id, deleted_at)`
- [X] T036 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000017_create_user_devices_table.php`: `user_id FK→users restrictOnDelete`, `platform ENUM('ios','android','web')`, `fcm_token VARCHAR(255)`, `device_id VARCHAR(190) NULL`, `last_seen_at TIMESTAMP NULL`, `timestamps()`; UNIQUE `(user_id, fcm_token)`
- [X] T037 [P] [US1] Create `app/Modules/Identity/Database/Migrations/2026_01_01_000018_create_two_factor_secrets_table.php`: `user_id FK→users restrictOnDelete UNIQUE`, `secret_encrypted TEXT`, `recovery_codes_encrypted TEXT`, `confirmed_at TIMESTAMP NULL`, `timestamps()`
- [X] T038 [US1] Create `app/Modules/Identity/Providers/IdentityServiceProvider.php`: `boot()` calls only `$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations')`; register in `bootstrap/providers.php` after `GeographyServiceProvider`

### Pest Tests (Geography + Shared)

- [X] T039 [P] [US1] [US3] Create `tests/Feature/Modules/Geography/GovernorateTest.php`: test factory creation; test translatable `name` write + read in EN (`setTranslation('name', 'en', 'Cairo')`) and AR (`setTranslation('name', 'ar', 'القاهرة')`); test `hasMany(Region)` relationship; test `scopeActive()` filters correctly — group `geography`
- [X] T040 [P] [US1] [US3] Create `tests/Feature/Modules/Geography/RegionTest.php`: factory creation; translatable name EN+AR; `belongsTo(Governorate)` relationship; `scopeForGovernorate()` returns only regions for the given governorate; FK `restrictOnDelete` — deleting a governorate with regions throws `QueryException` — group `geography`
- [X] T041 [P] [US1] [US3] Create `tests/Feature/Modules/Geography/CityTest.php`: factory creation; translatable name EN+AR; `belongsTo(Region)` and `belongsTo(Governorate)` relationships; denorm `governorate_id` equals region's `governorate_id`; `scopeActive()` excludes inactive cities; FK `restrictOnDelete` on both `region_id` and `governorate_id` — group `geography`
- [X] T042 [P] [US1] Create `tests/Feature/Modules/Identity/MigrationSmokeTest.php`: assert all 9 Identity tables exist in the database after migrate; assert `vendor_coverage_areas.city_id` FK is `restrictOnDelete` (attempt to delete a city referenced by coverage area → `QueryException`); assert `customer_addresses.city_id` FK is `restrictOnDelete` (same pattern) — group `migrations`
- [X] T043 [P] [US1] Create `tests/Unit/Modules/Shared/MoneyCastTest.php`: test `get()` converts `(5000, 'EGP')` → `Money::ofMinor(5000, 'EGP')`; test `set()` converts `Money::ofMinor(7500, 'EGP')` → `['base_price_minor' => 7500, 'base_price_currency' => 'EGP']`; test `set()` throws `InvalidArgumentException` when given a float — group `shared`

**Checkpoint**: `./vendor/bin/pest --group=geography,migrations,shared` passes 100%; `/admin` accessible with Egypt geography browsable in EN and AR; GeographyRepository contract is bound and resolvable from the container.

---

## Phase 4: User Story 2 — CI Pipeline Validates Every Push (Priority: P2)

**Goal**: Every push triggers GitHub Actions — install, migrate (MySQL service), Pest, Pint, PHPStan — all pass on a green commit.

**Independent Test**: Push a commit with a deliberate Pint violation; confirm CI fails on the lint step. Fix and push; confirm CI passes end-to-end.

- [X] T044 [US2] Create `.github/workflows/ci.yml`: trigger on `push` and `pull_request`; steps: `actions/checkout`, `shivammathur/setup-php@v2` (PHP 8.3, extensions: pdo_mysql, redis, mbstring, xml, bcmath, gd), `composer install --no-interaction`, start MySQL 8 service container (`MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` from env), wait for MySQL ready, `php artisan key:generate`, `php artisan migrate --force`, `./vendor/bin/pest --ci`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --no-progress`; all credentials read from `${{ secrets.* }}` — never hardcoded
- [~] T045 [P] [US2] Add a `## CI / GitHub Actions Secrets` section to `.env.example` listing every secret required: `CI_DB_PASSWORD`, `CI_REDIS_URL`, `STAGING_SSH_KEY`, `STAGING_HOST`, `STAGING_USER` — document purpose and format for each; confirm all locale, Sanctum, Scout, and Storage variables from T005 are also present (they must already be there — this task only adds the CI-specific block) — **BLOCKED**: `.env*` writes denied by `.claude/settings.json`. Manual step: add CI secrets block to `.env.example`.

**Checkpoint**: Push to `master`; GitHub Actions passes all four steps (install → migrate → pest → pint → phpstan) within 5 minutes.

---

## Phase 5: User Story 4 — Staging Accessible and Verified (Priority: P2, Cut-List Candidate)

**Goal**: App deployed to Hetzner CX22 via Docker Compose + Caddy; `https://staging.instaparty.com/admin` reachable over HTTPS; admin can log in.

> **Cut-list**: This phase can be deferred to Phase 7 (W8 hardening) if Week 1 is running behind. The CI pipeline (Phase 4) is the hard dependency for Phase 2 features; staging is a nice-to-have gate.

**Independent Test**: Visit `https://staging.instaparty.com/admin`, log in with admin credentials, confirm Egypt governorates are listed.

- [X] T046 [US4] Create `docker-compose.prod.yml` at repo root: services for `app` (production image), `mysql`, `redis`; named volumes for persistence; environment variables read from `.env` on the server (not baked into the image)
- [X] T047 [P] [US4] Create `Caddyfile` at repo root: `staging.instaparty.com { reverse_proxy app:8000 }` — Caddy handles TLS via Let's Encrypt automatically
- [X] T048 [US4] Add staging deploy job to `.github/workflows/ci.yml` (runs only on `master` branch, after CI job passes): SSH to Hetzner server via `appleboy/ssh-action`, `git pull`, `docker compose -f docker-compose.prod.yml up -d --build`, `docker compose exec app php artisan migrate --force`; server address, user, and SSH key from GitHub Secrets

**Checkpoint**: `https://staging.instaparty.com/admin` loads over HTTPS; admin login works; Geography Resources display Egypt data.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final integration, seeder wiring, and validation pass.

- [X] T049 Update `database/seeders/DatabaseSeeder.php` to call all seeders in dependency order: `RolesAndPermissionsSeeder` → `AdminUserSeeder` → `EgyptGeographySeeder`; confirm `php artisan migrate --seed` runs cleanly end-to-end
- [~] T050 [P] Add `Telescope` to `.env.example` — **BLOCKED**: `.env*` writes denied by `.claude/settings.json`. Manual step: append `TELESCOPE_ENABLED=false` to `.env.example`. with `TELESCOPE_ENABLED=false` for production; confirm `laravel/telescope` is only active when `APP_ENV=local`
- [~] T051 Follow `specs/001-phase-0-foundation/quickstart.md` — **MANUAL VALIDATION**: requires `docker compose up`, `php artisan migrate --seed`, browser visit to `/admin`. Run locally; not automatable from this session. steps 1-6 on a clean database (drop and recreate); confirm every step succeeds and the admin panel shows correct bilingual geography
- [X] T052 [P] Run `./vendor/bin/pint` + `./vendor/bin/phpstan analyse` one final time; fix any remaining violations before marking phase complete
- [~] T053 [P] Run `php artisan shield:generate --all` — **MANUAL POST-MIGRATE**: requires `php artisan migrate` first (needs `permissions` table). Run locally after T051. one final time to ensure all permissions are regenerated after any resource changes; commit the result

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — **BLOCKS all user stories**
- **US1 + US3 (Phase 3)**: Depends on Foundational completion — these two user stories are tightly coupled (Geography Filament Resources require Geography Models which require Geography Migrations which requires the app to boot)
- **US2 / CI (Phase 4)**: Depends on Foundational completion + at least Phase 3 checkpoint reached (CI must run migrations which requires Geography + Identity migrations to exist)
- **US4 / Staging (Phase 5)**: Depends on Phase 4 (CI must pass before staging deploy is trusted) — cut-list candidate
- **Polish (Phase 6)**: Depends on all desired phases complete

### User Story Dependencies

| Story | Depends on | Independently testable? |
|---|---|---|
| US1 + US3 (Phase 3) | Foundational (Phase 2) | Yes — `pest --group=geography,migrations,shared` + `/admin` browse |
| US2 (Phase 4) | Foundational + at least one green migrate | Yes — push with violation → CI fails; fix → CI passes |
| US4 (Phase 5) | US2 (need CI to gate the deploy job) | Yes — visit staging URL |

### Within Phase 3

1. Geography migrations — sequential (FK dependency): T014a (countries) → T014 (governorates) → T015 (regions) → T016 (cities) → T033 (vendor_coverage_areas, Geography-owned)
2. Identity migrations (T029 depends on T014/T016; T030-T032 parallel with each other; T035 depends on T016; T033 is now in Geography — no longer in Identity sequence)
3. Models (T017, T018, T019) — parallel after migrations
4. Factories (T020, T021, T022) — parallel after models
5. Seeder (T023) — after factories; must seed the `countries` row for Egypt first, then governorates referencing `country_id`
6. Filament Resources (T024, T025, T026) — parallel after models; T026 (City) reads Region's FK so needs Region model
7. GeographyRepository contract + impl (T026a) — parallel with Resources; bind in ServiceProvider T027
8. ServiceProvider + shield:generate (T027, T028) — after Resources and T026a
9. Tests (T039-T043, T041a) — parallel after models + migrations

### Parallel Opportunities

**Phase 1** — T003, T004, T005 can run in parallel (different files).

**Phase 2** — T007, T011, T012, T013 can run in parallel after T006 + T008 are done; T009 (Filament install) can start as soon as T002 finishes; T010a (Sanctum config) can run in parallel with T010.

**Phase 3** — After T016 (cities migration): T017/T018/T019 run in parallel; T020/T021/T022 run in parallel; T026a (GeographyRepository) runs in parallel with Filament Resources; T030/T031/T032/T034/T036/T037 run in parallel; T033 (vendor_coverage_areas) runs after T016 and T029 (vendor_profiles must exist for the FK).

**Phase 3 tests** — T039, T040, T041, T042, T043 all run in parallel (different files, different groups).

---

## Parallel Execution Example: Phase 3 (Geography models + factories)

```bash
# Geography migrations (sequential — FK dependency chain):
Task: "Create countries migration" (T014a)
Task: "Create governorates migration" (T014)   # depends on T014a
Task: "Create regions migration" (T015)         # depends on T014
Task: "Create cities migration" (T016)          # depends on T015

# After T016 + T029 (vendor_profiles), run vendor_coverage_areas:
Task: "Create vendor_coverage_areas migration in Geography" (T033)

# After T016, run all three model files in parallel:
Task: "Create Governorate.php model" (T017)
Task: "Create Region.php model" (T018)
Task: "Create City.php model" (T019)

# After models, run factories and repository contract in parallel:
Task: "Create GovernorateFactory.php" (T020)
Task: "Create RegionFactory.php" (T021)
Task: "Create CityFactory.php" (T022)
Task: "Create GeographyRepository contract + impl" (T026a)

# After factories and migrations, run all tests in parallel:
Task: "Create GovernorateTest.php" (T039)
Task: "Create RegionTest.php" (T040)
Task: "Create CityTest.php" (T041)
Task: "Create GeographyRepositoryTest.php" (T041a)
Task: "Create MigrationSmokeTest.php" (T042)
Task: "Create MoneyCastTest.php" (T043)
```

---

## Implementation Strategy

### MVP First (US1 + US3 only — fully bootable app with Geography)

1. Complete Phase 1: Setup (T001–T005)
2. Complete Phase 2: Foundational (T006–T013, including T010a Sanctum config)
3. Complete Phase 3: US1 + US3 (T014a, T014–T043, T026a, T033 in Geography, T041a)
4. **STOP and VALIDATE**: `php artisan migrate --seed` clean; `pest --group=geography,migrations,shared` 100%; `/admin` shows bilingual geography; GeographyRepository resolves from container
5. This is the Day 3 deliverable from the Phasing Plan

### Incremental Delivery

1. **Day 1–2**: Phases 1 + 2 → Foundation ready (app boots, Filament at `/admin`)
2. **Day 3**: Phase 3 → Geography module complete (US1 + US3 done, tests green)
3. **Day 4**: Remaining Phase 3 Identity migrations (T029–T038)
4. **Day 5**: Phase 4 CI (T044–T045) → pipeline green
5. **Day 5 (if time)**: Phase 5 Staging (T046–T048) — or defer to W8

---

## Notes

- All tasks include exact file paths — each is immediately executable by an LLM without additional context
- `[P]` tasks write to different files with no incomplete-task dependencies — safe to run in parallel
- Geography test group `geography` and migration smoke test group `migrations` are independent — `pest --group=geography` can run before Identity migrations are written
- The cut-list from the Phasing Plan: if behind on Day 5, skip Phase 5 (T046–T048) entirely — CI (Phase 4) is sufficient for soft-launch readiness
- Run `php artisan shield:generate --all` (T028) immediately after the Geography Resources are created — do not defer
- Never commit `.env` files or real credentials — all CI secrets via GitHub Actions Secrets
- `MoneyCast` (T007) has no tests of its own until T043 — write T043 immediately after T007 is done to keep test coverage tight
- **Geography → Meilisearch re-index observers** (Tech Decisions §1.1) are deferred to Phase 1 when the Catalog module is created and the Scout-indexed `services` table exists. Stubbing observers in Phase 0 would create dead code with no index to sync against.
- **Filament plugin lock**: Before implementing T009, update `docs/specs/02_Tech_Decisions.md §13` to add the 6 extra plugins (`filament-settings`, `filament-activitylog`, `tiptap-editor`, `filament-language-switch`, `filament-excel`, `filament-fullcalendar`) with one-line justifications. This keeps the package list authoritative.
- **ADR-0001 module count**: ADR-0001 states "13 modules" but Tech Decisions §1 lists 12 (Geography, Shared, Identity, Catalog, Discovery, Booking, Negotiation, Payments, Settlement, Reviews, Communication, Reporting). Update ADR-0001 to say 12 — the discrepancy predates the Negotiation/Booking split decision.
- **`vendor_coverage_areas` ownership**: Geography module owns the schema (migration in `Geography/Database/Migrations/`); Identity's `VendorProfile` model only holds the `belongsToMany` relationship pointer. Cross-module schema ownership must never drift.
