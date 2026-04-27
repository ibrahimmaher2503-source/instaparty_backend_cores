# Implementation Plan: Phase 0 — Foundation

**Branch**: `master` | **Date**: 2026-04-26 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/001-phase-0-foundation/spec.md`

## Summary

Bootstrap a runnable Laravel 12 + Filament v3 modular monolith with a fully sliced Geography module (migrations → models → factories → seeders → Filament Resources with EN/AR), Identity module migrations applied schema-only, `MoneyCast` shared cast, Docker dev environment, Spatie roles seeder, and a GitHub Actions CI pipeline — verified against a staging Hetzner server. No API endpoints, Actions, or DTOs in this phase.

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12
**Primary Dependencies**: Filament v3, Spatie (Permission, Translatable, MediaLibrary, Data, ModelStates, ActivityLog, Backup), Brick/Money, Meilisearch SDK, Sanctum, Scout, Reverb, Predis — all pinned per `10_Package_List.md`
**Storage**: MySQL 8 / MariaDB 11 (`utf8mb4_unicode_ci`), Redis (cache/queue/sessions), MinIO (local object storage), Meilisearch (search — not used until Phase 3)
**Testing**: Pest v3 + pest-plugin-laravel + pest-plugin-arch; PHPStan level 8 (Larastan); Pint (Laravel preset)
**Target Platform**: Docker Compose (local), Hetzner CX22 + Caddy (staging)
**Project Type**: Modular monolith web service (Laravel API at `/` + Filament admin at `/admin`)
**Performance Goals**: CI pipeline under 5 minutes; `php artisan migrate` under 30 seconds; admin list pages under 2 seconds
**Constraints**: Phase 0 scope only (no API endpoints, no Actions, no DTOs); `utf8mb4` on every table; no floats for money; no Phase 2 features; no packages outside `10_Package_List.md`
**Scale/Scope**: 20 tables in Phase 0 (framework + geography + identity); 11 modules total across all 8 phases; soft launch target = 5 vendors

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Rule (from CLAUDE.md) | Status | Notes |
|---|---|---|
| Stack is Laravel 12 + Filament v3 (locked) | PASS | Exact versions pinned in plan |
| Module layout: `app/Modules/{Name}/Domain/Application/Infrastructure/…` | PASS | Geography and Identity follow layout; Shared holds MoneyCast only |
| Models: only relationships, casts, scopes — no business logic | PASS | Phase 0 has no business logic anywhere |
| Translatable fields: JSON via `spatie/laravel-translatable` | PASS | All `name` columns on Geography are JSON |
| IDs: internal `id` BIGINT + external `public_id` CHAR(26) ULID | PASS | All user-facing tables have both |
| Money: `{field}_minor` BIGINT + `{field}_currency` CHAR(3) + MoneyCast | PASS | MoneyCast in Shared; Identity migrations use money convention |
| Migrations: `declare(strict_types=1)`, anonymous class, `utf8mb4` | PASS | All migrations follow .claude/rules/migrations.md |
| Soft deletes ONLY on listed tables | PASS | Geography has no soft deletes; Identity tables follow spec |
| No `if/elseif` on type strings — use `match($enum)` | PASS | No type-aware code in Phase 0 |
| Package discipline: no packages not in `10_Package_List.md` | PASS | All packages on the locked list |
| Filament resources auto-discovered from `app/Modules/*/Filament/Resources/` | PASS | AdminPanelProvider configured for module discovery |
| Run `shield:generate --all` after every new Resource | PASS | Included as explicit task in the ordered task list |
| Phase 1 scope: no Phase 2 features | PASS | Phase 0 has no subscription tiers, packages, dispute engine, etc. |

**Result: All gates pass.**

## Project Structure

### Documentation (this feature)

```text
specs/001-phase-0-foundation/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   ├── module-service-provider.md
│   └── money-cast.md
└── tasks.md             # Phase 2 output (/speckit.tasks — not created here)
```

### Source Code (repository root)

```text
app/
├── Modules/
│   ├── Shared/
│   │   ├── Domain/Casts/MoneyCast.php
│   │   └── Providers/SharedServiceProvider.php
│   ├── Geography/
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   │   ├── 2026_01_01_000001_create_governorates_table.php
│   │   │   │   ├── 2026_01_01_000002_create_regions_table.php
│   │   │   │   └── 2026_01_01_000003_create_cities_table.php
│   │   │   ├── Factories/
│   │   │   │   ├── GovernorateFactory.php
│   │   │   │   ├── RegionFactory.php
│   │   │   │   └── CityFactory.php
│   │   │   └── Seeders/EgyptGeographySeeder.php
│   │   ├── Domain/Models/
│   │   │   ├── Governorate.php
│   │   │   ├── Region.php
│   │   │   └── City.php
│   │   ├── Filament/Resources/
│   │   │   ├── GovernorateResource.php
│   │   │   ├── RegionResource.php
│   │   │   └── CityResource.php
│   │   └── Providers/GeographyServiceProvider.php
│   └── Identity/
│       ├── Database/Migrations/
│       │   ├── 2026_01_01_000010_create_vendor_profiles_table.php
│       │   ├── 2026_01_01_000011_create_vendor_documents_table.php
│       │   ├── 2026_01_01_000012_create_vendor_approved_product_types_table.php
│       │   ├── 2026_01_01_000013_create_vendor_business_hours_table.php
│       │   ├── 2026_01_01_000014_create_vendor_coverage_areas_table.php
│       │   ├── 2026_01_01_000015_create_customer_profiles_table.php
│       │   ├── 2026_01_01_000016_create_customer_addresses_table.php
│       │   ├── 2026_01_01_000017_create_user_devices_table.php
│       │   └── 2026_01_01_000018_create_two_factor_secrets_table.php
│       └── Providers/IdentityServiceProvider.php
├── Console/Commands/SetupDevEnvironmentCommand.php
└── Providers/Filament/AdminPanelProvider.php

database/
├── migrations/         # Laravel framework tables (users extended here)
└── seeders/
    ├── DatabaseSeeder.php
    ├── RolesAndPermissionsSeeder.php
    └── AdminUserSeeder.php

tests/
├── Feature/Modules/
│   ├── Geography/
│   │   ├── GovernorateTest.php
│   │   ├── RegionTest.php
│   │   └── CityTest.php
│   └── Identity/
│       └── MigrationSmokeTest.php
└── Unit/Modules/Shared/
    └── MoneyCastTest.php

.github/workflows/ci.yml
docker-compose.yml
.env.example
phpstan.neon
pint.json
```

**Structure Decision**: Single modular monolith project. All domain code under `app/Modules/{Name}/`. Framework seeders/migrations in standard Laravel paths. Module-specific migrations and factories live inside each module's `Database/` directory, loaded via the module's ServiceProvider. Tests mirror the module path under `tests/Feature/Modules/` and `tests/Unit/Modules/`.

## Complexity Tracking

> No Constitution violations. No justifications required.
