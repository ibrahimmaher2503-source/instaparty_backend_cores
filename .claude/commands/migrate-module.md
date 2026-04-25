---
description: Plan and generate migrations for a module of the InstaParty backend
argument-hint: <module-name> (e.g. Identity, Catalog, Booking)
---

# Generate module migrations

You're about to generate the migrations for the **$ARGUMENTS** module of InstaParty.

## Required reading first

Before writing any code:

1. Read `docs/specs/02_Tech_Decisions.md` §4 (Database) and §1 (Architecture).
2. Read `docs/specs/03_Three_Product_Types.md` if the module touches Catalog, Booking, Discovery, Reviews, or Imports.
3. Check `.claude/rules/migrations.md` for charset, ULID, money, FK, and soft-delete rules.

## Plan first, then code

Output a plan that includes:

- The list of tables to create, in dependency order.
- For each table: a one-line purpose + a column summary (don't write SQL yet).
- Any tables this module **needs from another module** (and which migration order assumes).
- Any indexes or composite UNIQUE constraints worth highlighting.
- Whether soft-deletes apply (per CLAUDE.md §15).

Then **wait for confirmation** before writing the migration files.

## After confirmation

Generate migrations in `app/Modules/$ARGUMENTS/Database/Migrations/` with timestamps in dependency order. After all migrations are written:

1. Run `php artisan migrate` to apply.
2. If any model files don't exist yet for the new tables, scaffold them in `app/Modules/$ARGUMENTS/Domain/Models/` with relationships, casts, scopes, and `$translatable` arrays where applicable.
3. Run `./vendor/bin/pest --filter=Module$ARGUMENTS --bail` if any tests exist for this module.

## Anti-patterns to avoid

- DECIMAL or FLOAT for money — always integer minor units + currency.
- Forgetting `utf8mb4` charset on `Schema::create`.
- Forgetting `public_id` ULID column on user-facing entities.
- Adding `softDeletes()` to append-only tables.
- A new top-level `services_*_full` mega-table — services follow polymorphic base + 3 detail tables.
