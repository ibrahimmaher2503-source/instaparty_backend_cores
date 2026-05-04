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
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack
---

# Feature Specification: Phase 6.4 — Admin Vendor Management (Full CRUD)

**Feature Branch**: `008-settlement-wallets-commissions-withdrawals`
**Created**: 2026-05-03
**Status**: Draft
**Phase**: 6.4 — Admin Vendor Management (1 day, Week 7)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Edit Vendor Business Details (Priority: P1)

An admin needs to correct or update a vendor's business information (business name, bio, phone, address, hours, coverage areas) without going through the approval queue, and without losing the vendor's existing approval state or document history.

**Why this priority**: This is the foundational management operation. Every other admin vendor action (revocation, impersonation, document replacement) depends on being able to navigate to and view a vendor's full profile.

**Independent Test**: Can be fully tested by navigating to `VendorResource`, finding a vendor, opening the edit form, updating a business name and coverage area, saving, and verifying that `vendor_profiles.approval_status` did not change and `audit_logs` recorded the update.

**Acceptance Scenarios**:

1. **Given** an approved vendor exists, **When** admin edits `business_name` (EN + AR) and saves, **Then** the `vendor_profiles` row is updated and the `vendor_profiles.approval_status` remains unchanged.
2. **Given** an admin edits business hours for a vendor, **When** the form is saved, **Then** `vendor_business_hours` rows are replaced and an `audit_logs` entry records who changed them.
3. **Given** an admin edits coverage areas, **When** the form is saved, **Then** `vendor_coverage_areas` rows are updated and the vendor's searchable coverage reflects the change.
4. **Given** the edit form has both EN and AR business name tabs, **When** only one locale is filled, **Then** form validation rejects submission with a bilingual error message.

---

### User Story 2 — Force-Revoke Product Type Approval with Reason (Priority: P2)

An admin discovers a vendor is misusing or underperforming in a specific product type (e.g., "rental") and needs to revoke that type approval while capturing a mandatory revocation reason for the audit trail.

**Why this priority**: Critical for platform trust and compliance. Per Constitution Principle X, per-type approvals are independently managed. Revocation must be captured in `vendor_approved_product_types` with a reason column and mirrored to `audit_logs`.

**Independent Test**: Can be fully tested by selecting a vendor with an approved type, triggering the revoke action, submitting a reason, and asserting the `vendor_approved_product_types` row has `status='revoked'` and `revocation_reason` is populated, plus an `audit_logs` entry exists.

**Acceptance Scenarios**:

1. **Given** a vendor is approved for `rental`, **When** admin revokes with reason "Repeated no-shows", **Then** `vendor_approved_product_types.status` becomes `revoked`, `revocation_reason` is stored, and an `audit_logs` entry is created.
2. **Given** a revocation is triggered, **When** the reason field is empty, **Then** the action form rejects submission (reason is mandatory).
3. **Given** a vendor has multiple type approvals, **When** admin revokes one type, **Then** other type approvals are unaffected.
4. **Given** a revocation is saved, **When** a domain event fires, **Then** a notification is dispatched to the vendor (via existing `DispatchNotificationAction`) informing them of the revocation.

---

### User Story 3 — Impersonate Vendor (Audit-Logged) (Priority: P3)

An admin needs to debug a vendor's experience (e.g., reproduce a UI bug or verify catalog visibility) by temporarily impersonating the vendor's account. Every impersonation session must be traceable in the audit log.

**Why this priority**: Powerful debugging tool but must never be silently used. Constitution Principle I (audit everything) applies. The audit log entry is non-negotiable — impersonation without logging is forbidden.

**Independent Test**: Can be fully tested by triggering the impersonate action on a vendor, asserting `audit_logs` contains `event='vendor_impersonation_started'` with `causer_id = admin_user_id` and `subject_id = vendor_user_id`, and verifying a Sanctum token is returned.

**Acceptance Scenarios**:

