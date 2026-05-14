# Feature Specification: Vendor Document Compliance Lifecycle

**Feature Branch**: `022-vendor-doc-compliance`  
**Created**: 2026-05-04  
**Status**: Draft  
**Phase**: Phase 6.9 — Vendor Document Compliance Lifecycle ⚠️ PHASE BACKFILL NEEDED (add to `docs/specs/09_Phasing_Plan.md`)  
**ADR Required**: ADR-0021-vendor-doc-expiry.md  
**Depends On**: Phase 1.1 (Vendor Onboarding + Approval)  
**Estimated Effort**: 1 day, Week 7  
**Priority**: 🔴 MUST

---

## PRD & Schema Traceability

### PRD Coverage

| Requirement | FR # | Notes |
|---|---|---|
| Admin reviews vendor data + bank for ongoing validity | FR-EXT-001 ⚠️ BACKFILL NEEDED | Admin Journey step 4 implies ongoing, not one-time, validity check |
| Vendor suspension when compliance is broken | FR-EXT-002 ⚠️ BACKFILL NEEDED | Extends FR-5 (vendor approval gate) with time-bounded validity |
| Admin override with audit trail | FR-EXT-003 ⚠️ BACKFILL NEEDED | Extends admin management capabilities |

### Tables Touched

| Table | Status | Notes |
|---|---|---|
| `vendor_documents` | Existing (alter) | Add `expires_at`, `is_critical`, `last_reminder_sent_at` |
| `vendor_compliance_events` | **⚠️ NEW TABLE — not yet in 11_DB_Schema.md** | Append-only audit log for all compliance lifecycle events |

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Vendor Receives Expiry Reminders (Priority: P1)

A vendor has a Commercial Registration (CR) document uploaded and approved by admin. The document is marked as `is_critical=true` with an expiry date set. The vendor receives automated reminder notifications 30, 14, 7, and 1 day(s) before expiry via their registered channels (email + push), giving them enough time to upload a renewed document.

**Why this priority**: Without reminders, vendors don't know their documents are expiring. An unexpected auto-suspension breaks their ability to accept new bookings, causing revenue loss and a bad vendor experience. The reminder cascade is the primary UX value of this feature.

**Independent Test**: Set a vendor document with `expires_at = today + 30 days`, run the daily compliance job, and verify a reminder notification is dispatched. Can be demonstrated end-to-end with a single document and time-travel in tests.

**Acceptance Scenarios**:

1. **Given** a vendor document with `expires_at = today + 30 days` and `last_reminder_sent_at = NULL`, **When** `CheckDocumentExpiryCommand` runs, **Then** a `vendor.doc_expiring_30d` notification is dispatched to the vendor and `last_reminder_sent_at` is updated to today.
2. **Given** a vendor document with `expires_at = today + 14 days` and `last_reminder_sent_at < today - 1 day`, **When** the command runs, **Then** a `vendor.doc_expiring_14d` notification is dispatched.
3. **Given** a vendor document with `expires_at = today + 7 days`, **When** the command runs, **Then** a `vendor.doc_expiring_7d` notification is dispatched.
4. **Given** a vendor document with `expires_at = today + 1 day`, **When** the command runs, **Then** a `vendor.doc_expiring_1d` notification is dispatched.
5. **Given** a vendor document where a reminder was already sent today (`last_reminder_sent_at = today`), **When** the command runs again, **Then** no duplicate notification is dispatched.

---

### User Story 2 — Critical Document Expiry Triggers Auto-Suspension (Priority: P1)

A vendor's CR document (`is_critical=true`) reaches its `expires_at` date without renewal. The system automatically suspends the vendor — they can no longer accept new bookings. Existing in-progress bookings are unaffected and continue to completion. A `vendor.doc_expired` notification is sent to the vendor. A `vendor_compliance_events` record logs the auto-suspension.

**Why this priority**: The platform must enforce document validity to maintain regulatory compliance. An expired CR means the vendor is operating without a valid business registration — allowing continued bookings would be a legal risk for the platform.

**Independent Test**: Set `expires_at = yesterday`, `is_critical = true`, run the command, and verify the vendor's `vendor_profiles.suspension_reason` is set and new bookings are rejected. Can be tested without any UI.

**Acceptance Scenarios**:

