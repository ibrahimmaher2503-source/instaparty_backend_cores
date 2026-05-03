# InstaParty Backend Constitution

> **Audience:** Every spec-kit command (`/speckit.specify`, `/speckit.plan`, `/speckit.tasks`, `/speckit.implement`, `/speckit.checklist`, `/speckit.analyze`) must read this file before generating any artifact. When this constitution conflicts with a chat instruction, **this file wins** — Ibrahim must explicitly amend the constitution to override.

---

## Project Source Files

These are the canonical project documents that every spec-kit command must be aware of. Read the relevant ones before generating any artifact.

| Path | One-line description |
|---|---|
| `.specify/memory/constitution.md` | This file — locked principles, rules, and spec-kit workflow integration |
| `docs/specs/01_PRD.md` | Phase 1 Product Requirements Document — functional requirements (FR-1…FR-30) and business rules (BR-1…BR-6) |
| `docs/specs/02_Tech_Decisions.md` | Locked tech stack, modular monolith rules, three-product-type code architecture |
| `docs/specs/03_Three_Product_Types.md` | Central domain reference for rental / sale / digital — schemas, lifecycles, behaviors |
| `docs/specs/04_Bilingual_Spec.md` | Full EN/AR/RTL implementation rules — translatable fields, validation, locale conversion |
| `docs/specs/05_Software_Description.md` | Original client brief (Arabic + mixed English) describing the app vision |
| `docs/specs/06_Customer_Journey.md` | Customer flow with Mermaid diagrams — discovery → booking → payment → review |
| `docs/specs/07_Vendor_Journey.md` | Vendor flow with Mermaid diagrams — onboarding → catalog → quoting → fulfillment → settlement |
| `docs/specs/08_Admin_Journey.md` | Admin flow with Mermaid diagrams — approvals, moderation, reports, CMS |
| `docs/specs/09_Phasing_Plan.md` | 26 micro-phases (≤3 days each) across 8 weeks with cut-lists and exit criteria |
| `docs/specs/10_Package_List.md` | Locked Composer + NPM package list with rationale and rejected alternatives |
| `docs/specs/11_DB_Schema.md` | Locked 60-table / 13-module DB schema |
| `CLAUDE.md` | Claude Code project memory — coding conventions, pushback triggers, build commands |

---

## Source of Truth Hierarchy

When project artifacts disagree, resolve in this order (top wins):

1. **`docs/specs/01_PRD.md`** — Phase 1 functional and business requirements
2. **`docs/specs/11_DB_Schema.md`** — locked schema (60 tables, 13 modules)
3. **`docs/specs/09_Phasing_Plan.md`** — 26 micro-phases with scope, cut-lists, exit criteria
4. **`.specify/memory/constitution.md`** — this file (principles, workflow, governance)
5. **Spec-kit generated artifacts** — `specs/NNN-feature/spec.md`, `plan.md`, `tasks.md` for the active feature

The detailed 13-tier hierarchy (including Tech Decisions, Three Product Types, Bilingual Spec, ADRs, conversation) is preserved in the original "Source-of-Truth Hierarchy" section below — this 5-tier ladder is the spec-kit-facing summary. When the two are read together, the 5-tier wins for arbitration; the 13-tier provides the granular ordering between specs of equal rank.

---

## Project Identity

- **Project:** InstaParty — multi-vendor event-commerce marketplace (birthdays, weddings, engagements)
- **Repo:** `instaparty-backend` (Laravel API + Filament admin)
- **Sister repos:** `instaparty-mobile` (Flutter, separate ADR system), `instaparty-web` (Next.js, Phase 1.5)
- **Phase:** Phase 1 (8 weeks, 26 micro-phases per `docs/specs/09_Phasing_Plan_v2.md`)
- **Languages:** Arabic (primary, RTL) + English (secondary, LTR) — both mandatory from day 1
- **Owner:** Ibrahim, solo full-stack, Benha Egypt

## Source-of-Truth Hierarchy

When artifacts disagree, resolve in this order (top wins):

