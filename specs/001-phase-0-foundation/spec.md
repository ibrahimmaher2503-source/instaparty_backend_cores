# Feature Specification: Phase 0 — Foundation

**Feature Branch**: `001-phase-0-foundation`
**Created**: 2026-04-26
**Status**: Draft
**Input**: User description: "Phase 0 — Foundation: bootable Laravel 12 + Filament v3 app with Geography module, Docker dev environment, CI pipeline. Verified deployable to staging."

---

## Clarifications

### Session 2026-04-26

- Q: Does Geography get its own module (`app/Modules/Geography/`) or does it live inside `app/Modules/Shared/`? → A: Standalone `app/Modules/Geography/` module; `Shared` holds only cross-cutting utilities (MoneyCast, base interfaces).
- Q: How are MinIO buckets and Meilisearch indexes initialized after `docker compose up`? → A: A single idempotent `php artisan app:setup-dev-env` command creates the MinIO bucket and verifies Meilisearch connectivity; documented in README and run once after `docker compose up`.
- Q: How are sensitive staging credentials (DB password, Redis, Meilisearch, MinIO, app key) managed across CI and staging? → A: GitHub Actions Secrets; the deploy step writes the staging `.env` file on the server via SSH from secrets; the CI test matrix reads credentials directly from GitHub Actions env vars. Secrets are never committed to the repository.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Developer boots the app locally (Priority: P1)

A developer clones the repository, runs the Docker Compose stack, seeds Egypt geography, and has a working `/admin` panel with governorates, regions, and cities browsable in English and Arabic — all within a single terminal session.

**Why this priority**: Everything else depends on a runnable local environment. Without this, no other development work can proceed.

**Independent Test**: Run `docker compose up`, seed Egypt geography, visit `/admin`, and confirm all three geography entities are visible with both EN and AR names.

**Acceptance Scenarios**:

1. **Given** a fresh clone with `.env` copied from `.env.example`, **When** `docker compose up` is run, **Then** the app, MySQL, Redis, Meilisearch, Mailpit, and MinIO containers all start without errors.
2. **Given** the containers are running, **When** `php artisan migrate && php artisan db:seed --class=EgyptGeographySeeder` is run, **Then** all governorates, regions, and cities for Egypt are created with translatable EN and AR names.
3. **Given** the database is seeded, **When** an admin visits `/admin` and browses Geography, **Then** governorates, regions, and cities are listed correctly and the locale switcher toggles between English and Arabic display.
4. **Given** a fresh environment, **When** `php artisan migrate`, **Then** the full schema — including Identity module migrations — is applied without errors.

---

### User Story 2 — CI pipeline validates every push (Priority: P2)

When a developer pushes to the repository, a GitHub Actions workflow automatically installs dependencies, runs migrations, executes the Pest test suite, runs Pint for formatting, and runs PHPStan for static analysis — reporting pass or fail.

**Why this priority**: Prevents regressions from landing on the main branch; critical for solo development where there is no code reviewer to catch breakage.

**Independent Test**: Push a commit with a deliberate formatting error and confirm CI flags it. Then fix and push again to confirm CI passes.

**Acceptance Scenarios**:

1. **Given** a push to the repository, **When** GitHub Actions triggers, **Then** the workflow installs Composer dependencies, runs `php artisan migrate`, and executes `./vendor/bin/pest` — all steps completing successfully on a green codebase.
2. **Given** a push with a Pint formatting violation, **When** CI runs, **Then** the pipeline fails on the Pint step and reports which file needs formatting.
3. **Given** a push with a PHPStan level-8 violation, **When** CI runs, **Then** the pipeline fails on the PHPStan step with the offending line reported.

---

### User Story 3 — Admin can manage Egypt geography in bilingual Filament (Priority: P1)

An admin user logs in to the Filament panel and can create, read, update, and delete governorates, regions, and cities — providing both English and Arabic names — with the interface itself switchable between English and Arabic.

**Why this priority**: Geography is the foundation of vendor coverage areas and customer address selection. All subsequent modules reference `cities`. Bilingual correctness must be verified before any other Filament work.

**Independent Test**: Log in to `/admin`, create a new governorate in both EN and AR, create a region under it, create a city under that region, then switch the panel language to Arabic and verify all names render correctly.

