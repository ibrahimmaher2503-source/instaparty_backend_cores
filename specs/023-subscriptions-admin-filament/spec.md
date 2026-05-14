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
- FR traceability: If the feature maps to existing PRD coverage → cite specific FR numbers from 01_PRD.md. If the feature is NEW or extends beyond the PRD → define local requirement numbers prefixed FR-EXT-NNN and add a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: If using an existing table → cite its name from 11_DB_Schema.md. If this feature introduces NEW tables → list them explicitly with a "⚠️ NEW TABLE — not yet in 11_DB_Schema.md" marker.
- Phase alignment: If the feature belongs to an existing phase → cite the Phase ID from 09_Phasing_Plan.md. If the feature is new work not yet phased → propose a Phase ID extension (e.g., Phase 1.X) and add a "⚠️ PHASE BACKFILL NEEDED" note.
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack
---

# Feature Specification: Subscriptions Admin Filament UI

**Feature Branch**: `023-subscriptions-admin-filament`
**Created**: 2026-05-04
**Status**: Draft
**Phase**: Phase 1.7 (ADR-0013 — Subscription Tiers Module)
**Input**: Make the already-shipped Subscriptions module operable from /admin

## Background & Motivation

The Subscriptions module backend (migrations, models, Actions, state machine) was shipped in Phase 1.7. However, no Filament admin surface exists yet, so administrators cannot:

- View or manage subscription plans and their feature limits
- See which tier a vendor is currently on
- Override a vendor's tier for exceptional cases (e.g., free trial extension, issue resolution)
- Monitor invoices, payments, or audit history

This is a blocking gap because `commission_rates.subscription_plan_id` already depends on subscription data — commissions cannot be correctly audited from the admin panel without surfacing the tier.

**ADR reference**: ADR-0013 §"Admin Override Layering" (FR-020). The exit criteria for Phase 1.7 state: "admin can override any vendor's tier with full audit visible in Filament."

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Manage Subscription Plans (Priority: P1)

A super-admin needs to view, create, edit, and deactivate subscription plans (Free, Silver, Gold, Premium) and configure each plan's feature limits (max active services, featured slots cap, Excel import access, commission discount).

**Why this priority**: Plans are the root entity everything else references. Without them, vendor subscription assignment and commission rate fallback are non-functional from the admin side.

**Independent Test**: Navigate to /admin → Subscriptions → Plans, create a "Silver" plan with max_services=10 and verify it appears in the table with the correct feature limit values.

**Acceptance Scenarios**:

1. **Given** I am logged in as super-admin, **When** I navigate to Subscriptions → Plans, **Then** I see a table listing all subscription plans with name (EN+AR), billing cycle, price, and status columns.
2. **Given** I am on the Plan creation form, **When** I fill in plan name in EN and AR, set billing_cycle=monthly, price, and add feature limits via the PlanFeatures relation manager, **Then** the plan is saved and appears in the list.
3. **Given** a plan has active vendor subscriptions, **When** I attempt to delete it, **Then** the system prevents deletion and shows a clear error message.
4. **Given** I am editing a plan, **When** I change a feature limit (e.g., max_services from 10 to 15), **Then** the change takes effect immediately for all vendors on that plan on next request.
5. **Given** I am a non-super-admin (e.g., staff without "manage subscription plans" permission), **When** I try to access Plans, **Then** I am shown an access-denied message.

---

### User Story 2 — View & Override Vendor Subscriptions (Priority: P1)

A super-admin needs to see which subscription tier every vendor is currently on, when it expires, and whether an admin override is active — and must be able to apply or revoke a tier override with a bilingual reason, creating an audit trail.

**Why this priority**: This is the core deliverable of Phase 1.7's admin exit criteria. Without it, commission auditing and vendor support escalations are manual guesswork.

**Independent Test**: Navigate to Subscriptions → Vendor Subscriptions, find a vendor, apply "Override Tier" to Gold for 30 days with reason in EN+AR, verify the vendor now shows "Gold (Override)" and an audit entry exists.

**Acceptance Scenarios**:

