# Phase 0 Research: Vendor Onboarding Checklist Widget

**Feature**: `034-vendor-onboarding-checklist`
**Date**: 2026-05-16

This document resolves the open questions and design choices that surfaced during planning. Each decision states the chosen approach, the rationale, and the alternatives rejected.

---

## Decision 0.1 — Required document types resolver: extract or inline?

- **Decision**: Introduce `RequiredVendorDocumentTypesResolver` under `app/Modules/Identity/Application/Services/` with a single public method `forBusinessType(BusinessType $type): array<DocType>`. Implementation returns a hard-coded map for now (verify against `022-vendor-doc-compliance` first — reuse if it already provides one) and is fully internal to the Identity module.
- **Rationale**: FR-EXT-010 mandates a centralised resolver so future requirement additions don't require widget changes. A dedicated class — rather than a constant array on `VendorOnboardingChecklistService` — keeps that service focused on aggregation and lets `022-vendor-doc-compliance` reuse the resolver for its document-expiry reminders.
- **Alternatives**:
  - Inline constant array on `VendorOnboardingChecklistService` — rejected: violates single-responsibility, forces every consumer to depend on the checklist service.
  - Database-driven `required_document_types` table — rejected: out of Phase 1 scope, no PRD/schema entry, would be ⚠️ NEW TABLE.
- **Default map** (validate against the `vendor_documents.doc_type` enum: `cr`, `tax_card`, `national_id`, `iban_proof`, `other`):
  - `individual` → `[national_id, iban_proof]`
  - `company` → `[cr, tax_card, iban_proof]`
  - `establishment` → `[cr, tax_card, national_id, iban_proof]`

## Decision 0.2 — Widget position on `VendorDashboardPage`

- **Decision**: Register the widget via `getHeaderWidgets()` on `app/Modules/Shared/Filament/Vendor/Pages/VendorDashboardPage.php`, with `getColumns(): int|array { return 1; }` so the checklist spans the full dashboard width above any future stats widgets.
- **Rationale**: The checklist is the dashboard's primary call-to-action for any not-yet-fully-onboarded vendor. Anchoring it at the top with full width avoids scroll-past and matches the "above the fold" requirement in SC-002.
- **Alternatives**: footer widget (rejected — below the fold), main content widget (rejected — fights with future booking widgets).

## Decision 0.3 — Banking-details completeness rule

- **Decision**: Banking is "complete" iff `vendor_profiles.bank_name`, `bank_account_holder`, AND `bank_iban` are all non-null/non-empty. `bank_swift_bic` and `bank_branch` are NOT required.
- **Rationale**: Mirrors the actual `withdrawals` settlement requirement for Phase 1 Paymob payouts. Forcing SWIFT/branch would block local-bank vendors needlessly.
- **Alternatives**: Require all five — rejected (SWIFT unnecessary for domestic EGP withdrawals). Require IBAN only — rejected (need account-holder name for bank-transfer narration).

## Decision 0.4 — Business-profile completeness rule (by business_type)

- **Decision**: Profile is "complete" iff `business_name` (JSON, both en+ar non-empty), `business_type`, `primary_governorate_id`, `primary_city_id`, `address_line` (JSON, both en+ar non-empty), AND at least one government-ID column appropriate to the business_type:
  - `individual` → `national_id` present
  - `company` → `commercial_register_no` AND `tax_id` present
  - `establishment` → `commercial_register_no` present (tax_id optional)
- **Rationale**: Matches the per-business-type validation in `RegisterVendorPage` / `VendorProfilePage`.
- **Alternatives**: Require all government IDs regardless of type — rejected (over-restrictive, mismatches form validation).

## Decision 0.5 — Cross-module access to `services` presence

- **Decision**: Introduce `App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery` with two methods:
  - `hasAnyService(int $vendorProfileId): bool`
  - `hasServiceInReviewOrPublished(int $vendorProfileId): bool`
  - Implementation: `App\Modules\Catalog\Infrastructure\Repositories\EloquentVendorServicePresenceQuery`, bound in `CatalogServiceProvider->register()`.
- **Rationale**: Constitution Principle I forbids cross-module model imports. The contract pattern (existing in this repo per `app/Modules/*/Domain/Contracts/`) is the prescribed approach. Two methods keep query semantics simple and let each call use `exists()` for performance.
- **Alternatives**: Direct `Service::query()` import in Identity (rejected — architecture-test violation). Domain event subscription (rejected — overkill for a synchronous read).

## Decision 0.6 — Cache layer

- **Decision**: No cache. Compute on each page load.
- **Rationale**: Six indexed `exists()` queries on a single vendor scope cost well under 100 ms on prod-class MySQL. Adding Redis caching introduces invalidation complexity (cache must invalidate on document upload, service draft, profile edit, etc.) for marginal benefit. Re-evaluate in Phase 7 hardening if profiling shows hot-path overhead.
- **Alternatives**: 60-second per-vendor cache — rejected for Phase 1 scope.

## Decision 0.7 — DTO shape and immutability

- **Decision**: Both DTOs (`VendorOnboardingChecklistDTO`, `VendorOnboardingChecklistItemDTO`) are `readonly` PHP 8.3 classes constructed in the service. Item DTO holds the enum key, status enum, computed label (already-translated string), optional sub-text, optional URL.
- **Rationale**: Matches the `Application/DTOs` convention in `.claude/rules/modules.md`. `readonly` enforces immutability between service and view.
- **Alternatives**: Plain associative arrays — rejected (no IDE support, no type safety, violates the DTO convention).

## Decision 0.8 — Progress denominator with conditional rejection row

- **Decision**: The rejection row renders as a **banner** outside the row counter. The progress denominator is always 10 (the ten base rows). The rejection banner sits above the checklist and is independent of the percentage.
- **Rationale**: User-facing progress should reflect the vendor's self-completable steps plus admin decision visibility. The admin rows (approval status, approved types) are counted in the denominator because they are part of "ready to take bookings", but the rejection alert is a separate banner that doesn't change the math.
- **Alternatives**: Conditional 10-or-11 denominator (rejected — confusing UX when "100 %" appears beside a rejection banner). Exclude approval rows from denominator (rejected — vendors would see 100 % before admin approval, misleading).

---

## Open items deferred to implementation

- Confirm whether `022-vendor-doc-compliance` already provides a `RequiredVendorDocumentTypesResolver` (or similar). If so, reuse instead of creating a new class. **Verify in the first task of `/speckit.tasks`**.
- Confirm `VendorDashboardPage` lives at `app/Modules/Shared/Filament/Vendor/Pages/VendorDashboardPage.php` (per `VendorPanelProvider.php` line 15). If the file is missing, scaffold it; if it already has `getHeaderWidgets()`, append the new widget to the array.
