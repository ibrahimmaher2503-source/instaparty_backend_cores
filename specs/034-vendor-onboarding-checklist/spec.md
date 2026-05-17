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
- FR traceability: All requirements below are local FR-EXT-NNN entries. ⚠️ BACKFILL NEEDED: add vendor-portal onboarding checklist requirements to `docs/specs/01_PRD.md` §7.2 (Vendor Journey) — no FR currently covers an in-portal onboarding-progress widget.
- Schema traceability: All tables already exist in `docs/specs/11_DB_Schema.md` (see "Schema Traceability" table at the bottom). No new tables introduced.
- Phase alignment: Belongs to Phase 1 (Identity & Vendor onboarding). Treat as a Phase 1.x widget-completeness deliverable — analogous to spec `001-dashboard-widgets` but on the **vendor** panel. ⚠️ PHASE BACKFILL NEEDED: `09_Phasing_Plan.md` Phase 1 mentions vendor onboarding flow but does not enumerate the dashboard checklist widget — add it to the Phase 1 deliverables list.
- No new packages required (Filament v3 `Widget` + Blade view + per-module read-only query service — already covered by `10_Package_List.md`).
- Vendor panel only (`/vendor`). Not on `/admin`.

API DOCUMENTATION CONSTRAINT:
- This feature exposes **no HTTP API endpoints** — it is a Filament widget rendering server-side from authenticated session data. Scribe / Bruno / api-registry entries are therefore not applicable.
---

# Feature Specification: Vendor Onboarding Checklist Widget

**Feature Branch**: `034-vendor-onboarding-checklist`
**Created**: 2026-05-16
**Status**: Draft
**Phase**: Phase 1 (Identity & Vendor onboarding) — vendor-portal dashboard completeness
**Related Specs**: `002-phase-1-identity-vendor-onboarding`, `022-vendor-doc-compliance`, `001-dashboard-widgets` (admin counterpart)
**Input**: User description: "Build VendorOnboardingChecklistWidget for /vendor dashboard. Give vendors a clear checklist from registration to admin approval to operational readiness."

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Newly-registered vendor sees the path to approval (Priority: P1)

A vendor who just completed registration logs into `/vendor` for the first time. They see a single dashboard widget that lists every step required between "account created" and "ready to take bookings", each with an obvious status icon (done / pending / blocked) and a one-click link to the page where they can complete that step. A progress percentage and a single "Next recommended action" call-to-action remove all ambiguity about what they should do next.

**Why this priority**: This is the **first thing a vendor sees** after registering. Without it, vendors stall — they don't know whether their documents are still needed, whether the admin is reviewing them, or which service to create first. Vendor activation rate (registered → first published service) is the primary funnel metric Phase 1 must move.

**Independent Test**: Seed a brand-new vendor (only `vendor_profiles.business_name` set, `approval_status=pending`, no documents, no coverage areas, no business hours, no services, no banking details). Log in as that vendor and load `/vendor`. Confirm the widget renders the full checklist, only the "Business profile started" row is complete, progress shows ~9 % (one of eleven), and the "Next recommended action" button reads "Complete business profile" and links to `VendorProfilePage`.

**Acceptance Scenarios**:

1. **Given** a vendor whose `vendor_profiles` row has all required business-profile columns populated (business_name, business_type, commercial_register_no OR tax_id OR national_id depending on business_type, primary_governorate_id, primary_city_id, address_line), **When** the vendor loads `/vendor`, **Then** the "Business profile completed" row shows a success icon and the next incomplete row becomes the "Next recommended action".
2. **Given** a vendor with `bank_name`, `bank_account_holder`, and `bank_iban` all populated, **When** the dashboard loads, **Then** the "Banking details completed" row shows success.
3. **Given** a vendor with at least one `vendor_documents` row per document type required for their `business_type`, **When** the dashboard loads, **Then** the "Required documents uploaded" row shows success; if any required type is missing, the row is pending and links to `VendorDocumentsPage`.
4. **Given** all uploaded vendor documents have `review_status='approved'`, **When** the dashboard loads, **Then** the "Required documents approved" row shows success; if any are `pending`, the row shows an info icon (not error); if any are `rejected`, the row shows a danger icon with the rejection reason summarised.
5. **Given** at least one `vendor_coverage_areas` row exists for the vendor, **When** the dashboard loads, **Then** the "Coverage area added" row shows success; otherwise the row is pending and links to `VendorCoverageAreasPage`.
6. **Given** `vendor_business_hours` rows exist that cover at least one weekday, **When** the dashboard loads, **Then** the "Business hours configured" row shows success.
7. **Given** at least one `services` row exists for the vendor with any non-archived status, **When** the dashboard loads, **Then** the "At least one service drafted" row shows success.
8. **Given** at least one `services` row for the vendor has `status='pending_review'` OR `status='published'`, **When** the dashboard loads, **Then** the "At least one service submitted for review" row shows success.
9. **Given** the vendor's `vendor_profiles.approval_status='pending'`, **When** the dashboard loads, **Then** the "Admin approval status" row shows an info icon labelled "Awaiting admin review" with no link.
10. **Given** `vendor_profiles.approval_status='approved'`, **When** the dashboard loads, **Then** the "Admin approval status" row shows success and a sub-line lists the approved product types (from `vendor_approved_product_types`).

