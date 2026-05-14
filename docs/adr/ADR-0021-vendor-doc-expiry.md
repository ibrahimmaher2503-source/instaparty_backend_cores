# ADR-0021: Vendor Document Expiry and Compliance Lifecycle

**Status:** Accepted  
**Date:** 2026-05-04  
**Author:** Ibrahim Maher  
**Relates to:** Phase 6.9 (Vendor Document Compliance Lifecycle)

---

## Context

Vendor documents (Commercial Registry, tax cards, IBAN proofs) are foundational to vendor onboarding and ongoing operations. Currently, once approved, they have no time validity — the system treats them as permanent. In practice, government-issued documents have expiry dates and must be renewed. Without a compliance lifecycle, vendors may operate beyond legal authority, creating legal and regulatory risk.

The PRD (Admin Journey §4) implies ongoing validity checks, not one-time approval. The system must:
- Notify vendors of upcoming expirations (30/14/7/1 days before)
- Automatically suspend vendors when critical docs expire (cannot accept new bookings)
- Allow admins to grant grace periods (14 days) with audit trail
- Track all compliance state changes for audit

---

## Decision

We implement vendor document expiry tracking via six architectural constraints:

### 1. **Expiry per Document, Not per Vendor**

**Decision:** Each document (`vendor_documents` row) has an independent `expires_at` date. No single "vendor expiry date" exists.

**Rationale:**
- Commercial Registry, tax card, IBAN proof have different expiry cycles in Egyptian law (CR: 1 year, tax: annual, IBAN: none).
- Vendors often renew docs on different schedules.
- Simpler query patterns (check one doc at a time) than aggregating across all vendor docs.
- Enables Phase 1.5 to add per-document-type policies without schema churn.

**Consequence:** Compliance logic iterates per-document, not per-vendor. A vendor with one expired critical doc suspends; other docs don't matter.

### 2. **Document Criticality Determines Auto-Suspension**

**Decision:** Only documents marked `is_critical = true` trigger automatic suspension on expiry. Non-critical expired docs only send notifications.

**Rationale:**
- Not all docs are equally important (e.g., optional imagery or certificates).
- Legal risk concentrates on a few key docs (CR, tax, IBAN proof).
- Admins set criticality during initial doc upload; system enforces policy.

**Consequence:**
- Auto-suspension is a binary safety gate, not a continuum.
- Admins must deliberately mark docs critical to activate suspension logic.
- Test coverage splits: critical → suspend, non-critical → remind-only.

### 3. **Grace Period Fixed at 14 Days**

**Decision:** When an admin grants grace (via `GrantDocGracePeriodAction`), the `expires_at` is extended by exactly 14 calendar days. No other grace duration exists in Phase 1.

**Rationale:**
- Vendors need predictable breathing room to obtain renewals (typical Egyptian processing: 5–10 days).
- 14 days is standard in commercial software and admin experience (e.g., Stripe disputes, Shopify risk holds).
- Fixed duration prevents admin discretion creep (avoid per-case hardcoding).

**Consequence:**
- Grace period is hardcoded in `GrantDocGracePeriodAction::GRACE_DAYS = 14`.
- Phase 1.5 can introduce configurable grace periods via `app_settings` table.
- If a vendor misses grace deadline, they are re-suspended; no further automatic extensions.

### 4. **AutoSuspendForExpiredDocAction Is Independent**

**Decision:** `AutoSuspendForExpiredDocAction` executes within the scheduled `CheckDocumentExpiryCommand` and operates independently from the existing `SuspendVendorAction` (which handles manual/policy-driven suspensions).

**Rationale:**
- Auto-suspension is a **compliance workflow**: triggered by system state, not user request.
- `SuspendVendorAction` is a **vendor management action**: triggered by admin UI with broad permissions.
- Authorization context differs: scheduled command runs as system, not on behalf of a user.
- Separation avoids conflating compliance events with admin actions in audit logs.

**Consequence:**
- `AutoSuspendForExpiredDocAction` updates `vendor_profiles.approval_status = 'suspended'` directly.
- It does NOT call `SuspendVendorAction` (no auth guard needed).
- It creates a compliance event with `admin_id = null` (system-initiated).
- Listeners (e.g., `RevokeAllVendorTypesOnStatusChange`) still fire on the status change.

### 5. **Compliance Events Are Append-Only**

**Decision:** `vendor_compliance_events` table has no `updated_at` column. Records are created once and never modified. Corrections are new events, not updates.