1. **`docs/specs/01_PRD.md`** — Phase 1 functional requirements (FR-1 to FR-30, BR-1 to BR-6)
2. **This constitution** (`.specify/memory/constitution.md`)
3. `CLAUDE.md` — Claude Code project memory
4. `docs/specs/02_Tech_Decisions.md` — locked stack
5. `docs/specs/03_Three_Product_Types.md` — rental/sale/digital matrix
6. `docs/specs/04_Bilingual_Spec.md` — translatable fields and locale rules
7. `docs/specs/11_DB_Schema.md` — locked schema (60 tables, 13 modules)
8. `docs/specs/09_Phasing_Plan_v2.md` — 26 micro-phases
9. `docs/specs/10_Package_List.md` — approved packages
10. `docs/adr/*.md` — module-level decisions (ADR-0001 onward)
11. Spec-kit `spec.md` for the active feature
12. Spec-kit `plan.md` for the active feature
13. Conversation

---

## Core Principles

### I. Modular Monolith — NEVER Microservices in Phase 1

Every feature lives under `app/Modules/{ModuleName}/` with the standard layer structure:

```
app/Modules/{Name}/
├── Domain/         # Entities, enums, value objects, contracts (no Laravel deps where possible)
├── Application/    # Actions (use cases), Form Requests, DTOs, Resources
├── Infrastructure/ # Models, Repositories, Gateways, Listeners
├── Filament/       # Resources, Pages, Widgets (auto-discovered)
├── Routes/         # customer.php, vendor.php, admin.php
└── Database/       # Migrations, Factories, Seeders
```

**Cross-module communication rules (NON-NEGOTIABLE):**
- Modules communicate via **domain events** or **public Contracts** (interfaces)
- **NEVER** import another module's Eloquent Model directly across module boundaries
- An architecture test must enforce this: `tests/Architecture/CrossModuleImportTest.php`
- Public contracts live in `Modules/{Name}/Domain/Contracts/` — anything else is internal

**Why:** Phase 2 may extract some modules (Payments, Communications) into services. Clean module boundaries make extraction surgical, not a rewrite.

### II. Three Product Types — `match($enum)`, NEVER if/elseif (NON-NEGOTIABLE)

The system handles three product types with distinct fulfillment logic: **rental** (returnable, with security deposit + setup), **sale** (consumable/keepable, often perishable), **digital** (codes/files/templates with redemption flow).

**Strict rules:**
- Use `App\Modules\Catalog\Domain\Enums\ProductType` enum everywhere — never string literals
- Cross-type code must use `match($productType)` — never `if/elseif` chains on type strings
- Per-type code (Form Requests, Actions, API Resources, Filament Resources) lives in separate classes:
  - `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction`
  - Plus a cross-type `PublishServiceAction` using `match($enum)` internally
- Tests for any type-aware feature must cover **all three types** — no exceptions

**Polymorphic detail tables:**
- `services` (base) → `service_rental_details` / `service_sale_details` / `service_digital_details`
- **NEVER** Single Table Inheritance — keep details in dedicated tables for query performance and schema clarity

### III. Money Discipline — Integer Minor Units, Brick\Money (NON-NEGOTIABLE)

**Rules:**
- Every money column stored as `BIGINT UNSIGNED` named `{field}_minor` + `CHAR(3)` `{field}_currency`
- Cast through `App\Modules\Shared\Domain\Casts\MoneyCast` → returns `Brick\Money\Money` instances
- **NEVER float/decimal for money** — anywhere in the codebase
- All arithmetic via `Brick\Money\Money` methods (`plus`, `minus`, `multipliedBy`, `dividedBy` with rounding mode)
- Display formatting via `core/utils/money_formatter` — locale-aware EGP, Western numerals by default

**Enforcement:**
- A Pint/PHPStan rule MUST flag `float` type hints on money-related variables
- Pest test in `tests/Unit/MoneyCastTest.php` validates cast roundtrip

### IV. Bilingual EN+AR Mandatory — No Late Localization (NON-NEGOTIABLE)

Both English and Arabic content must exist before any feature is considered complete.

**Rules:**
- Translatable text fields use **JSON columns** + `spatie/laravel-translatable`
- Both `en` and `ar` keys must be present — empty strings fail validation
- API Resources convert to current `App::getLocale()` for response (single-locale responses)
- Filament Resources use translatable plugin with EN/AR tabs side-by-side
- Validation error messages must be translatable (resources/lang/{locale}/validation.php)
- Tests must assert response shape in **both** locales for any user-facing endpoint

**RTL/LTR considerations:**
- Filament admin panel must render correctly in AR (RTL) — visual QA in every Filament Day
- API responses include `direction: 'rtl'` or `'ltr'` hint when locale is set