**Acceptance Scenarios**:

1. **Given** an admin is logged in, **When** they create a governorate with `name_en = "Cairo"` and `name_ar = "القاهرة"`, **Then** the governorate is saved and both names appear in the list view.
2. **Given** a governorate exists, **When** an admin creates a region under it, **Then** the region is linked to the correct governorate and appears in the region list filtered by that governorate.
3. **Given** a region exists, **When** an admin creates a city with latitude/longitude and marks it active, **Then** the city appears under the correct region and governorate, with the `governorate_id` denormalized correctly.
4. **Given** the panel is in English, **When** the admin switches the top-bar locale to Arabic (العربية), **Then** the Filament UI labels, navigation, and all translatable record values render in Arabic without error.
5. **Given** a city with `is_active = false`, **When** queried for vendor coverage area assignment, **Then** it does not appear in the active city list.

---

### User Story 4 — Staging is accessible and verified (Priority: P2)

After the local environment is confirmed working, the app is deployed to a Hetzner CX22 staging server. The `/admin` login page is reachable over HTTPS at the staging domain, migrations have run, and an admin user can log in.

**Why this priority**: Confirms that the deployment pipeline works before any feature code is built, catching environment-specific issues early.

**Independent Test**: Deploy the app to staging, visit `https://staging.instaparty.com/admin`, log in with the seeded admin credentials, and browse the Geography section.

**Acceptance Scenarios**:

1. **Given** the app is deployed to staging, **When** a browser visits `https://staging.instaparty.com/admin`, **Then** the Filament login page loads over HTTPS without certificate errors.
2. **Given** the login page loads, **When** the admin credentials are entered, **Then** the admin is logged in and can see the dashboard.
3. **Given** the admin is logged in on staging, **When** they browse Geography, **Then** the seeded Egypt governorates, regions, and cities are present.

---

### Edge Cases

- What happens when a region is deleted that has cities referencing it? The foreign key constraint must prevent deletion (restrictOnDelete) until all child cities are removed first.
- What happens when `php artisan migrate` is run twice? Laravel's migration tracking must prevent duplicate migrations.
- What happens if the Docker Redis container is down? The app must surface a clear error rather than silently failing to queue jobs.
- What happens when a city is created without a valid `region_id`? Validation must reject the request.
- What happens when the Arabic name is left blank for a geography entry? Both EN and AR names are required — the form must not save a partial translation.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST run a complete local development environment via `docker compose up`, including the application server, MySQL database, Redis, Meilisearch, email preview (Mailpit), and object storage (MinIO). After containers start, a single idempotent `php artisan app:setup-dev-env` command MUST create the required MinIO bucket and verify Meilisearch connectivity, completing the environment bootstrap without manual console steps.
- **FR-002**: The system MUST apply all database migrations — Framework, Geography, and Identity — via `php artisan migrate` without errors.
- **FR-003**: The system MUST seed Egypt's geography (governorates, regions, cities) with accurate English and Arabic names via a dedicated seeder.
- **FR-004**: The `cities` table MUST denormalize `governorate_id` for query performance; this must be kept consistent by application logic, not a separate sync job.
- **FR-005**: The `MoneyCast` custom Eloquent cast MUST convert `{field}_minor` integer columns to `Brick\Money\Money` value objects and back, used by all money-carrying models.
- **FR-006**: The Filament v3 admin panel MUST be installed at `/admin` with custom resource discovery scanning `app/Modules/*/Filament/Resources/` recursively.
- **FR-007**: The Filament panel MUST include a top-bar locale switcher allowing admins to toggle between English and Arabic.
- **FR-008**: All three Geography Filament Resources (Governorate, Region, City) MUST display and edit translatable `name` fields using the `filament/spatie-laravel-translatable-plugin` EN/AR tab pattern.
- **FR-009**: The GitHub Actions CI pipeline MUST run on every push: install Composer dependencies, run migrations against a test database, execute the full Pest suite, run Pint, and run PHPStan at level 8. All credentials used in CI (database, Redis, Meilisearch, MinIO) MUST be provided via GitHub Actions environment variables — never committed to the repository. The staging deploy step MUST write the staging `.env` file on the Hetzner server from GitHub Actions Secrets over SSH.
- **FR-010**: Pest tests MUST cover: Geography model factory creation, translatable field write and read in EN and AR, and foreign key relationship correctness (governorate → region → city).
- **FR-011**: The app MUST be deployable to a Hetzner CX22 staging server via a Docker Compose + Caddy setup serving HTTPS.
- **FR-012**: The Filament Geography Resources MUST enforce that both EN and AR `name` values are provided before saving.
- **FR-013**: The Spatie Permission roles seeder MUST create the standard roles (`admin`, `vendor`, `customer`) and seed the per-product-type vendor permissions defined in the schema (`service.create.{rental|sale|digital}.own`, etc.).
- **FR-014**: The Identity module migrations MUST be applied in Phase 0 (the tables exist), even though the Identity module's models, Actions, and Filament Resources are deferred to Phase 1. Because `vendor_coverage_areas.city_id` and `customer_addresses.city_id` are foreign keys to the `cities` table, all Identity module migrations MUST run after the three Geography migrations have been applied.
- **FR-015**: A Pest test MUST verify that all Identity module migrations apply cleanly against a fresh database, and that the `vendor_coverage_areas.city_id` and `customer_addresses.city_id` foreign key constraints exist with `restrictOnDelete` semantics (i.e., deleting a city that is referenced by a vendor coverage area or customer address must be rejected by the database).

