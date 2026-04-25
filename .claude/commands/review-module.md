---
description: Perform a deep code review of a module against locked specs
argument-hint: <module-name>
---

# Review Module

You're reviewing the **$ARGUMENTS** module.

## Review against

1. `CLAUDE.md` (constitution)
2. `docs/specs/02_Tech_Decisions.md` (locked stack and DB conventions)
3. `docs/specs/03_Three_Product_Types.md` (if module is type-aware)
4. `docs/specs/04_Bilingual_Spec.md` (if module has user-facing content)
5. `.claude/rules/migrations.md`, `.claude/rules/actions.md`, `.claude/rules/modules.md`, `.claude/rules/filament.md`, `.claude/rules/product-types.md`

## Analyze

### 1. Architecture
- Module structure follows the Domain/Application/Infrastructure/Http/Filament layout?
- Cross-module imports through Contracts only, no direct Model imports?
- ServiceProvider registers routes, migrations, translations, listeners?

### 2. Database
- Naming consistency (`{field}_minor`, `{field}_currency`, `public_id`, `{table}_id` for FKs)
- All FKs declared and constrained?
- Missing indexes for (FK, status) and (FK, created_at) combos?
- ULID `public_id` on user-facing entities?
- Money columns are `BIGINT UNSIGNED` paired with `CHAR(3)` currency, never DECIMAL/FLOAT
- Soft-delete only on tables in CLAUDE.md §15? Append-only tables truly append-only?
- All `Schema::create` callbacks set `utf8mb4` charset and `utf8mb4_unicode_ci` collation?

### 3. Models
- Models hold ONLY relationships, casts, scopes (no business logic)?
- Translatable fields declared with `protected $translatable = [...]`?
- Money cast registered correctly?
- States via spatie/laravel-model-states where relevant?

### 4. Actions
- One `execute()` method per Action? Constructor-injected deps?
- Mutations wrapped in `DB::transaction`?
- Events fired with `DB::afterCommit` or queued listeners?
- Per-type Actions named correctly (`Create{Type}{Noun}Action`)?
- Cross-type code uses `match($enum)` — no `if/elseif` on type strings?

### 5. Controllers
- 3-line action body maximum?
- All real work delegated to Action classes?
- ApiResponse envelope used consistently?

### 6. Filament (if module has resources)
- Per-product-type Resources (three for services, not one)?
- Translatable plugin with EN/AR tabs?
- Shield permissions generated and per-type-scoped?
- Resources auto-discovered from the module's Filament/Resources/?

### 7. Tests
- Coverage: happy path, auth, authz, validation, idempotency, locale?
- Type-aware features: do tests cover all three product types?
- Pest groups used (`->group('rental')`, etc.)?

### 8. Phase 1 scope adherence
- Anything in this module that belongs to Phase 2 (subscription tiers, packages, dispute engine, card templates, page slider, QR catalog, advanced tax)?

## Output format