### V. Append-Only Tables — No softDeletes, No Updates Except Status (NON-NEGOTIABLE)

These tables represent immutable facts and history. They MUST NOT have `softDeletes()`, and only specific columns are mutable:

| Table | Mutable columns |
|---|---|
| `wallet_ledger` | (none — fully immutable) |
| `audit_logs` | (none) |
| `payments` | `status`, `gateway_response_log` |
| `commissions` | `status` |
| `booking_state_transitions` | (none) |
| `event_outbox` | `published_at`, `published_attempts` |
| `analytics_events` | (none) |
| `loyalty_ledger` | (none) |
| `booking_snapshots` | (none) |
| `chat_message_log` | (none) |
| `search_logs` | (none) |

**Why:** financial audit, regulatory compliance, debugging production issues, fraud forensics. A wallet credit must be reversible by a counter-entry, never by editing or deleting the original row.

### VI. Spec-Driven Development — ADR Before Code (NON-NEGOTIABLE)

Every new module under `app/Modules/` requires an ADR in `docs/adr/NNNN-{module-slug}.md` with status `Accepted` BEFORE any migration is written.

**ADR template:** `docs/adr/templates/0002-new-module.md`
**Slash command:** `/new-module-adr {ModuleName}` scaffolds an ADR with all required sections

**Required ADR sections:**
1. Status (Proposed → Accepted → Superseded)
2. Context (why this module exists)
3. Decision (the chosen approach)
4. Consequences (positive + negative + neutral)
5. Alternatives Considered (with reasons rejected)
6. Internal Decisions (sub-decisions within the module — see ADR-0003 §6 for example)
7. Related ADRs

**Spec-kit integration:** the `spec.md` for any feature touching a new module MUST reference its ADR by ID in the **Constitution Check** section of the plan.

### VII. Test-First for Critical Paths (NON-NEGOTIABLE for money/auth/bookings)

Pest tests are non-negotiable for: auth flows, money flows, booking state transitions, refund policies per type, commission calculation, idempotency under concurrent load.

**Coverage requirements:**
- **Auth/payments/bookings:** 80%+ on Action classes (mandatory before phase exit)
- **Models:** 80%+ on relationships, scopes, casts
- **Other modules:** 60%+ minimum
- **Type-aware features:** every test group has explicit `it('...rental...')`, `it('...sale...')`, `it('...digital...')` cases — no skipping

**Test-with-code rule:** Pest tests are written in the same day as the Action/Model — never deferred to a "test day" that slips. The phasing plan (`09_Phasing_Plan_v2.md`) treats tests as Day-N work, not "later."

**Architecture tests required:**
- `tests/Architecture/NoCrossModuleModelImportsTest.php`
- `tests/Architecture/NoFloatForMoneyTest.php`
- `tests/Architecture/NoIfElseOnProductTypeStringTest.php`
- `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php`

### VIII. Idempotency for All State-Changing Endpoints

Any endpoint that mutates state (`POST`, `PATCH`, `DELETE`) MUST support idempotency keys for:
- Booking submission
- Booking modification confirmation
- Payment initiation
- Refund initiation
- Withdrawal request

**Implementation:**
- `idempotency_keys` table stores `(key, user_id, request_hash, response_hash, status_code, expires_at)`
- Middleware short-circuits duplicate requests with the same key+hash
- Same key + different request body = `409 Conflict`
- Tests must assert idempotency under concurrent requests

### IX. Domain Events Fire `DB::afterCommit`

All domain events (`BookingSubmitted`, `PaymentCaptured`, `VendorApprovedForType`, etc.) MUST fire only after the database transaction commits. Listeners that send notifications, dispatch jobs, or call external services run on the queue, not synchronously.

**Pattern:**
```php
DB::transaction(function () use ($input) {
    $booking = Booking::create($input);
    BookingSubmitted::dispatch($booking);  // fires after commit
    return $booking;
});
```

**Forbidden:** firing events inside transactions or synchronously calling external APIs from within a transaction.

### X. Vendor Approval — Two-Step Gate (per ADR-0003 §6.2)

- Profile approval (`vendor_profiles.approval_status='approved'`) must happen **before** any per-type approval
- `ApproveVendorForTypeAction` throws `VendorNotApprovedException` if profile is not approved
- Suspension or rejection of profile auto-revokes all active type approvals via listener on `VendorSuspended` / `VendorRejected` events
- Both decisions are auditable in separate tables (`vendor_profiles.approval_status` + `vendor_approved_product_types`)