1. **Given** a vendor document with `is_critical=true` and `expires_at = yesterday` and vendor is currently active, **When** `CheckDocumentExpiryCommand` runs, **Then** the vendor profile is suspended, `vendor_compliance_events` gets an `auto_suspended` record, and a `vendor.doc_expired` notification is dispatched.
2. **Given** an auto-suspended vendor (due to expired critical doc), **When** the vendor tries to receive a new booking, **Then** the booking is rejected with an appropriate error message in EN + AR.
3. **Given** an auto-suspended vendor, **When** checking existing in-progress bookings, **Then** those bookings continue normally — suspension does not cascade to existing bookings.
4. **Given** a vendor document with `is_critical=false` and `expires_at = yesterday`, **When** the command runs, **Then** the vendor is NOT suspended — only a reminder notification is sent.
5. **Given** a vendor document with `is_critical=true` and no `expires_at` set (NULL), **When** the command runs, **Then** no action is taken — no expiry check, no notification.

---

### User Story 3 — Admin Views Compliance Dashboard (Priority: P2)

An operations admin visits the Filament admin panel and sees two at-a-glance widgets on the ops dashboard (Phase 8.1): (1) documents expiring this calendar month with vendor name + doc type + days remaining, (2) vendors currently auto-suspended awaiting document renewal with the suspension date and reason.

**Why this priority**: The dashboard is the admin's primary monitoring surface. Without visibility, admins cannot proactively reach out to vendors or audit the system's compliance enforcement.

**Independent Test**: Seed 3 documents expiring this month and 2 auto-suspended vendors. Navigate to `/admin` dashboard and verify both widgets show the correct counts and records.

**Acceptance Scenarios**:

1. **Given** vendor documents with `expires_at` falling within the current calendar month, **When** admin opens the ops dashboard, **Then** the "Documents Expiring This Month" widget shows each document with vendor name, doc type, expiry date, and days remaining.
2. **Given** vendors with `suspension_reason LIKE '%doc_expiry%'`, **When** admin opens the dashboard, **Then** the "Auto-Suspended Vendors" widget shows each vendor with suspension date and reason.
3. **Given** an admin clicks a vendor row in either widget, **Then** they are navigated to that vendor's profile management page.

---

### User Story 4 — Admin Grants Grace Period Override (Priority: P2)

An admin reviews an auto-suspended vendor who has a legitimate renewal in progress (e.g., waiting for government office). The admin selects "Grant Grace Period (14 days)" from the `ExpiredDocsResource` queue, provides a written reason, and confirms. The vendor is un-suspended for 14 days. The override is logged in `vendor_compliance_events` with the admin's user ID and reason. The vendor receives a notification confirming the grace period.

**Why this priority**: Auto-suspension is a blunt instrument. Real-world compliance has delays (bureaucracy, postal service). The grace period prevents unnecessary vendor churn while maintaining the platform's audit trail.

**Independent Test**: Start with an auto-suspended vendor, call `GrantDocGracePeriodAction::execute()` with a reason, and verify the vendor is un-suspended and the compliance event is logged.

**Acceptance Scenarios**:

1. **Given** an auto-suspended vendor in the `ExpiredDocsResource` queue, **When** admin selects "Grant Grace Period" and provides a reason, **Then** `GrantDocGracePeriodAction` is called, the vendor is un-suspended, `vendor_compliance_events` gets a `manually_overridden` record with `admin_id` and `reason`, and a grace period confirmation notification is sent to the vendor.
2. **Given** a grace period vendor whose grace period has elapsed, **When** the daily command runs, **Then** the vendor is re-suspended and a new `auto_suspended` compliance event is logged.
3. **Given** an admin attempts to grant grace without a reason, **Then** the Filament form validation rejects the action with an appropriate validation message.
4. **Given** a grace period override is applied, **When** querying `vendor_compliance_events`, **Then** the event record shows `event_type='manually_overridden'`, the admin's ID, and the reason text in both EN + AR.

---

### User Story 5 — Admin Sets Document Expiry During Approval (Priority: P1)

When an admin approves a vendor document in the Filament admin panel, they can set the `expires_at` date and toggle `is_critical` for that document type. This is a direct edit on the `vendor_documents` form.

**Why this priority**: Expiry data entry is the starting point for the entire lifecycle — without it, no reminders or suspensions can fire. It must be integrated into the existing approval workflow, not a separate screen.

**Acceptance Scenarios**:

1. **Given** an admin is on the vendor document approval form, **When** they set `expires_at = 2027-05-04` and `is_critical = true` and save, **Then** the document row has those values persisted.
2. **Given** an admin sets `expires_at` to a past date, **Then** the form rejects with a validation error (expiry must be in the future).
3. **Given** a document with `is_critical=false`, **When** it expires, **Then** only a notification is sent — no auto-suspension.

---

### Edge Cases