---

### User Story 2 — Vendor with rejected / changes-requested status sees the blocker first (Priority: P1)

A vendor previously submitted their profile for review and the admin returned a "changes requested" or "rejected" decision with a reason. When the vendor opens `/vendor`, the widget surfaces that decision as the **top-most** alert row, in red, with the human-readable rejection reason in the vendor's locale (EN or AR), and links to the page where the vendor can amend their profile or documents.

**Why this priority**: Without explicit, in-portal surfacing of the admin's decision, vendors will not see the rejection reason (it lives buried in a sub-page) and they will think the platform is broken. This is the single biggest cause of vendor churn during onboarding.

**Independent Test**: Seed a vendor with `approval_status='rejected'` and `rejection_reason={"en":"Tax ID does not match commercial register","ar":"الرقم الضريبي لا يطابق السجل التجاري"}`. Load `/vendor`. Verify a danger banner appears at the top of the widget with the EN string (when locale=en) or the AR string (when locale=ar), and a button "Edit profile and resubmit" links to `VendorProfilePage`.

**Acceptance Scenarios**:

1. **Given** `approval_status='rejected'` with a populated `rejection_reason`, **When** the vendor loads `/vendor` in EN, **Then** the widget renders a danger-coloured alert at the top with the EN rejection reason and a link to `VendorProfilePage`.
2. **Given** the same vendor switches the panel locale to AR, **When** the dashboard re-renders, **Then** the alert shows the AR rejection reason.
3. **Given** `approval_status='changes_requested'` (added via migration `2026_05_04_000003_add_changes_requested_to_vendor_profiles_approval_status.php`), **When** the dashboard loads, **Then** the widget shows a warning-coloured alert with the admin-requested change reason and a link to the relevant page.

---

### User Story 3 — Suspended vendor sees suspension reason and is not misled by the checklist (Priority: P1)

A vendor whose account has been suspended (`approval_status='suspended'`) logs into `/vendor`. The suspension is enforced by `CheckVendorSuspension` middleware, which redirects to `AccountSuspendedPage`. The onboarding widget itself must therefore never render in a way that suggests the suspension can be self-resolved — but on the suspended page (or wherever the widget *is* rendered for a suspended account), the widget must clearly show the suspension state with the recorded reason.

**Why this priority**: A suspended vendor seeing a 90 %-complete green checklist would be deeply misleading. Either the widget must not render at all when suspended, or it must show a single suspension banner that supersedes all other rows.

**Independent Test**: Seed a vendor with `approval_status='suspended'`, `suspended_at=now()`, `rejection_reason` populated, all other onboarding steps complete. Authenticate. Verify either (a) the `CheckVendorSuspension` middleware redirects away from the dashboard, OR (b) the widget renders only the suspension banner and hides all checklist rows.

**Acceptance Scenarios**:

1. **Given** `approval_status='suspended'`, **When** the vendor authenticates, **Then** existing `CheckVendorSuspension` middleware behaviour is unchanged (vendor is redirected to `AccountSuspendedPage`).
2. **Given** the same vendor reaches a route where the widget is rendered, **When** the widget computes its state, **Then** the widget returns its `suspended` view variant — only the suspension banner, suspension date, and the recorded reason are shown.
3. **Given** the suspension is lifted (`approval_status` returns to `approved`), **When** the vendor next loads `/vendor`, **Then** the widget reverts to its normal checklist view.

---

### User Story 4 — Arabic vendor sees fully-localised checklist (Priority: P2)

A vendor whose preferred locale is Arabic loads `/vendor`. Every row label, every status pill, the progress label ("3 of 11 complete" → "اكتمل 3 من 11"), the rejection reason, and the "Next recommended action" CTA all render in Arabic. RTL rendering is correct (status icon on the right, link text right-aligned).