1. **Given** I am a super-admin, **When** I navigate to Subscriptions → Vendor Subscriptions, **Then** I see a table with columns: Vendor, Current Plan, Status, Started At, Expires At, Auto-Renew, Is Override, and Override Expires At.
2. **Given** I click "Override Tier" on a vendor row, **When** I select a plan, enter a reason in English AND Arabic, and confirm, **Then** a new `vendor_subscriptions` row with `is_admin_override=true` is inserted, the vendor's effective tier changes immediately, and an entry appears in Subscription Audit.
3. **Given** an override is active on a vendor, **When** I click "Revoke Override", **Then** the override row's status is set to cancelled, the vendor reverts to their underlying paid subscription, and a revocation audit entry is created.
4. **Given** the override reason is provided only in English (Arabic blank), **When** I submit, **Then** the form rejects submission with a validation error requiring both languages.
5. **Given** I am a staff admin without super-admin role, **When** I view the Vendor Subscriptions table, **Then** the "Override Tier" action is not visible.

---

### User Story 3 — Browse Subscription Invoices (Priority: P2)

A super-admin or billing-support admin needs to browse subscription invoices, filter by status (pending, paid, overdue, cancelled), and view the full detail of any invoice to understand what was billed and when.

**Why this priority**: Invoices are the financial paper trail for recurring billing. Support staff need this to handle vendor billing disputes without direct DB access.

**Independent Test**: Navigate to Subscriptions → Invoices, filter by status=overdue, verify the list shows only overdue invoices with correct vendor and amount columns.

**Acceptance Scenarios**:

1. **Given** I am on the Invoices list, **When** I apply the status filter = "overdue", **Then** only overdue invoices are shown, sorted by due date ascending.
2. **Given** I click "View" on an invoice, **Then** I see a read-only detail page with: vendor name, plan, period, amount, status, due date, and any associated payment records.
3. **Given** an invoice has been paid, **When** I view it, **Then** the payment record is visible in an embedded payments relation on the detail page.
4. **Given** I am a staff user without billing permission, **When** I access the Invoices resource, **Then** I see an access-denied message.

---

### User Story 4 — Browse Subscription Payments (Priority: P2)

A super-admin or billing-support admin needs a read-only ledger of all subscription payments, showing amount, status, gateway reference, and the invoice they settled.

**Why this priority**: Completes the financial audit trail. Required for reconciliation with Paymob gateway records.

**Independent Test**: Navigate to Subscriptions → Payments, confirm the list shows payment records with amount, gateway_ref, status, and a link to the parent invoice.

**Acceptance Scenarios**:

1. **Given** I am on the Payments list, **When** I sort by created_at descending, **Then** the most recent payments appear first.
2. **Given** I click a payment row, **Then** no edit page opens (read-only) — only a view page or an inline detail panel.
3. **Given** no create/edit/delete buttons exist on the Payments resource, **Then** only ViewAction is accessible (no CreateAction, EditAction, or DeleteAction in the table).

---

### User Story 5 — Browse Subscription Audit Log (Priority: P2)

A super-admin needs a read-only, append-only audit trail of every significant subscription event (tier overrides, status transitions, manual admin actions) to support compliance and support escalations.

**Why this priority**: Append-only auditability is a CLAUDE.md hard rule for ledger tables. The audit resource must surface this data without allowing any mutation.

**Independent Test**: Navigate to Subscriptions → Audit Log, verify the list loads with actor, event_type, before_state, after_state, reason (EN+AR), and created_at columns. Verify no Create, Edit, or Delete actions exist.

**Acceptance Scenarios**:

1. **Given** I am on the Audit Log list, **When** I view the table, **Then** columns show: actor (admin user), event_type, before_state, after_state, reason (bilingual), vendor, and created_at.
2. **Given** any admin role, **Then** no Create, Edit, or Delete actions are visible on the Audit Log resource.
3. **Given** I filter by vendor, **Then** only audit entries for that vendor are shown.

---

### User Story 6 — Dashboard Subscription Widgets (Priority: P3)

An admin on the dashboard needs at-a-glance visibility into vendor subscription health: how many vendors are on each tier, and how many subscriptions are past-due.

**Why this priority**: Surface-level KPIs. Ops teams check the dashboard before drilling into lists; surfacing past-due counts prompts proactive intervention.

**Independent Test**: Visit /admin dashboard, confirm two subscription widgets appear — a stacked bar "Vendors by Tier" and a stat "Past-Due Subscriptions" showing a count > 0 when seeded data has overdue records.

