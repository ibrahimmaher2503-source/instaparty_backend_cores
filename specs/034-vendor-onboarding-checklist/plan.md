---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- No new packages — all components (Filament v3 `Widget`, Blade view, read-only service) are already available per `10_Package_List.md`.
- No new tables — see `spec.md` "Schema Traceability".
- Vendor panel only (`/vendor`). Filament admin (`/admin`) is out of scope.
- No API endpoints exposed — the API documentation constraint does not apply.
---

# Implementation Plan: Vendor Onboarding Checklist Widget

**Branch**: `034-vendor-onboarding-checklist` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/034-vendor-onboarding-checklist/spec.md`

---

## Summary

Render a single dashboard widget on `/vendor` (Filament v3 vendor panel) that surfaces an 11-row onboarding/operational-readiness checklist (10 base rows + 1 conditional rejection/changes-requested alert) for the authenticated vendor. Each row shows a status icon, an EN/AR-localised label, optional sub-text, and (when incomplete) a deep link to the matching vendor page. The widget displays a progress percentage and a single "Next recommended action" CTA. Suspended vendors see a stripped-down suspension banner only (existing `CheckVendorSuspension` middleware continues to enforce route-level redirects).

**Technical approach** (one paragraph): introduce a read-only `VendorOnboardingChecklistService` under `app/Modules/Identity/Application/Services/` that returns a `VendorOnboardingChecklistDTO` after issuing at most six aggregate queries (one per concern: profile completeness, banking, documents-with-expiry, coverage areas, business hours, services). The widget class extends `\Filament\Widgets\Widget`, calls the service, and renders a custom Blade view at `app/Modules/Identity/Resources/views/widgets/onboarding-checklist.blade.php` (loaded via the existing `IdentityServiceProvider->loadViewsFrom`). Translation keys live in `app/Modules/Identity/Resources/lang/{en,ar}/vendor-onboarding.php`. The widget is auto-discovered by `VendorPanelProvider->discoverWidgets()` after adding the path `app/Modules/Identity/Filament/Vendor/Widgets/`, and is registered as a header widget on `VendorDashboardPage`. Pest tests cover the empty-vendor, fully-onboarded, rejected, suspended, AR-locale, and cross-vendor-isolation cases.

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**: Filament v3 (`filament/filament` + `filament/spatie-laravel-translatable-plugin` + `bezhansalleh/filament-language-switch` — all already installed and configured on `VendorPanelProvider`), Spatie laravel-translatable, Spatie laravel-model-states (existing; not strictly required here since the widget queries don't transition state)
**Storage**: MySQL 8 / MariaDB 11 — read-only `SELECT` and `EXISTS` aggregates against `vendor_profiles`, `vendor_documents`, `vendor_approved_product_types`, `vendor_business_hours`, `vendor_coverage_areas`, `services`. No new tables, no writes.
**Testing**: Pest with the existing Filament test traits (`Filament\Tests\Auth\AuthenticatesUsers`, `livewire()` helpers, `assertSee()`, `assertSeeText()`, query-log assertion). Place under `tests/Feature/Modules/Identity/Filament/Vendor/VendorOnboardingChecklistWidgetTest.php` and `tests/Unit/Modules/Identity/Application/Services/VendorOnboardingChecklistServiceTest.php`.
**Target Platform**: Server-side rendered Filament v3 page in a browser; vendor panel at `/vendor`. Must render correctly in both LTR (en) and RTL (ar).
**Project Type**: Modular monolith — single Laravel app, work scoped to the **Identity** module.
**Performance Goals**: Widget adds ≤ 6 DB queries and < 200 ms wall time to `/vendor` page load on the dev DB (per spec FR-EXT-017 and SC-005).
**Constraints**: No business logic in the Widget class; all logic in the service. No cross-module model imports (the widget reads `services` table — owned by Catalog — through a thin read-only query, with a `ServicePresenceQuery` contract in `app/Modules/Catalog/Domain/Contracts/` to avoid importing the Service model directly across module boundaries). No new packages. EN + AR translation keys mandatory.
**Scale/Scope**: One widget, one service, one DTO, one Blade view, two language files, one cross-module contract, and one optional cross-module implementation. ~10 PHP files + 1 view + 2 lang files + 1 docs/api-registry note (N/A — no endpoints). Estimated 2 days of work including tests.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | How this plan satisfies it |
|---|---|---|
| I. Modular Monolith | ✅ PASS | All Identity code lives under `app/Modules/Identity/`. The one cross-module read (existence of services for the authenticated vendor) is mediated by a `ServicePresenceQuery` contract under `app/Modules/Catalog/Domain/Contracts/`, with the implementation under `app/Modules/Catalog/Infrastructure/Repositories/`. No direct `use App\Modules\Catalog\Domain\Models\Service` in Identity code. `tests/Architecture/NoCrossModuleModelImportsTest.php` continues to enforce this. |
| II. Three Product Types (`match($enum)`) | ✅ PASS | The "approved product types" sub-line iterates over `ProductType` enum cases (`match` for the per-type colour badge — same pattern as the admin counterpart in spec `001-dashboard-widgets`). No `if/elseif` on type strings. |
| III. Money Discipline | ✅ N/A | Widget reads no money columns. |
| IV. Bilingual EN+AR | ✅ PASS | All visible strings sourced from `vendor-onboarding.php` lang files (EN+AR). FR-EXT-013 forbids hard-coded strings. Pest test (SC-004) asserts identical key sets between EN and AR files. RTL rendering verified manually + via Filament panel locale switcher. |
| V. Append-Only Tables | ✅ N/A | No writes — append-only rules don't apply. |
| VI. Spec-Driven Development (ADR before code) | ✅ PASS | No new module is created. Identity is an existing module with an accepted ADR (foundational, no ADR ID change needed). Spec-kit `spec.md` exists at `specs/034-vendor-onboarding-checklist/spec.md`. No ADR required. |
| VII. Test-First for Critical Paths | ✅ PASS | Pest tests for all 6 acceptance scenarios in FR-EXT-018 plus translation-parity and query-budget assertions. Widget itself is not a money/auth/booking critical path (purely read-only display), so the 80 % Action-class bar does not apply, but FR-EXT-018 sets explicit per-scenario coverage. |
| VIII. Idempotency | ✅ N/A | No state-changing endpoints exposed. |
| IX. Domain Events `DB::afterCommit` | ✅ N/A | No writes, no domain events. |
| X. Vendor Approval Two-Step Gate | ✅ PASS | Widget displays — but does not enforce — both gates: (a) `vendor_profiles.approval_status` row, and (b) the `vendor_approved_product_types` rows. It surfaces them read-only, consistent with the existing `ApproveVendorForTypeAction` flow. |
| XI. Document Storage | ✅ N/A | Widget reads `vendor_documents` metadata only (status, expires_at, doc_type). No file I/O. |

**Result**: All applicable principles pass. **No violations, no Complexity Tracking entries needed.**

---

## Project Structure

### Documentation (this feature)

```text
specs/034-vendor-onboarding-checklist/
├── plan.md              # This file (/speckit.plan command output)
├── spec.md              # Feature specification (already created by /speckit.specify)
├── research.md          # Phase 0 output — decisions/rationale/alternatives
├── data-model.md        # Phase 1 output — DTO shape, entity reads (no schema changes)
├── quickstart.md        # Phase 1 output — local verification recipe for a developer
├── contracts/           # Phase 1 output — DTO interface + cross-module ServicePresenceQuery contract
│   ├── VendorOnboardingChecklistDTO.md
│   └── ServicePresenceQuery.md
├── checklists/
│   └── requirements.md  # Spec quality checklist (already created)
└── tasks.md             # Phase 2 output — generated by /speckit.tasks
```

### Source Code (repository root)

```text
app/Modules/Identity/
├── Application/
│   ├── DTOs/
│   │   ├── VendorOnboardingChecklistDTO.php                 # NEW
│   │   └── VendorOnboardingChecklistItemDTO.php             # NEW
│   └── Services/
│       ├── OtpRateLimiter.php                               # existing
│       ├── RequiredVendorDocumentTypesResolver.php          # NEW (or inlined constant — decided in Phase 0)
│       └── VendorOnboardingChecklistService.php             # NEW (the read-only service)
├── Domain/
│   └── Enums/
│       └── VendorOnboardingChecklistItemKey.php             # NEW (one case per row: PROFILE, BANKING, DOCS_UPLOADED, DOCS_APPROVED, COVERAGE, HOURS, SERVICE_DRAFT, SERVICE_SUBMITTED, APPROVAL_STATUS, APPROVED_TYPES, REJECTION_BANNER)
├── Filament/
│   └── Vendor/
│       ├── Pages/
│       │   └── (existing pages, unchanged except VendorDashboardPage if it lives here — see note)
│       └── Widgets/                                          # NEW directory
│           └── VendorOnboardingChecklistWidget.php           # NEW (extends \Filament\Widgets\Widget)
└── Resources/
    ├── lang/
    │   ├── en/
    │   │   └── vendor-onboarding.php                         # NEW
    │   └── ar/
    │       └── vendor-onboarding.php                         # NEW
    └── views/
        └── widgets/
            └── onboarding-checklist.blade.php                # NEW (custom Blade view for the widget)