### XI. Document Storage — Direct S3 for Typed, MediaLibrary for Galleries (per ADR-0003 §6.5)

| Asset Type | Storage | Reason |
|---|---|---|
| Vendor documents (CR, tax card, IBAN proof) | Direct S3 private with `file_path`/`file_name` columns | Typed entities with review state |
| Settlement proofs (bank transfer screenshots) | Direct S3 private with explicit columns | Audit-required typed entities |
| Service galleries (rental/sale photos) | spatie/laravel-medialibrary on S3 public | Collections, conversions, gallery UI |
| Profile avatars (vendor logo, user pic) | spatie/laravel-medialibrary on S3 public | Single image with conversions |
| Chat media (images, voice) | Firebase Storage | Locked decision — separate auth model |

---

## Locked Tech Stack — Forbidden to Replace Without Constitution Amendment

| Layer | Choice | Forbidden Alternatives |
|---|---|---|
| Framework | Laravel 12 | (locked) |
| Admin | Filament v3 | Backpack, Nova, custom |
| Database | MySQL 8 / MariaDB 11 | PostgreSQL (Phase 2 maybe) |
| Cache/queue | Redis | Memcached, database queue |
| Auth | Sanctum SPA + tokens | Passport, JWT, custom |
| Authorization | spatie/laravel-permission | Bouncer, custom gates |
| Search | Meilisearch via Scout | Algolia, Typesense, ElasticSearch |
| Translatable | spatie/laravel-translatable | astrotomic, custom JSON casts |
| Money | brick/money | brick/math alone, decimal columns |
| State machines | spatie/laravel-model-states | Workflow component, custom |
| Activity log | spatie/laravel-activitylog | telescope alone, custom |
| Excel | maatwebsite/excel | PhpSpreadsheet alone, csv |
| File storage | DigitalOcean Spaces / MinIO via S3 driver | Local for production, FTP |
| Realtime | Laravel Reverb | Pusher (cost), Soketi (alt OK in dev) |
| Push | Firebase Cloud Messaging | OneSignal, custom |
| Payment gateway | Paymob (primary, Phase 1) | Stripe (not in MENA), Tabby/Tamara (Phase 2) |

**Adding a package requires:** entry in `docs/specs/10_Package_List.md` with rationale + version + alternatives considered.

---

## Phase 1 Forbidden Features (per PRD §5.2 + §11)

If any spec-kit artifact suggests these, the command MUST refuse and reference this section:

- ❌ Vendor subscription tiers (silver/gold/bronze/plan-based privileges)
- ❌ Platform-owned package products with separate accounting/commission rules
- ❌ Dispute-resolution engine with automated penalties/compensation
- ❌ Per-category visual card-template system managed by admin
- ❌ Vendor page slider module
- ❌ Vendor QR/barcode direct catalog feature
- ❌ Advanced tax invoicing beyond foundational structural readiness
- ❌ Multi-currency activation (schema is ready; activation is Phase 2)
- ❌ GCC payment gateway adapters (Tabby, Tamara, HyperPay) — Phase 2

---

## Phasing Discipline (per `docs/specs/09_Phasing_Plan_v2.md`)

The 26 micro-phases (≤3 days each) ARE the executable plan. Spec-kit features must align to a phase number.

**Each spec-kit feature spec MUST declare:**
- Phase ID (e.g., `Phase 1.1 — Vendor Onboarding + Approval`)
- PRD coverage (specific FR numbers)
- Tables touched (only those THIS phase creates/modifies)
- ADR required (or already-accepted ADR ID)
- Cut-list (what defers to Phase 1.5 if behind)
- Exit criteria (3-5 checkboxes)

**Each spec-kit plan MUST cite:**
- Specific spec sections (e.g., "Tech Decisions §11", "Schema §3", "PRD FR-19")
- Constitution principles enforced (this file)
- Architecture test that validates the work

---

## Daily Discipline (Enforced by `.claude/hooks/`)

1. One micro-phase per chunk — finish before starting the next
2. Tests written **the same day** as the code (Pest, not deferred)
3. Commit at end of every day (conventional commits: `<type>(<module>): <description>`)
4. End-of-phase deploy to staging — verify deployability per phase
5. `php artisan pint` + PHPStan run on every save (auto via `post-edit-pint.sh`)
6. Spec-guard hook warnings fixed in same session
7. No new packages mid-phase without `10_Package_List.md` entry
8. Friday checkpoint: review week's commits, plan next phase