**Why this priority**: AR support is mandatory across the platform (see `docs/specs/04_Bilingual_Spec.md`). A widget that hard-codes EN strings is a Phase 1 acceptance blocker. P2 rather than P1 only because broken AR labels are detectable visually and have a workaround (switch to EN).

**Independent Test**: Load `/vendor` with the Filament locale switched to AR. Inspect each checklist row label, the progress label, and any inline help text — none should equal a raw translation key (e.g., `vendor-onboarding.row.profile`). All translatable JSON fields (rejection_reason, etc.) render their `ar` values.

**Acceptance Scenarios**:

1. **Given** locale=ar, **When** the widget renders, **Then** every label is an Arabic string sourced from `app/Modules/Identity/Resources/lang/ar/vendor-onboarding.php`.
2. **Given** locale=en, **When** the widget renders, **Then** every label is an English string sourced from `app/Modules/Identity/Resources/lang/en/vendor-onboarding.php`.
3. **Given** a translation key is missing in one locale, **When** the widget renders, **Then** Laravel's translator falls back to the configured fallback locale rather than displaying the raw key.

---

### Edge Cases

- A vendor was approved long ago and has many services already. The widget still renders, but all rows are green and the "Next recommended action" button is replaced by a small "Onboarding complete" confirmation chip (not a button).
- `vendor_approved_product_types` is empty even though the profile is approved (admin approved without specifying any types). The "Approved product types" sub-line shows a warning chip "No product types approved yet — contact support".
- `vendor_documents` schema includes expiry tracking (per migration `2026_05_04_000002_alter_vendor_documents_add_expiry_columns.php`). If a previously-approved document is now expired, the "Required documents approved" row reverts to a warning state with the expiring document name and an "Upload new copy" link.
- The Phase 1 schema may evolve to add new required document types per business_type. The widget MUST read the "required document types per business_type" map from a centralised read-only service so adding a new requirement does not require touching widget code.
- The vendor's stored `users.locale` does not match the active panel locale. The widget uses `app()->getLocale()` (the Filament-active locale), not the stored user locale, so the displayed checklist always follows the language switcher.
- Empty/missing `vendor_business_hours` for some weekdays must NOT fail the "Business hours configured" check — the check is "at least one weekday with hours" (vendors who only operate Sat + Sun must be allowed).
- The widget MUST NOT issue more than one DB query per checklist concern. Aggregate via a single service call that uses `withCount` / `exists()` semantics to keep dashboard load fast.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-001**: System MUST render a `VendorOnboardingChecklistWidget` on the vendor dashboard (`/vendor`) for every authenticated vendor whose `vendor_profiles` row is not in `suspended` status. ⚠️ BACKFILL NEEDED: add to 01_PRD.md §7.2 Vendor Journey FRs.
- **FR-EXT-002**: Widget MUST render exactly **11 checklist items** in fixed order: (1) Business profile completed, (2) Banking details completed, (3) Required documents uploaded, (4) Required documents approved, (5) Coverage area added, (6) Business hours configured, (7) Service drafted, (8) Service submitted for review, (9) Admin approval status, (10) Approved product types listed, (11) Changes-requested / rejection reason (rendered only when applicable; otherwise the row collapses out and the total adjusts to 10).
- **FR-EXT-003**: Each checklist item MUST display: a status icon (success / warning / danger / info / pending), a localised label (EN+AR), an optional one-line sub-text (e.g., the approved product types list), and a "Go to step" link to the matching vendor page when the item is incomplete.
- **FR-EXT-004**: Widget MUST display a progress percentage = `completed_items / total_items` rounded down to the nearest whole percent.
- **FR-EXT-005**: Widget MUST display a single "Next recommended action" button. The recommended action is the **first incomplete item in priority order**: rejection / changes-requested alert → business profile → documents (upload before approval) → banking details → coverage areas → business hours → service drafted → service submitted. Approval-status and approved-types rows are read-only and never become the recommended action.
- **FR-EXT-006**: When `vendor_profiles.approval_status='rejected'` or `='changes_requested'`, the widget MUST display the localised `rejection_reason` as a top-anchored banner above the checklist, in danger or warning colour respectively.
- **FR-EXT-007**: When `vendor_profiles.approval_status='approved'`, the widget MUST display the vendor's approved product types (rental / sale / digital) sourced from `vendor_approved_product_types` as a sub-line on the "Approved product types" row.
- **FR-EXT-008**: When `vendor_profiles.approval_status='suspended'`, the widget MUST render a suspended view variant that hides all checklist rows and displays only a suspension banner with `suspended_at` timestamp and the recorded suspension reason. The dashboard route itself remains protected by the existing `CheckVendorSuspension` middleware — the widget MUST NOT duplicate that authorisation logic.
- **FR-EXT-009**: All checklist completion logic MUST live in a read-only service `App\Modules\Identity\Application\Services\VendorOnboardingChecklistService` with a single public method `forVendor(VendorProfile $vendor): VendorOnboardingChecklistDTO`. The widget calls the service and renders only — **no business logic in the widget class**.
- **FR-EXT-010**: The service MUST resolve "required document types per `business_type`" from a centralised resolver (existing or new under `Identity/Application/Services/`) so that adding/removing required types does not require widget changes.
- **FR-EXT-011**: Document completion check MUST be expiry-aware: a document with `expires_at <= now()` does NOT satisfy the "Required documents approved" row.
- **FR-EXT-012**: All vendor-data queries used by the widget MUST be scoped by the authenticated vendor's `vendor_profile_id`. Cross-vendor data leakage is forbidden — verified by a Pest test that authenticates Vendor A and asserts only A's progress is computed.
- **FR-EXT-013**: All checklist row labels, status pill text, the progress label, the "Next recommended action" button label, and the suspension / rejection banner copy MUST be sourced from `app/Modules/Identity/Resources/lang/{en,ar}/vendor-onboarding.php`. No hard-coded strings in the widget or Blade view.
- **FR-EXT-014**: The widget MUST extend Filament v3's `\Filament\Widgets\Widget` base class with a custom Blade view — NOT `StatsOverviewWidget` (which does not support the row-with-link layout this widget needs).
- **FR-EXT-015**: The widget MUST be registered for discovery via `VendorPanelProvider->discoverWidgets()` in `app/Modules/Identity/Filament/Vendor/Widgets/`; the `VendorDashboardPage` MUST include it in its `getHeaderWidgets()` (or equivalent) array with deterministic sort order placing it at the top of the dashboard.
- **FR-EXT-016**: When a vendor is fully onboarded (all base rows green), the "Next recommended action" button MUST be replaced by a static "Onboarding complete" chip (no button). The widget continues to render — it does not auto-hide — so vendors can self-audit completion status at any time.
- **FR-EXT-017**: Page load impact: the widget's DB query budget MUST be ≤ 6 queries (one per concern) and total wall-time MUST be < 200 ms on a seeded dev DB. Verified by a Pest assertion (`->assertQueryCountLessThan(6)` or query-log inspection).
- **FR-EXT-018**: Pest test coverage MUST include at least: (a) empty vendor → only profile row complete; (b) fully-onboarded vendor → all complete + no CTA button; (c) rejected vendor → red banner with localised reason; (d) suspended vendor → suspended view variant; (e) AR locale → labels in Arabic; (f) cross-vendor leakage — Vendor A's session never sees Vendor B's data.

