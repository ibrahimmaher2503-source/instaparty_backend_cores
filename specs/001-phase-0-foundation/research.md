# Research: Phase 0 — Foundation

**Date**: 2026-04-26
**Status**: Complete — all unknowns resolved

---

## Decision 1: Geography module placement — standalone vs. inside Shared

**Decision**: Standalone `app/Modules/Geography/` module with its own ServiceProvider, migrations, models, factories, seeders, and Filament Resources.

**Rationale**: Geography (`governorates`, `regions`, `cities`) is a bounded context in its own right. Multiple downstream modules (Identity's `vendor_coverage_areas`, `vendor_profiles`; Shared's `customer_addresses`) all depend on `cities`. Keeping it as a standalone module means the module boundary is clear, it can be migrated and tested in isolation, and its ServiceProvider declares the dependency order explicitly.

**Alternatives considered**:
- Place under `app/Modules/Shared/`: Rejected — Shared is for cross-cutting utilities (casts, base interfaces), not for domain data with its own migration dependency chain.
- Place under `app/Modules/Identity/`: Rejected — Identity depends on Geography, not the other way around.

---

## Decision 2: Module resource auto-discovery in Filament v3

**Decision**: Configure `AdminPanelProvider::discoverResources()` to scan `app/Modules/*/Filament/Resources/` using a glob. In Laravel + Filament v3 this is done by calling `->discoverResources(in: app_path('Modules'), for: 'App\\Modules')` with a custom path resolver, or by explicitly registering each module's resources directory.

**Rationale**: The CLAUDE.md spec requires Filament Resources to live under each module's `Filament/Resources/` folder, not the default `app/Filament/Resources/`. Filament v3's `discoverResources()` accepts a base path and namespace prefix, making it straightforward to scan multiple module directories.

**Implementation pattern**:
```php
// app/Providers/Filament/AdminPanelProvider.php
$panel->discoverResources(
    in: app_path('Modules'),
    for: 'App\\Modules',
);
```
Filament will recurse into every subdirectory and register any class extending `Filament\Resources\Resource`.

**Alternatives considered**:
- Manually call `->resources([...])` for each resource: Rejected — requires updating AdminPanelProvider every time a new resource is added; error-prone.
- Place all resources in `app/Filament/Resources/`: Rejected — violates CLAUDE.md module layout rule (modules.md: "Filament resources outside the module's `Filament/Resources/` — fail").

---

## Decision 3: `users` table extension strategy

**Decision**: Extend the single Laravel-generated `users` migration (`0001_01_01_000000_create_users_table.php`) directly — add the InstaParty-specific columns (`public_id`, `phone_e164`, `preferred_locale`, `timezone`, `numeral_system`, `status`, `last_login_at`, `last_login_ip`, `deleted_at`) to the existing migration file rather than creating a separate `alter_users_table` migration.

**Rationale**: This is a fresh project with no legacy data. Editing the initial migration gives a clean, single-migration history. An `ALTER TABLE` migration would require running two migrations and could cause ordering issues with Spatie Permission (which also creates tables in an early migration).

**Alternatives considered**:
- Separate `alter_users_table` migration: Rejected for fresh projects — adds migration debt with no benefit when starting from scratch.
- Use the framework's `HasUlids` trait and override `public_id` generation: Kept — Laravel's `Str::ulid()` and the `HasUlids` trait are used on all models that expose `public_id` (no external package needed per `10_Package_List.md`).

---

## Decision 4: MoneyCast placement and API

**Decision**: `App\Modules\Shared\Domain\Casts\MoneyCast` implements `CastsAttributes`. It accepts `['{field}_minor', '{field}_currency']` as additional cast parameters and returns a `Brick\Money\Money` instance from `get()` and accepts `Brick\Money\Money | int` in `set()`.

**Rationale**: `Brick\Money` is the mandated money library (CLAUDE.md Convention #6). Implementing it as a native Eloquent cast keeps model code clean — just declare `protected $casts = ['base_price' => MoneyCast::class]` using the compound cast pattern. The cast reads/writes to two underlying columns automatically.

**Implementation pattern**:
```php
// Usage on any model:
protected function casts(): array
{
    return [
        'base_price' => MoneyCast::class . ':base_price_minor,base_price_currency',
    ];
}
```

**Alternatives considered**:
- Return `Brick\Money\Money` as an Eloquent accessor: Rejected — accessors are not type-safe and don't participate in `fill()`/`create()` flows cleanly.
- Use `moneyphp/money`: Rejected explicitly in `10_Package_List.md` §7 ("don't mix them in one project").

---

## Decision 5: Egypt geography seed data format

**Decision**: Seed from a PHP array defined in `EgyptGeographySeeder`, grouped as `[governorate => [region => [cities...]]]` with explicit EN and AR names for each entry. At minimum seed Cairo governorate → 2 regions → 3 cities to satisfy SC-007.

**Rationale**: A PHP array seeder is version-controlled, readable, and doesn't require a CSV/Excel import tool in Phase 0. Full Egypt geography can be expanded in Phase 1 or imported from an open dataset later.

**Alternatives considered**:
- Import from an open-data JSON file: Deferred — adds I/O complexity in Phase 0; PHP array is simpler and still correct.
- Use a DB transaction per region: Kept — seeder wraps all inserts in a single `DB::transaction()` to avoid partial seeds.

---

## Decision 6: CI database strategy

**Decision**: GitHub Actions CI uses a MySQL 8 service container. The `.github/workflows/ci.yml` defines a `mysql` service with `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` from GitHub secrets. The workflow sets `DB_*` env vars from those secrets in the `env:` block.

**Rationale**: Matches the production database engine version. SQLite is forbidden (charset and FK differences would hide migration bugs). Secrets are never committed to the repo (FR-009).

**Alternatives considered**:
- SQLite in-memory for CI: Rejected — MySQL-specific syntax (ENUM, utf8mb4 collation, FK constraints) would silently pass on SQLite but fail in production.
- Use a pre-provisioned RDS: Rejected — overkill for Phase 0; GitHub-hosted MySQL service container is free and sufficient.

---

## Decision 7: Staging deployment approach

**Decision**: Docker Compose + Caddy on Hetzner CX22. A `docker-compose.prod.yml` defines the app, MySQL, Redis services with production env. Caddy provides automatic HTTPS via Let's Encrypt. The CI deploy job SSHs into the server, pulls the latest image, and runs `docker compose up -d`.

**Rationale**: Simple, reproducible, matches the local Docker Compose setup. Caddy handles TLS automatically with zero config. Single server is sufficient for the soft-launch target of 5 vendors.

**Cut-list note**: This step is deferrable to Phase 7 (W8) per the Phasing Plan cut-list. Mark it as optional in the task list.

---

## All NEEDS CLARIFICATION resolved

No open questions remain from the spec. All technical decisions above are consistent with:
- `docs/specs/02_Tech_Decisions.md` — stack and architecture
- `docs/specs/11_DB_Schema.md` — table structures
- `docs/specs/10_Package_List.md` — locked packages
- `CLAUDE.md` — immutable coding conventions