**Rationale:**
- Compliance audit trails must be immutable: admins cannot rewrite history.
- Append-only schema prevents accidental overwrites and simplifies analytics queries.
- Natural time-series structure for reporting and debugging.
- Aligns with other audit-critical tables (`audit_logs`, `booking_state_transitions`, `wallet_ledger`).

**Consequence:**
- Every state change (reminder sent, grace granted, re-suspension) creates a new event row.
- To correct a mistaken compliance event, an admin creates an inverse event (e.g., "manually_overridden" with reason "Reversal of previous incorrect suspension").
- No soft-deletes on this table; deletion is a new event.

### 6. **Re-Suspension After Grace Expiry**

**Decision:** If a vendor is granted grace (14 days) and the document still has `is_critical = true` when grace expires, the `CheckDocumentExpiryCommand` automatically re-suspends them without requiring a new admin action or grace override.

**Rationale:**
- Grace is a **one-time reprieve**, not a repeat safety net.
- After 14 days, admins have intervened to grant time; vendors must act.
- Prevents abuse: vendor lets doc expire, gets grace, lets it expire again → repeat loop.
- Matches intuition: "one more chance" has a deadline.

**Consequence:**
- Scheduling logic tracks grace-period expiry as a separate temporal boundary.
- If `suspended_until` < today and doc still critical and expired, auto-suspend again.
- Vendor receives final expiry notification and re-suspension event in audit log.
- Admins see re-suspended vendors in compliance dashboard and can grant grace again if justified.

---

## Alternatives Considered

| Alternative | Why Rejected |
|---|---|
| Vendor-level expiry (single date) | Ignores multi-doc renewal cycles; breaks Phase 1.5 per-type policies. |
| Infinite grace periods | Enables vendor indefinite operation beyond legal renewal deadlines. |
| Three configurable grace durations | Introduces admin decision fatigue; most enterprises standardize on one duration. |
| Unified `SuspendVendorAction` for auto + manual | Blurs compliance workflow with vendor management; complicates audit trail. |
| Updatable compliance events | Breaks immutability guarantee; admins could rewrite history; fails audit requirements. |
| Repeat grace indefinitely | Allows circumvention: expire → grace → expire → grace → ... |

---

## Rationale

This design balances three concerns:

1. **Compliance:** Vendors cannot indefinitely operate with expired critical docs.
2. **Operational:** Admins have one clear intervention point (grant grace) with clear consequences (one-time 14-day extension).
3. **Maintainability:** Append-only events, independent actions, and immutable audit trails reduce future debugging and enable auditing.

The modular architecture (separate Actions, Events, Commands, Listeners) allows Phase 1.5 to extend without breaking Phase 1 core behavior. For example:
- Per-document-type expiry rules (CR = 1 year, IBAN = no expiry) can be added as a policy layer above document-creation endpoints without touching the compliance scheduler.
- Auto-renewal via document upload can be added as a listener on a hypothetical `VendorDocumentRenewed` event.

---

## Implementation Notes

- **Migration Order:** Compliance schema (3 migrations: vendor_profiles.suspension_reason, vendor_documents columns, vendor_compliance_events table) must be applied before scheduled command deployment.
- **Notification Templates:** 5 event keys (vendor.doc_expiring_{30d,14d,7d,1d,expired}) × 2 channels (in_app, email) × 2 locales (EN, AR) = 10 notification rows.
- **Testing:** Time-travel tests verify reminder delivery and suspension/grace boundaries. All 16 test cases use `$this->travelTo()` to simulate date progression without side-effects.
- **Audit:** Every compliance state change (reminder, expiry, auto-suspend, grace, re-suspend) is logged as a `VendorComplianceEvent` with optional `reason` JSON for bilingual admin explanations.

---

## Acceptance Criteria

✅ Vendor document expiry dates are independently tracked per-document.  
✅ Critical documents trigger auto-suspension on expiry; non-critical docs only notify.  
✅ Admin grace-period grants extend `expires_at` by 14 days with audit trail.  
✅ Vendors cannot indefinitely extend grace; re-suspension occurs if doc still expired after grace.  
✅ All compliance events are append-only with no update capability.  
✅ Auto-suspension is independent from manual suspension; both update status but only auto-suspension creates compliance events.  

---

## Consequences

- **For Phase 1:** Compliance dashboard and notification system are operational Day 1; daily scheduled job processes document expirations.
- **For Phase 1.5:** Per-document-type policies, auto-renewal heuristics, and configurable grace periods can be layered on top without schema churn.
- **For Operations:** Vendor suspension is now bi-modal: manual (admin action) and automatic (compliance). Both show in audit logs. Admins must monitor pending suspensions and grant grace or require renewal.