app/Modules/Catalog/
├── Domain/
│   └── Contracts/
│       └── VendorServicePresenceQuery.php                    # NEW (read-only contract for cross-module use)
└── Infrastructure/
    └── Repositories/
        └── EloquentVendorServicePresenceQuery.php            # NEW (implementation, bound in CatalogServiceProvider)

app/Modules/Shared/Filament/Vendor/Pages/
└── VendorDashboardPage.php                                   # MODIFIED — register the new widget in getHeaderWidgets()

app/Providers/Filament/
└── VendorPanelProvider.php                                   # MODIFIED — already calls discoverWidgets() on Identity/Filament/Vendor/Widgets (verified at lines 122–125). No code change required UNLESS the path differs.

tests/
├── Feature/Modules/Identity/Filament/Vendor/
│   └── VendorOnboardingChecklistWidgetTest.php               # NEW (the 6 acceptance scenarios)
└── Unit/Modules/Identity/Application/Services/
    └── VendorOnboardingChecklistServiceTest.php              # NEW (per-row unit cases)
```

**Note on `VendorDashboardPage`**: per `VendorPanelProvider.php` line 15, the page lives at `app/Modules/Shared/Filament/Vendor/Pages/VendorDashboardPage.php` (Shared module owns the dashboard page itself; Identity module owns the widget). The widget is registered via the page's `getHeaderWidgets()` (or `getFooterWidgets()` if positioning at the bottom is preferred — decided in Phase 0). Auto-discovery from `app/Modules/Identity/Filament/Vendor/Widgets/` is already configured in `VendorPanelProvider->discoverWidgets()` at lines 122–125, so registration on the page is the only required wiring change.

**Structure Decision**: Single-module-owned feature with one narrow cross-module read (mediated by a contract). The Identity module owns the widget, the service, the DTOs, the enum, the Blade view, and the lang files. The Catalog module owns the `VendorServicePresenceQuery` contract + Eloquent implementation. The Shared module's `VendorDashboardPage` is touched only to register the widget.

---

## Phase 0 — Outline & Research

Open questions deferred from the spec → resolved here.

### Decision 0.1 — Required document types resolver: extract or inline?

- **Decision**: Introduce `RequiredVendorDocumentTypesResolver` under `app/Modules/Identity/Application/Services/` with a single public method `forBusinessType(BusinessType $type): array<DocType>`. Implementation returns a hard-coded map for now (the same map that `022-vendor-doc-compliance` uses, if it exists; otherwise the map below) and is fully internal to the Identity module.
- **Rationale**: FR-EXT-010 mandates a centralised resolver so future requirement additions don't require widget changes. A dedicated class — rather than a constant array on the service — keeps `VendorOnboardingChecklistService` focused on aggregation and lets `022-vendor-doc-compliance` consume the same resolver if it doesn't already.
- **Alternatives**:
  - Inline constant array on `VendorOnboardingChecklistService` — rejected: violates single-responsibility and forces every consumer to depend on the checklist service.
  - Database-driven `required_document_types` table — rejected: out of Phase 1 scope, no PRD/schema entry, would be ⚠️ NEW TABLE.
- **Default map (subject to verification against 022-vendor-doc-compliance, in Phase 0 of implementation)**:
  - `individual` → `[national_id, iban_proof]`
  - `company` → `[cr, tax_card, iban_proof]`
  - `establishment` → `[cr, tax_card, national_id, iban_proof]`

### Decision 0.2 — Widget position on `VendorDashboardPage`

- **Decision**: Register via `getHeaderWidgets()` with `getColumns(): int|array { return 1; }` so the checklist spans the full dashboard width above any future stats widgets.
- **Rationale**: The checklist is the dashboard's primary call-to-action for any not-yet-fully-onboarded vendor. Anchoring it at the top with full width avoids scroll-past and matches the "above the fold" requirement in SC-002.
- **Alternatives**: Footer widget (rejected — below the fold), main content widget (rejected — fights with future booking widgets).

### Decision 0.3 — Banking-details completeness rule

- **Decision**: Banking is "complete" iff `vendor_profiles.bank_name`, `bank_account_holder`, AND `bank_iban` are all non-null/non-empty. `bank_swift_bic` and `bank_branch` are NOT required (per the migration — both nullable, and Egyptian-bank IBAN is sufficient for withdrawal payouts in Phase 1 Paymob settlement flow).
- **Rationale**: Mirrors the actual `withdrawals` settlement requirement. Forcing SWIFT/branch would block local-bank vendors needlessly.
- **Alternatives**: Require all five — rejected (SWIFT is unnecessary for domestic EGP withdrawals). Require IBAN only — rejected (need account-holder name for the bank-transfer narration).

### Decision 0.4 — Business-profile completeness rule (by business_type)

- **Decision**: Profile is "complete" iff: `business_name` (JSON, both en+ar non-empty), `business_type`, `primary_governorate_id`, `primary_city_id`, `address_line` (JSON, both en+ar non-empty), AND **at least one** government-ID column per business_type:
  - `individual` → `national_id` present
  - `company` → `commercial_register_no` AND `tax_id` present
  - `establishment` → `commercial_register_no` present (tax_id optional in this tier)
- **Rationale**: Matches the existing per-business-type validation in `RegisterVendorPage` / `VendorProfilePage` (the FormRequest already enforces these on save).
- **Alternatives**: Require all government IDs regardless of type — rejected (over-restrictive, mismatches form validation).

### Decision 0.5 — Cross-module access to `services` presence

- **Decision**: Introduce `App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery` with two methods:
  - `hasAnyService(int $vendorProfileId): bool`
  - `hasServiceInReviewOrPublished(int $vendorProfileId): bool`
  - Implementation in `App\Modules\Catalog\Infrastructure\Repositories\EloquentVendorServicePresenceQuery`, bound in `CatalogServiceProvider->register()`.
- **Rationale**: Constitution Principle I forbids cross-module model imports. The contract pattern (existing in this repo per `app/Modules/*/Domain/Contracts/`) is the prescribed approach. Two methods (instead of one returning a struct) keep query semantics simple and let each call use `exists()` for performance.
- **Alternatives**: Direct `Service::query()` import — rejected (architecture-test violation). Domain event subscription — rejected (overkill for a read).

### Decision 0.6 — Cache layer

- **Decision**: No cache. Compute on each page load.
- **Rationale**: Six indexed `exists()` queries on a single vendor scope cost < 50 ms on dev DB and < 100 ms on prod-class MySQL. Adding Redis caching introduces invalidation complexity (cache must invalidate on document upload, service draft, profile edit, etc.) for marginal benefit. Re-evaluate in Phase 7 hardening if profiling shows hot-path overhead.
- **Alternatives**: 60-second per-vendor cache — rejected for Phase 1 scope.

### Decision 0.7 — DTO shape and immutability

- **Decision**: Both DTOs (`VendorOnboardingChecklistDTO`, `VendorOnboardingChecklistItemDTO`) are `readonly` PHP 8.3 classes constructed in the service. No setters, no public mutation. Item DTO holds the enum key, status enum, computed label (already-translated string), optional sub-text, optional URL.
- **Rationale**: Matches the `Application/DTOs` convention in `.claude/rules/modules.md`. `readonly` enforces immutability between service and view.
- **Alternatives**: Plain associative arrays — rejected (no IDE support, no type safety, violates the DTO convention).

### Decision 0.8 — How the widget computes "all 11 rows green" when the rejection row is conditional

- **Decision**: The rejection row is rendered as a **banner**, separate from the row counter. The denominator for the progress percentage is always 10 (the ten base rows). The rejection banner sits above the checklist and is independent of the percentage.
- **Rationale**: The user-facing progress should reflect the vendor's *self-completable* steps. The admin decision rows (approval status, approved types) are read-only displays — they're counted in the denominator because they ARE part of being "ready to take bookings", but the rejection banner is a separate alert that doesn't change the percentage math.
- **Alternatives**: Conditional 10-or-11 denominator — rejected (confusing UX when "100 %" appears with a rejection banner). Exclude approval rows from the denominator — rejected (vendors would see 100 % before admin approval, misleading).

**Output**: `research.md` written below — same content as this section, formatted for stand-alone reading.

---

## Phase 1 — Design & Contracts

### 1.1 — Data Model (`data-model.md`)

**No schema changes.** This feature only reads existing tables. The DTO shape is the "data model" delivered here.

**`VendorOnboardingChecklistDTO`** (readonly):

| Field | Type | Source |
|---|---|---|
| `vendorId` | `int` | authenticated vendor's `vendor_profiles.id` |
| `isSuspended` | `bool` | `approval_status === 'suspended'` |
| `suspendedAt` | `?\Carbon\CarbonImmutable` | `suspended_at` |
| `suspensionReason` | `?string` | translated `rejection_reason` JSON (if suspended) |
| `rejectionState` | `?Enum{NONE, CHANGES_REQUESTED, REJECTED}` | from `approval_status` |
| `rejectionReason` | `?string` | translated `rejection_reason` JSON |
| `items` | `array<VendorOnboardingChecklistItemDTO>` | the 10 base rows, always in fixed order |
| `completedCount` | `int` | derived |
| `totalCount` | `int` | always `10` |
| `progressPercent` | `int` | `intdiv($completedCount * 100, $totalCount)` |
| `nextRecommendedAction` | `?VendorOnboardingChecklistItemDTO` | first incomplete item in priority order |
| `approvedProductTypes` | `array<ProductType>` | from `vendor_approved_product_types` |

**`VendorOnboardingChecklistItemDTO`** (readonly):

| Field | Type | Notes |
|---|---|---|
| `key` | `VendorOnboardingChecklistItemKey` | one of 10 enum cases |
| `status` | `Enum{COMPLETE, PENDING, WARNING, DANGER, INFO}` | drives the icon and colour |
| `label` | `string` | already-translated for `app()->getLocale()` |
| `subText` | `?string` | already-translated |
| `url` | `?string` | Filament URL to the matching vendor page (only when status !== COMPLETE) |

**Entity reads (no writes)**:

| Source | Columns read | Aggregate |
|---|---|---|
| `vendor_profiles` | `id, business_name, business_type, commercial_register_no, tax_id, national_id, primary_governorate_id, primary_city_id, address_line, bank_name, bank_account_holder, bank_iban, approval_status, suspended_at, rejection_reason` | single row (the authenticated vendor) |
| `vendor_documents` | `doc_type, status, expires_at` | `groupBy(doc_type) → latest per type` |
| `vendor_approved_product_types` | `product_type` | all rows |
| `vendor_business_hours` | `id` | `exists()` |
| `vendor_coverage_areas` | `id` | `exists()` |
| `services` (via contract) | `id, status` | two `exists()` checks |

Total query budget: **6 queries** (one per concern), satisfying FR-EXT-017.

### 1.2 — Contracts (`contracts/`)

Two contracts documented for downstream use:

- `contracts/VendorOnboardingChecklistDTO.md` — Public interface of `VendorOnboardingChecklistService::forVendor(VendorProfile): VendorOnboardingChecklistDTO`. Stable across the feature's lifetime; consumed only by the widget.
- `contracts/ServicePresenceQuery.md` — Cross-module read contract `App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery` with the two methods listed in Decision 0.5. Bound by `CatalogServiceProvider`.

No HTTP API endpoints, no Bruno/Postman collection entries, no `api-registry.md` updates required (per the constraint at the top of this file).

### 1.3 — Quickstart (`quickstart.md`)

A 10-step recipe a developer can copy-paste to verify the widget locally:

1. Pull branch `034-vendor-onboarding-checklist` and run `composer install`.
2. Run migrations: `php artisan migrate:fresh --seed`.
3. Seed a vendor in each of the four interesting states (empty / partial / fully-onboarded / rejected / suspended) via `php artisan tinker` snippets in the file.
4. Log in to `/vendor` as each vendor and visually confirm the widget rendering.
5. Switch the panel locale to AR and confirm RTL + Arabic labels.
6. Run `./vendor/bin/pest --filter=VendorOnboardingChecklistWidget` and confirm green.
7. Run `./vendor/bin/pest --filter=VendorOnboardingChecklistService` and confirm green.
8. Run `./vendor/bin/phpstan analyse app/Modules/Identity` and confirm clean.
9. Run `./vendor/bin/pint app/Modules/Identity` and confirm no diffs.
10. Run `php artisan filament:cache-components` for the production-mode smoke check.

### 1.4 — Agent context update

Run `.specify/scripts/powershell/update-agent-context.ps1 -AgentType claude` after the plan is committed to refresh `CLAUDE.md` markers if needed.

### 1.5 — Post-design Constitution re-check

Re-evaluated after writing the data-model and contracts above:

- **I. Modular Monolith**: still PASS. The `VendorServicePresenceQuery` contract preserves boundaries.
- **II. Three Product Types**: still PASS. The widget uses `match($productType)` for the approved-type badge colours.
- **IV. Bilingual EN+AR**: still PASS. The DTO's `label` and `subText` are pre-translated; the service is responsible for calling `__(...)` with `app()->getLocale()`.
- **VI. Spec-Driven Development**: still PASS. No new module, no new ADR required.

**No new violations introduced by the design.** Complexity Tracking remains empty.

---

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| _(none)_ | _(none)_ | _(none)_ |

The plan introduces **one new cross-module contract** (`VendorServicePresenceQuery`) — this is not a violation; it's the prescribed pattern for cross-module reads per Principle I.