1. **Given** admin triggers impersonation on a vendor, **When** the action executes, **Then** an `audit_logs` entry is created with `event='vendor_impersonation_started'`, the admin user as `causer`, and the vendor user as `subject` — before the token is returned.
2. **Given** an audit log entry for impersonation exists, **When** queried, **Then** it contains the admin's IP address and the timestamp.
3. **Given** a non-super-admin triggers impersonation, **When** the permission check runs, **Then** the action is denied with a 403.

---

### User Story 4 — View Vendor Detail Tabs (Services, Bookings, Wallet, Reviews) (Priority: P4)

An admin investigating a vendor complaint needs to see all related data (services, booking history, wallet balance, reviews) in a single unified view without navigating to multiple separate resources.

**Why this priority**: Observability feature — improves admin efficiency but does not mutate data. Lower priority than write operations.

**Independent Test**: Can be fully tested by navigating to a vendor's detail page and verifying each tab (Overview, Documents, Services, Bookings, Wallet, Withdrawals, Reviews, Activity) renders the correct related records without errors.

**Acceptance Scenarios**:

1. **Given** a vendor has 3 services, 2 bookings, and 1 review, **When** admin opens the vendor detail page, **Then** each tab displays the correct count and records.
2. **Given** a vendor has no wallet yet, **When** admin opens the Wallet tab, **Then** an empty state message is shown (no 500 error).
3. **Given** the Activity tab is open, **When** admin views it, **Then** `audit_logs` entries for this vendor are displayed in reverse-chronological order.

---

### User Story 5 — Re-upload / Replace Vendor Documents (Priority: P5)

An admin needs to replace an expired or rejected vendor document (commercial registration, tax card, IBAN proof) without creating a duplicate record.

**Why this priority**: Supporting operation for the core approval flow. Lower priority since documents are primarily managed through the approval queue; this is a management-only fallback.

**Independent Test**: Can be fully tested by uploading a new file for an existing `vendor_documents` row and asserting the old S3 path is replaced and an `audit_logs` entry records the replacement.

**Acceptance Scenarios**:

1. **Given** a vendor has a `commercial_registration` document, **When** admin uploads a replacement file, **Then** the `vendor_documents.file_path` is updated and the old file is deleted from S3 (or marked replaced).
2. **Given** a replacement is uploaded without a file attached, **When** the form is submitted, **Then** validation rejects with an error message.

---

### Edge Cases

- What happens when admin edits a suspended vendor's profile? Suspension must remain; only business fields update.
- How does the system handle coverage area edits for a vendor with active bookings in the old areas? Existing bookings are unaffected (coverage areas are not denormalized into bookings).
- What if two admins simultaneously edit the same vendor? Last-write-wins (no optimistic locking in Phase 1 — acceptable given low concurrency).
- What if impersonation is triggered for a vendor whose account is `banned`? The action should still log the attempt but can return a warning to the admin.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The admin panel MUST expose a `VendorResource` (distinct from `VendorApprovalQueueResource`) providing full CRUD for vendor business information.
- **FR-002**: Admin MUST be able to filter the vendor list by approval status, approved product types, and governorate.
- **FR-003**: Admin MUST be able to edit vendor business name (EN+AR), bio (EN+AR), phone, website, and address without altering the vendor's approval state.
- **FR-004**: Admin MUST be able to edit a vendor's business hours (all days of the week) via the vendor detail page.
- **FR-005**: Admin MUST be able to edit a vendor's coverage areas (add/remove cities) via the vendor detail page.
- **FR-006**: Admin MUST be able to force-revoke a product-type approval with a mandatory text reason; the revocation MUST be recorded in `vendor_approved_product_types` and `audit_logs`.
- **FR-007**: Every edit action on a vendor record (profile, hours, coverage, documents) MUST create an `audit_logs` entry capturing the admin user, changed fields, and timestamp.
- **FR-008**: Admin MUST be able to replace any vendor document by uploading a new file; the new file MUST be stored in the private S3 bucket and the old path replaced.
- **FR-009**: The vendor detail view MUST display tabbed related data: Overview, Documents, Services, Bookings, Wallet, Withdrawals, Reviews, Activity.
- **FR-010**: Admin MUST be able to impersonate a vendor by triggering `ImpersonateVendorAction`, which MUST create an `audit_logs` entry BEFORE returning a Sanctum token or session data.
- **FR-011**: Impersonation MUST be restricted to admins with the `impersonate_vendor` permission (Shield-managed).
- **FR-012**: Product-type revocation MUST fire a domain event (`VendorTypeRevoked`) after `DB::afterCommit`, which dispatches a notification to the vendor.
- **FR-013**: All admin-facing text in the `VendorResource` (labels, confirmation dialogs, notifications) MUST be available in both EN and AR via lang files.