---

## Spec-Kit Workflow Integration

For every feature using spec-kit:

### `/speckit.specify`

The generated `spec.md` must include:

- Phase ID matching `09_Phasing_Plan_v2.md`
- Functional Requirements traceable to PRD FR numbers (one-to-one or one-to-many)
- User stories for each role (customer / vendor / admin) where applicable
- Acceptance scenarios in Given/When/Then form
- **Constitution Check section** with explicit pass/fail per principle (I-XI)
- Bilingual content notes (which fields are translatable, which messages need i18n)

### `/speckit.clarify`

Required before `/speckit.plan` for any feature touching:
- New module under `app/Modules/`
- Money flows (payment, refund, commission, withdrawal, loyalty)
- State machines (booking lifecycle, fulfillment per type)
- Cross-module domain events

Skip allowed only with explicit "spike/exploratory" tag.

### `/speckit.plan`

The generated `plan.md` must include:

- ADR reference (existing or to-be-created with `/new-module-adr`)
- Constitution Check (which principles apply, how each is satisfied)
- Tables to create/modify (cite `11_DB_Schema.md` line numbers if existing)
- Per-type coverage (rental/sale/digital) explicit if type-aware
- Locale coverage (EN+AR) explicit
- Idempotency keys (which endpoints get them)
- Domain events fired (with `DB::afterCommit` confirmation)
- Architecture tests added/updated
- Cut-list inherited from phase

### `/speckit.tasks`

The generated `tasks.md` must follow this layer order:

1. ADR finalization (if new module)
2. Migrations (in dependency order)
3. Models + relationships + casts + scopes (NO business logic)
4. Form Requests / DTOs (per type if type-aware)
5. Actions (per type if type-aware; cross-type via `match($enum)`)
6. API endpoints + Resources (locale conversion at Resource layer)
7. Filament Resources (per type if type-aware)
8. Listeners + domain events (`DB::afterCommit`)
9. Pest tests — explicit per-type cases for type-aware features

Tasks marked `[P]` for parallel execution must NOT touch the same file as another `[P]` task.

### `/speckit.implement`

Before execution, the agent MUST:

1. Run `git status` — abort if working tree dirty with unrelated work
2. Verify ADR exists with status `Accepted`
3. Verify all packages used are in `10_Package_List.md`
4. Verify no Phase 2 features in scope (run a `/scope-audit` mental check)
5. Stop and ask if any constitution principle would be violated

After execution:

1. Run `php artisan pint`
2. Run `./vendor/bin/phpstan analyse`
3. Run `./vendor/bin/pest --bail` — all green before commit
4. Commit with conventional format
5. Do NOT push (deny rule in `.claude/settings.json`)

### `/speckit.checklist`

Use the InstaParty checklist template at `.specify/templates/checklists/instaparty-feature-checklist-template.md`. The default categories cover:
- Schema discipline
- Module boundaries
- Three product types
- Money discipline
- Bilingual coverage
- Append-only invariants
- Idempotency
- Tests
- Filament admin
- Documentation

---

## Governance

This constitution supersedes all other practices unless explicitly overridden in writing by Ibrahim.

**Amendments:**
- Documented as a new ADR (e.g., `ADR-NNNN-amend-constitution-principle-X.md`)
- Approved before being applied (no silent edits to this file)
- Migration plan if amendment affects existing code
- Version bump per Semantic Versioning rules below

**Versioning rules (semantic):**
- **MAJOR** — backward-incompatible governance/principle removals or redefinitions
- **MINOR** — new principle/section added or materially expanded guidance
- **PATCH** — clarifications, wording, typo fixes, non-semantic refinements

**Compliance verification:**
- All PRs/commits must pass architecture tests (auto-enforced)
- `/speckit.analyze` runs cross-artifact consistency check before `/speckit.implement`
- `/scope-audit` slash command checks for Phase 2 leakage
- Weekly Friday review verifies adherence

**Conflict resolution:**
- If conversation tells you to violate a principle, refuse and cite the principle
- If a spec asks for Phase 2 feature, refuse and cite §"Phase 1 Forbidden Features"
- If unclear, ask Ibrahim — never guess on architectural decisions

---

**Version:** 1.0.0 | **Ratified:** 2026-04-30 | **Last Amended:** 2026-04-30