**Acceptance Scenarios**:

1. **Given** the dashboard is loaded, **When** vendor subscriptions exist across tiers, **Then** the "Vendors by Tier" widget shows a bar chart with one bar per tier (Free, Silver, Gold, Premium).
2. **Given** past-due subscriptions exist, **When** the dashboard is loaded, **Then** the "Past-Due Subscriptions" stat shows the correct count with a warning color.
3. **Given** no past-due subscriptions exist, **Then** the stat shows "0" with a success color.

---

### Edge Cases

- What happens when an admin tries to override a vendor that has no active subscription? → A new override subscription row is created from a "none" baseline; effective tier resolution picks the override. The underlying "none" state is preserved.
- What if an override's `override_expires_at` passes without manual revocation? → The `subscriptions:end-overrides` cron job (already registered per ADR-0013) transitions the override row to expired; the underlying subscription resurfaces automatically.
- What if a plan is edited while the FeatureResolver cache is warm? → Plan edit must call `FeatureResolver::forgetForPlan($planId)` in an `afterSave()` hook so limits reflect immediately.
- What if a user navigates to Audit Log and attempts to guess an edit URL directly? → The resource must not register `EditAction` or `CreateAction` — Shield permissions must not grant edit/create roles.

---

## Requirements *(mandatory)*

### Functional Requirements

**Plan Management**

- **FR-EXT-001**: Admin MUST be able to create, view, edit, and list subscription plans with bilingual names (EN + AR). ⚠️ BACKFILL NEEDED: add to 01_PRD.md §Subscriptions
- **FR-EXT-002**: Admin MUST be able to add, edit, and remove plan feature limits (max_services, featured_cap, excel_import_enabled, commission_discount_bps) via the PlanFeatures relation manager.
- **FR-EXT-003**: System MUST prevent deletion of a plan that has at least one active `vendor_subscriptions` row referencing it; a validation error must be shown.
- **FR-EXT-004**: Editing a plan's feature limits MUST invalidate the FeatureResolver cache for that plan immediately after save.

**Vendor Subscription Management**

- **FR-020** (ADR-0013): Admin MUST be able to apply a tier override to any vendor. Override inserts a new `vendor_subscriptions` row with `is_admin_override=true`. The underlying subscription continues uninterrupted.
- **FR-EXT-005**: The "Override Tier" action MUST require a non-empty reason in both English and Arabic before submission is accepted.
- **FR-EXT-006**: The "Override Tier" action MUST be restricted to users with the `super_admin` role or the explicit `override_vendor_subscription` permission.
- **FR-EXT-007**: Applying an override MUST create a corresponding `subscription_audit` row recording actor, event_type=`admin_override_applied`, before/after states, and the bilingual reason.
- **FR-EXT-008**: Admin MUST be able to revoke an active override. Revocation sets the override row's status to `cancelled` and creates a `subscription_audit` row with event_type=`admin_override_revoked`.
- **FR-EXT-009**: The VendorSubscriptionResource table MUST display: vendor display name, current effective plan, status, started_at, expires_at, auto_renew flag, is_admin_override flag, and override_expires_at.

**Invoice Browse**

- **FR-EXT-010**: Admin MUST be able to list subscription invoices filterable by status (pending, paid, overdue, cancelled) and by vendor.
- **FR-EXT-011**: Invoice detail page MUST be read-only, showing all invoice fields and an embedded payments relation.
- **FR-EXT-012**: Invoices resource MUST NOT expose Create, Edit, or Delete actions.

**Payment Ledger**

- **FR-EXT-013**: Admin MUST be able to list subscription payments with columns: amount (formatted EGP), status, gateway_ref, invoice link, created_at.
- **FR-EXT-014**: Payments resource MUST be read-only (no Create, Edit, or Delete actions).

**Audit Log**

- **FR-EXT-015**: Admin MUST be able to list subscription audit entries with columns: actor, event_type, vendor, before_state, after_state, reason (bilingual), created_at.
- **FR-EXT-016**: Audit Log resource MUST NOT expose Create, Edit, or Delete actions — append-only rule (CLAUDE.md §15).
- **FR-EXT-017**: Audit entries MUST be filterable by vendor and by event_type.

**Dashboard Widgets**