- What happens when a vendor has two critical documents and one expires? → Only the expired one triggers suspension; the other is unaffected. The vendor remains suspended until the expired doc's grace period is granted or a renewed doc is uploaded and approved.
- What happens when `CheckDocumentExpiryCommand` is run multiple times in the same day? → Idempotency via `last_reminder_sent_at` prevents duplicate notifications. The command is safe to run multiple times.
- What if `expires_at` is NULL on an `is_critical` document? → No lifecycle action is taken; it is treated as "no expiry set" and is excluded from all checks.
- What if a vendor's docs have multiple expiry levels hitting on the same day (e.g., 30-day and 14-day for different docs)? → Each doc triggers its own notification independently.
- What if the daily job fails mid-run? → Each document is processed atomically; failures for one document do not block others. Compliance events are only written after successful action completion.
- Timezone: all expiry comparisons use UTC server time; `expires_at` is stored as a `DATE` column and compared against `today()` in UTC.

---

## Requirements *(mandatory)*

### Functional Requirements

**Migration & Schema**

- **FR-001**: System MUST add `expires_at DATE NULL`, `is_critical TINYINT(1) NOT NULL DEFAULT 0`, and `last_reminder_sent_at DATE NULL` columns to the existing `vendor_documents` table.
- **FR-002**: System MUST create a new append-only `vendor_compliance_events` table with `(id, public_id, vendor_profile_id FK, event_type ENUM, document_id FK nullable, occurred_at TIMESTAMP, admin_id FK nullable, reason JSON nullable)` and no `updated_at`, no `softDeletes()`.

**Scheduled Command**

- **FR-003**: System MUST provide a `CheckDocumentExpiryCommand` that runs daily at 02:00 UTC and processes ALL vendor documents with a non-NULL `expires_at`.
- **FR-004**: For each document, the command MUST send reminder notifications at exactly 30, 14, 7, and 1 day(s) before `expires_at`, guarded by `last_reminder_sent_at` to prevent duplicates.
- **FR-005**: For each `is_critical=true` document where `expires_at <= today`, the command MUST invoke `AutoSuspendForExpiredDocAction` to suspend the vendor and log a `auto_suspended` compliance event.
- **FR-006**: For each `is_critical=false` document where `expires_at <= today`, the command MUST send a `vendor.doc_expired` notification — no suspension.

**Actions**

- **FR-007**: `SetDocumentExpiryAction` MUST accept a `VendorDocument`, `expires_at` date, and `is_critical` bool; persist both fields; and return the updated document. Must reject past expiry dates.
- **FR-008**: `AutoSuspendForExpiredDocAction` MUST set `vendor_profiles.suspension_reason` to a translatable JSON value referencing the expired document, fire a `VendorAutoSuspended` domain event `DB::afterCommit`, and write a `vendor_compliance_events` record atomically within one `DB::transaction`.
- **FR-009**: `GrantDocGracePeriodAction` MUST un-suspend the vendor, extend `vendor_documents.expires_at` by 14 days from today, write a `manually_overridden` compliance event (with `admin_id` + bilingual `reason`), fire a `VendorGracePeriodGranted` domain event `DB::afterCommit`, and notify the vendor.

**Notification Templates**

- **FR-010**: System MUST provide EN + AR notification templates for: `vendor.doc_expiring_30d`, `vendor.doc_expiring_14d`, `vendor.doc_expiring_7d`, `vendor.doc_expiring_1d`, and `vendor.doc_expired` — seeded via `ChangeRequestNotificationTemplateSeeder` pattern or dedicated seeder.

**Filament Admin**

- **FR-011**: The `VendorComplianceWidget` MUST display on the ops dashboard (Phase 8.1) with two sections: (a) "Documents expiring this month" listing vendor name, doc type, days remaining; (b) "Auto-suspended vendors" listing vendor name, suspension date.
- **FR-012**: The `ExpiredDocsResource` queue page MUST list all documents with `expires_at <= today + 7 days` or `is_critical` documents past expiry, with per-row Filament actions: "Renew" (opens SetDocumentExpiryAction form), "Grant Grace Period" (opens GrantDocGracePeriodAction form with reason field), and "Suspend Manually" (opens AutoSuspendForExpiredDocAction confirmation).
- **FR-013**: All Filament forms that touch `vendor_documents` (including existing approval forms) MUST show `expires_at` and `is_critical` fields. Validation: `expires_at` must be in the future when being set.

### Key Entities