### Key Entities *(include if feature involves data)*

- **VendorProfile** (`vendor_profiles`): drives identity, banking, approval status, suspension state, and (via `business_type`) the set of required document types.
- **VendorDocument** (`vendor_documents`): per-vendor document rows. Read for upload-presence and approval-state checks. `expires_at` (added in `2026_05_04_000002_*`) participates in the approval check.
- **VendorApprovedProductType** (`vendor_approved_product_types`): one row per approved product type (`rental` / `sale` / `digital`). Read to populate the "Approved product types" sub-line.
- **VendorBusinessHour** (`vendor_business_hours`): existence of at least one row satisfies the "Business hours configured" row.
- **VendorCoverageArea** (`vendor_coverage_areas`): existence of at least one row satisfies the "Coverage area added" row.
- **Service** (`services`): existence of at least one row for the vendor satisfies "Service drafted"; existence of one with `status IN ('pending_review','published')` satisfies "Service submitted for review".

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A newly-registered vendor lands on `/vendor` and within 5 seconds can identify, by looking at the widget, what their next action is — verified by usability walkthrough and by the presence of a single, visually-dominant "Next recommended action" button.
- **SC-002**: A vendor whose admin decision is "rejected" or "changes requested" sees the rejection reason **above the fold** on `/vendor` 100 % of the time — verified by Pest snapshot of the rendered widget DOM and by manual locale-switching review.
- **SC-003**: Each checklist row has its own Pest test asserting (a) the row renders complete when the underlying data exists and (b) the row renders incomplete and links to the right vendor page when the data is missing.
- **SC-004**: 100 % of EN-locale labels have a matching AR translation in `vendor-onboarding.php` — verified by a Pest test that loads both lang files and asserts identical key sets.
- **SC-005**: Widget load adds < 200 ms to the `/vendor` dashboard page load time on a seeded dev DB; total queries added ≤ 6 — verified by a Pest performance assertion.
- **SC-006**: Cross-vendor data leakage rate is 0 % — verified by an isolation Pest test (Vendor A authenticated, no Vendor B data exposed in the widget DTO).
- **SC-007**: For a vendor on the `suspended` state, the widget renders the suspended variant in 100 % of cases — no checklist rows leak through.
- **SC-008**: After Phase 1 launch, the vendor-activation funnel (registered → first service published) becomes **observable** because the widget exposes each step explicitly. Operationally measurable target: ≥ 60 % of newly-registered vendors reach "Service submitted for review" within 7 days of registration (qualitative target, tracked via existing analytics).