### Key Entities

- **Governorate**: Top-level administrative region (e.g., Cairo). Has a `code` (ISO-style), a bilingual `name`, and an `is_active` flag. Has many regions.
- **Region**: Sub-division of a governorate (e.g., Heliopolis). Has a bilingual `name` and an `is_active` flag. Has many cities.
- **City**: Leaf-level location used for vendor coverage and customer addresses. Has a bilingual `name`, optional lat/lon centroid, and denormalized `governorate_id`. Referenced by many other modules.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer with no prior setup can have the local environment running and the admin panel accessible within 15 minutes of cloning the repository, by following three steps: `docker compose up`, `php artisan migrate && php artisan db:seed`, `php artisan app:setup-dev-env`.
- **SC-002**: `php artisan migrate` completes in under 30 seconds on a fresh local database, with zero errors.
- **SC-003**: The Pest suite for the Geography module passes 100% with coverage of model factory, translatable read/write (EN and AR), and relationship integrity.
- **SC-004**: The CI pipeline completes in under 5 minutes on a standard GitHub Actions runner.
- **SC-005**: An admin switching the Filament panel from English to Arabic sees all Geography record names correctly rendered in Arabic with no missing or garbled text.
- **SC-006**: The staging deployment at `https://staging.instaparty.com/admin` is reachable and the admin can log in within 10 minutes of a successful deploy pipeline run.

---

## Assumptions

- The project starts from a fresh `composer create-project laravel/laravel` scaffold; no legacy code exists.
- Only Egypt geography is seeded in Phase 0; other countries are deferred to a later phase.
- The staging server (Hetzner CX22) is provisioned and SSH-accessible before Day 5.
- The staging domain `staging.instaparty.com` has DNS pointing to the server before Day 5 (can be deferred per the cut-list).
- The CI environment uses a MySQL 8 service container matching the production MySQL version.
- Phase 0 does NOT include the Identity module's business logic (models, Actions, Filament Resources) — only the migrations; those arrive in Phase 1.
- Geography module does NOT touch product types; it is entirely cross-cutting (no rental/sale/digital type-awareness needed).
- Geography has its own module (`app/Modules/Geography/`) with its own ServiceProvider, migrations, models, factories, seeders, and Filament Resources. `app/Modules/Shared/` holds only cross-cutting utilities: `MoneyCast`, base interfaces, and similar project-wide concerns.
- `MoneyCast` is written once in `app/Modules/Shared/Domain/Casts/` and reused by all money-carrying models across all modules.
- The admin first-user seeder creates one super-admin account; credential management is out of scope for Phase 0.
- Docker Compose volumes for MySQL and MinIO persist between restarts using named volumes (not ephemeral).
- Sensitive credentials (database password, Redis password, Meilisearch master key, MinIO keys, app key) are never committed to the repository. Locally they live in `.env` (git-ignored); in CI and on staging they are provided via GitHub Actions Secrets.