- **VendorDocument**: Existing entity extended with `expires_at DATE`, `is_critical BOOL`, `last_reminder_sent_at DATE`. Owned by Identity module.
- **VendorComplianceEvent**: New append-only entity. Records every compliance lifecycle event (reminder sent, expired, auto-suspended, manually overridden). `event_type` is an enum. `admin_id` is nullable (NULL for system-generated events). `reason` is a translatable JSON column (EN+AR). Owned by Identity module.
- **CheckDocumentExpiryCommand**: Laravel console command. No model state — delegates to Actions.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Vendors receive reminder notifications at all four checkpoints (30d, 14d, 7d, 1d) with zero duplicate dispatches on the same day, confirmed by Pest time-travel tests.
- **SC-002**: 100% of `is_critical` documents that pass their `expires_at` date trigger an auto-suspension within 24 hours (next daily job run), confirmed by integration test.
- **SC-003**: Admin grace period override is auditable — every `manually_overridden` event has a non-null `admin_id` and bilingual `reason` (EN + AR both non-empty), enforced by database constraints and form validation.
- **SC-004**: Auto-suspension does not affect any existing bookings in progress — confirmed by a Pest test asserting existing `bookings.lifecycle_status` is unchanged after suspension.
- **SC-005**: All Pest tests pass green, including 30d/14d/7d reminder cascade, critical-doc expiry auto-suspend, non-critical expiry (notification only), and grace period override.
- **SC-006**: Admin dashboard widgets display accurate real-time counts (within 24-hour job cadence) without requiring a page reload beyond normal cache TTL.

---

## Assumptions

- The `vendor_profiles` table already has a `suspension_reason` column or similar mechanism used by the existing `AutoSuspendVendorAction` (from Phase 1.1). If not, `suspension_reason JSON NULL` will be added via migration in this feature.
- Notification dispatch uses the existing `Communication` module's `DispatchNotificationAction` — no new notification infrastructure is required.
- Grace period is always exactly 14 days in Phase 1; configurable durations are deferred to Phase 1.5.
- `vendor.doc_expiring_1d` fires on the calendar day before `expires_at` — i.e., when `expires_at = today + 1`.
- All five notification templates send via `email` and `in_app` channels by default; `sms`/`push` channels are not required for Phase 1 compliance notifications.
- The `VendorComplianceWidget` is added to the existing ops dashboard (Phase 8.1) via a widget class registered in the Identity module's Filament provider — it does NOT require Phase 8.1 to be complete; it will be visible once both features ship.
- Non-critical expired documents (reminders only) do not appear in the `ExpiredDocsResource` suspension queue — they appear only if admin manually decides to suspend.
- Per the Constitution §V (Append-Only Tables), `vendor_compliance_events` MUST NOT have `updated_at` or `softDeletes()`.
- The `reason` field on `vendor_compliance_events` uses the same JSON translatable pattern `{"en": "...", "ar": "..."}` as all other translatable columns — both locales are required when written by admin; system-generated events (reminders, auto-suspend) use a pre-defined translatable reason.

---

## Cut-List (Deferred to Phase 1.5)

- Auto-renewal via uploaded new doc + auto-approve heuristics (e.g., doc type + new expiry logic)
- Per-doc-type expiry rules (CR = 1 year, IBAN = no expiry, tax card = 2 years) — Phase 1 uses manual entry only
- Configurable grace period duration (currently hardcoded to 14 days)
- Multi-document expiry aggregation logic (e.g., "suspend only after ALL critical docs expire")

---

## Constitution Check

| Principle | Applies? | How Satisfied |
|---|---|---|
| I — Modular monolith | ✅ | All new classes in `app/Modules/Identity/` — compliance events and actions stay within Identity module boundaries |
| II — Three product types | ❌ | Not type-aware — compliance lifecycle applies to vendor identity, not product types |
| III — Money discipline | ❌ | No money columns in this feature |
| IV — Bilingual EN+AR | ✅ | `vendor_compliance_events.reason` is JSON translatable; all notification templates require EN+AR; Filament forms use translatable plugin |
| V — Append-only tables | ✅ | `vendor_compliance_events` has no `updated_at`, no `softDeletes()` — append-only as specified |
| VI — ADR before code | ✅ | ADR-0021-vendor-doc-expiry.md MUST be written and accepted before migrations are generated |
| VII — Test-first | ✅ | Pest tests for all 4 scenarios written same day as Actions |
| VIII — Idempotency | ✅ | `CheckDocumentExpiryCommand` is idempotent via `last_reminder_sent_at` guard; no HTTP state-changing endpoints introduced |
| IX — Events `DB::afterCommit` | ✅ | `VendorAutoSuspended` and `VendorGracePeriodGranted` events fire via `DB::afterCommit` in their Actions |
| X — Two-step vendor gate | ✅ | Auto-suspension sets `vendor_profiles.suspension_reason` consistent with the existing suspension mechanism from ADR-0003 §6.2 |
| XI — Document storage | ✅ | No new file storage — extending existing `vendor_documents` metadata only |