---

## Assumptions

- The vendor panel route `/vendor` is the single dashboard page; there is no per-page customisation or multi-dashboard layout to support in Phase 1 (consistent with `001-dashboard-widgets`, which deferred per-admin layouts).
- Filament v3's `\Filament\Widgets\Widget` base class with a custom Blade view is the correct component shape per `.claude/rules/filament-components.md` §5 (`Widget` (custom) | Anything else — render any Blade view). `StatsOverviewWidget` is not used because the checklist needs rich per-row layout (icon + label + sub-line + link).
- All required tables already exist in the locked 60-table schema and have been migrated in Phase 1 (`002-phase-1-identity-vendor-onboarding`) plus Phase 6.9 (`022-vendor-doc-compliance`) for `vendor_documents.expires_at` and the `changes_requested` value on `vendor_profiles.approval_status`.
- The Filament locale switcher (`bezhansalleh/filament-language-switch`, listed in `10_Package_List.md`) is already configured on `VendorPanelProvider` (verified in source) and is the single source of truth for the active locale used by the widget.
- The `CheckVendorSuspension` middleware (existing per `app/Modules/Identity/Http/Middleware/CheckVendorSuspension.php`, registered on `VendorPanelProvider->authMiddleware()`) already redirects suspended vendors to `AccountSuspendedPage`. The widget therefore renders its suspended variant on the suspended page itself if registered there, otherwise it never runs for a suspended vendor — both behaviours are acceptable per FR-EXT-008.
- A `RequiredDocumentTypesResolver` (or equivalent) may already exist as part of `022-vendor-doc-compliance`. If not, this spec assumes it will be introduced (or that an equivalent constant array on `VendorOnboardingChecklistService` is acceptable until extracted). The plan phase will confirm.
- The widget reads `vendor_approved_product_types` directly via the Identity module's own model — no cross-module imports required (Identity owns this table per the module-ownership rules).
- The widget links to existing vendor pages already present in `app/Modules/Identity/Filament/Vendor/Pages/`: `VendorProfilePage`, `VendorAccountPage` (banking details), `VendorDocumentsPage`, `VendorCoverageAreasPage`, `VendorBusinessHoursPage`, `VendorApprovalStatusPage`. Service rows link to `VendorRentalServiceResource` / `VendorSaleServiceResource` / `VendorDigitalServiceResource` index pages (Phase 2 catalog scaffolding, already exists).
- Cache layer: queries are cheap (single vendor scope, indexed FKs), so no Redis caching of the checklist state is required for Phase 1. Re-evaluation per page load is acceptable. Caching can be added in a later phase if the dashboard is profiled and shows hot-path overhead.
- Translation file location: `app/Modules/Identity/Resources/lang/{en,ar}/vendor-onboarding.php` per the module-layout convention in `CLAUDE.md`. The Identity `ServiceProvider` already calls `$this->loadTranslationsFrom(...)` for this directory.

---

## Schema Traceability

All tables referenced are existing tables from `docs/specs/11_DB_Schema.md`:

| Table | Module | Checklist Row(s) |
|---|---|---|
| `vendor_profiles` | Identity | Business profile, banking details, approval status, rejection / changes-requested reason, suspension state |
| `vendor_documents` | Identity | Documents uploaded, documents approved (with expiry check) |
| `vendor_approved_product_types` | Identity | Approved product types sub-line |
| `vendor_business_hours` | Identity | Business hours configured |
| `vendor_coverage_areas` | Identity | Coverage area added |
| `services` | Catalog | Service drafted, service submitted for review |

No new tables introduced. No write operations. All queries are read-only, single-vendor-scoped aggregates.