- **FR-EXT-018**: Dashboard MUST display a "Vendors by Tier" bar/stacked chart showing vendor count per subscription plan.
- **FR-EXT-019**: Dashboard MUST display a "Past-Due Subscriptions" stat widget showing the count of subscriptions in grace or overdue status, colored warning/danger.

**Admin Panel Registration**

- **FR-EXT-020**: `AdminPanelProvider::panel()` MUST include a `discoverResources` call for `app/Modules/Subscriptions/Filament/Resources`.
- **FR-EXT-021**: A "Subscriptions" navigation group MUST be registered in `AdminPanelProvider::navigationGroups()`.

**Permissions**

- **FR-EXT-022**: `php artisan shield:generate --all` MUST be run after resource creation to register permissions in `filament-shield`.
- **FR-EXT-023**: Override and revoke actions MUST enforce `super_admin` or explicit `override_vendor_subscription` permission via Shield.

### Key Entities

- **SubscriptionPlan** (`subscription_plans`): A billing tier with name (EN+AR), billing_cycle, price_minor, currency, is_active. Has many PlanFeatures.
- **PlanFeature** (`plan_features`): A limit row keyed by (plan_id, feature_key) with a numeric or boolean value. Governs enforcement in Catalog and Discovery.
- **VendorSubscription** (`vendor_subscriptions`): Assignment of a vendor to a plan for a billing period. Has state machine (active/grace/expired/cancelled). `is_admin_override=true` marks admin-inserted rows.
- **SubscriptionInvoice** (`subscription_invoices`): Append-only (status-only UPDATE). Represents a billing event for a vendor subscription period.
- **SubscriptionPayment** (`subscription_payments`): Insert-only. Records Paymob payment attempts against an invoice.
- **SubscriptionAuditEntry** (`subscription_audit`): Append-only ledger. Records every admin action and state transition on vendor subscriptions.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can locate any vendor's current subscription tier in under 30 seconds from the dashboard.
- **SC-002**: Applying a tier override completes in a single form submission with no additional steps, and the audit entry is visible immediately after.
- **SC-003**: 100% of past-due subscriptions (relative to seeded test data) appear in the "Past-Due Subscriptions" dashboard widget.
- **SC-004**: No Create, Edit, or Delete actions are accessible on the SubscriptionPaymentResource or SubscriptionAuditEntryResource by any role.
- **SC-005**: All 5 Subscriptions resources appear under the "Subscriptions" navigation group in /admin.
- **SC-006**: All Pest tests pass green: override action, invoice filter, audit immutability, and widget count tests.
- **SC-007**: Both dashboard widgets load without errors on a fresh seeded database.

---

## Assumptions

- The Subscriptions module backend (models, Actions, migrations, state machine) is fully shipped and working on the current branch — this spec covers the Filament UI surface only.
- The `SubscriptionPlan`, `VendorSubscription`, `SubscriptionInvoice`, `SubscriptionPayment`, and `SubscriptionAuditEntry` Eloquent models already exist in `app/Modules/Subscriptions/Domain/Models/`.
- The `AdminOverrideApplied` domain event class already exists (per ADR-0013); the Filament action fires it via `DB::afterCommit()`.
- The `FeatureResolver` service already exists and has a `forgetForPlan(int $planId)` method.
- The `subscriptions:end-overrides` cron job is already registered; this spec does not add new cron jobs.
- No new packages are required — all Filament patterns use the existing curated plugin list from `docs/specs/10_Package_List.md`.
- `subscription_plans` plan names are translatable JSON columns (per ADR-0013 and CLAUDE.md conventions).
- The "Subscriptions" navigation group key (`admin.nav.groups.subscriptions`) needs to be added to `AdminPanelProvider::navigationGroups()` and the EN/AR translation files.
- Phase scope: This is Phase 1.7 work per ADR-0013. No Phase 2 features (e.g., per-feature toggle UX, compare-plans view, mass Excel import) are in scope.

---

## Out of Scope (Cut-List per ADR-0013)

- Mass-import of subscription plans via Excel → Phase 1.5 (deferred)
- Per-feature toggle UX inside plan edit form (JSON field is sufficient for now) → Phase 1.5
- "Compare plans side-by-side" admin view → Phase 1.5
- Vendor-facing subscription management UI → Phase 2 (Flutter/Next.js)
- Subscription renewal checkout flow → Phase 2