### Key Entities

- **VendorProfile**: Core vendor entity with `approval_status`, `business_name` (JSON translatable), `bio` (JSON translatable), contact details, and S3 document references. Owned by Identity module.
- **VendorDocument**: Typed document record (`document_type` enum: `commercial_registration`, `tax_card`, `iban_proof`) with `file_path`, `file_name`, `status`. Owned by Identity module.
- **VendorBusinessHour**: Day-of-week + open/close time rows per vendor. Replaced as a set on each update.
- **VendorCoverageArea**: Junction between `vendor_profile_id` and `city_id`. Replaced as a set on each update.
- **VendorApprovedProductType**: Records per-type approval with `status` (`approved`, `revoked`) and `revocation_reason` (nullable text).
- **AuditLog**: Append-only event record. Every admin mutation on vendor data produces one entry here.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can locate a vendor, open their detail page, edit a business field, and save — completing the full edit flow in under 90 seconds in a production-sized dataset (1,000+ vendors).
- **SC-002**: 100% of impersonation events produce a corresponding `audit_logs` entry before the action response is returned — zero impersonation events can occur without a log.
- **SC-003**: 100% of product-type revocations persist a `revocation_reason` — no revocation record exists without a reason in the database.
- **SC-004**: The vendor detail tabbed view loads all six tabs without an HTTP 500 or query error, including when related tables (wallet, bookings) are empty for that vendor.
- **SC-005**: All vendor management UI labels and notifications render correctly in both EN (LTR) and AR (RTL) without layout breakage.
- **SC-006**: Pest test suite covers: edit preserves approval state, impersonation creates audit entry, revocation stores reason, revocation fires domain event, unauthorized impersonation returns 403.

---

## Assumptions

- `VendorApprovalQueueResource` already exists (from Phase 1.1) and handles the initial approval workflow. `VendorResource` is a complementary management interface — it does NOT replace the approval queue.
- The `vendor_approved_product_types` table has a `revocation_reason` column (nullable text). If not present, a migration adding this column is required before this phase begins.
- `audit_logs` table is append-only and already exists (from Phase 6.0). This phase only writes to it, never reads for mutation.
- Document replacement deletes the old S3 object or marks it orphaned — exact S3 cleanup strategy defers to the admin's judgment (the `file_path` column is updated immediately).
- Impersonation returns a short-lived Sanctum token (not a session cookie) — the Flutter/Next.js frontend consumes it. The scope of how the frontend uses the token is out of scope for this backend spec.
- `ImpersonateVendorAction` does NOT require the vendor to have an approved profile — an admin may need to impersonate a suspended vendor for debugging.
- Business hours replacement is full-delete-and-reinsert within a single `DB::transaction` — no partial-update strategy.
- Coverage area replacement is full-delete-and-reinsert within a single `DB::transaction`.
- The `VendorTypeRevoked` domain event notification template is assumed to already exist (or will be seeded) in `notification_templates` from the Communication module. If not, this phase seeds a minimal placeholder.
