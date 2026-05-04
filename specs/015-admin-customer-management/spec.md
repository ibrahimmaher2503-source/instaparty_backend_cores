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

# Feature Specification: Admin Customer Management

**Feature Branch**: `015-admin-customer-management`
**Phase**: Phase 6.3 — Admin Customer Management (Week 7)
**Created**: 2026-05-03
**Status**: Draft
**PRD Coverage**: FR-22 (Admin dashboard), FR-25 (User management), FR-26 (Audit log access)
**Tables Touched**: `users`, `customer_profiles`, `customer_addresses`, `bookings`, `service_reviews`, `vendor_reviews`, `loyalty_ledger`, `loyalty_programs`, `audit_logs` (read-only — no schema changes)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Find and Inspect a Customer (Priority: P1)

A super-admin or customer-manager needs to locate any customer quickly by phone, email, or name, and view their full profile footprint: personal info, addresses, booking history, reviews written, loyalty balances per vendor, and recent activity.

**Why this priority**: The primary operational need. Everything else builds on being able to find and read a customer record.

**Independent Test**: An admin can search for a customer by email, open their profile, and see the Overview tab with name, phone, email, status, and join date — without any other tab needing to work.

**Acceptance Scenarios**:

1. **Given** a logged-in super-admin, **When** they navigate to Customers and type a partial email in the search bar, **Then** matching customers appear within 2 seconds.
2. **Given** a customer record is open, **When** the admin views the Overview tab, **Then** they see: name, phone, email, account status (active/suspended), join date, last login, total bookings count, and total spend.
3. **Given** a customer record is open, **When** the admin opens the Bookings tab, **Then** they see a paginated list of all bookings with date, items, total amount, and lifecycle/payment/fulfillment status.
4. **Given** a customer record is open, **When** the admin opens the Reviews tab, **Then** they see all service reviews and vendor reviews the customer has written, with service name and rating.
5. **Given** a customer record is open, **When** the admin opens the Wallet tab, **Then** they see loyalty balances grouped per vendor program with current points balance.
6. **Given** a customer record is open, **When** the admin opens the Addresses tab, **Then** they see all saved customer addresses.
7. **Given** a customer record is open, **When** the admin opens the Activity tab, **Then** they see a chronological audit log of actions performed by or on that user account.

---

### User Story 2 — Edit Customer Profile Fields (Priority: P2)

An admin may need to correct or update basic customer profile information (display name, phone) that a customer has reported as incorrect.

**Why this priority**: Operational support task. Needed less frequently than read-only inspection, but essential for support workflows.

**Independent Test**: Admin opens a customer profile, clicks Edit, changes the display name, saves — the new name appears in the list and overview tab.

**Acceptance Scenarios**:

1. **Given** an admin with edit permissions, **When** they click "Edit Profile" on a customer record, **Then** a form opens with editable fields: display name (EN + AR), phone number.
2. **Given** the admin submits the edit form with valid data, **Then** the profile is updated and an audit log entry is created recording who changed what.
3. **Given** the admin submits the edit form with an empty display name, **Then** a validation error is shown and no change is persisted.
4. **Given** a customer-manager role (not super-admin), **When** they try to access the edit action, **Then** they are shown only if they have the `update_customer_profile` permission — access is denied otherwise.

---

### User Story 3 — Suspend / Unsuspend a Customer (Priority: P2)

An admin needs to suspend a customer who has violated platform rules, blocking their ability to log in and make new bookings. The suspension must be reversible.

**Why this priority**: Critical for platform safety. Must work end-to-end before the feature is considered done.

**Independent Test**: Admin suspends customer X → X cannot log in → admin unsuspends X → X can log in again.

**Acceptance Scenarios**:

1. **Given** an active customer, **When** an admin clicks "Suspend Customer" and confirms, **Then** the customer's account status changes to `suspended`, they are force-logged-out (all tokens revoked), and an audit log entry is created.
2. **Given** a suspended customer, **When** they attempt to log in via the API, **Then** they receive a `403` response with a localized "account suspended" message in EN and AR.
3. **Given** a suspended customer record, **When** the admin clicks "Unsuspend Customer" and confirms, **Then** the customer's account status returns to `active` and they can log in again.
4. **Given** an admin attempts to suspend an already-suspended customer, **Then** the action is blocked with an appropriate message.
5. **Given** the suspension action fires, **Then** an audit log entry is written with: actor (admin user ID), target (customer user ID), action (`customer.suspended`), timestamp.

---

### User Story 4 — Force Logout a Customer (Priority: P3)

An admin can revoke all active Sanctum tokens for a customer without suspending their account — useful when a security incident is suspected.

**Why this priority**: Safety tool. Less common than suspend but important for incident response.

**Independent Test**: Admin force-logs-out customer X → all X's API tokens are gone → X must re-authenticate to use the app.

**Acceptance Scenarios**:

1. **Given** a customer with active API tokens, **When** an admin clicks "Force Logout" and confirms, **Then** all of the customer's Sanctum tokens are deleted and an audit entry is written.
2. **Given** a customer's tokens have been force-revoked, **When** they make an API request with a previously valid token, **Then** they receive a `401 Unauthenticated` response.
3. **Given** a customer with no active tokens, **When** an admin clicks "Force Logout", **Then** the action completes silently (no tokens to revoke) and a success notification is shown.

---

### Edge Cases

- What happens when admin searches a name that matches hundreds of customers? Pagination must be present; results must be limited per page with server-side search.
- What happens if a customer has zero bookings, zero reviews, or zero loyalty? Each tab displays an empty-state message rather than an error.
- What happens if an admin without `suspend_customer` permission tries to suspend? The action button must not be visible (hidden by Shield permissions), and the backend also returns `403` if called directly.
- What happens if a super-admin accidentally suspends themselves? The action MUST be blocked — an admin cannot suspend their own account.
- What happens when the audit log is empty for a user? The Activity tab shows an empty-state, not a 500 error.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide an admin Filament resource for customers under the "Users" navigation group, visible only to users with `super-admin` or `customer-manager` role.
- **FR-002**: The list view MUST support search by customer name (partial match), phone number (partial match), and email address (partial match).
- **FR-003**: The list view MUST display: customer name, email, phone, account status badge (active / suspended), join date, and total booking count.
- **FR-004**: The customer detail view MUST be organized into tabs: Overview, Bookings, Reviews, Wallet, Addresses, Activity.
- **FR-005**: The Overview tab MUST show: full name (EN + AR), email, phone, account status, join date, last login timestamp, total bookings count, and total lifetime spend (sum of all confirmed booking totals, displayed in EGP).
- **FR-006**: The Bookings tab MUST show a paginated list of all bookings linked to the customer with booking date, booking public ID, item count, lifecycle status, payment status, and total amount.
- **FR-007**: The Reviews tab MUST show all service reviews and vendor reviews written by the customer, with service/vendor name, star rating, and review date.
- **FR-008**: The Wallet tab MUST show loyalty points balances per vendor loyalty program the customer has participated in, including program name, vendor name, and current point balance.
- **FR-009**: The Addresses tab MUST show all customer addresses (label, full address, governorate/city).
- **FR-010**: The Activity tab MUST show a paginated feed of audit log entries where `subject_type = 'App\Modules\Identity\Domain\Models\User'` and `subject_id = {customer_id}`, ordered newest first.
- **FR-011**: Admins MUST be able to edit customer profile fields (display name EN + AR, phone number) via an inline edit action that writes an audit log entry on save.
- **FR-012**: Admins MUST be able to suspend a customer; this action MUST atomically: set account to suspended AND revoke all active Sanctum tokens for the customer.
- **FR-013**: Admins MUST be able to unsuspend a previously suspended customer.
- **FR-014**: Admins MUST be able to force-logout a customer (revoke all tokens) without changing account status.
- **FR-015**: Every admin action (edit, suspend, unsuspend, force logout) MUST write an entry to the `audit_logs` table.
- **FR-016**: A customer whose account is `suspended` MUST receive a `403` response with a bilingual error message when attempting to authenticate or use a protected API endpoint.
- **FR-017**: An admin MUST NOT be able to suspend their own account.
- **FR-018**: All actions (suspend, unsuspend, force logout) MUST require confirmation before execution.
- **FR-019**: Authorization MUST use `filament-shield` permissions: `view_customer`, `view_any_customer`, `update_customer_profile`, `suspend_customer`, `force_logout_customer`.

### Key Entities

- **Customer**: A `users` record with role `customer`, linked to a `customer_profiles` record. Has associated bookings, reviews, addresses, and loyalty ledger entries.
- **Audit Log Entry**: An append-only record in `audit_logs` capturing who did what to which customer and when.
- **Sanctum Token**: A `personal_access_tokens` row. Force logout = `DELETE WHERE tokenable_id = {user_id}`.
- **Loyalty Balance per Vendor**: Derived by summing `loyalty_ledger.points` grouped by `loyalty_program_id` for a given `user_id`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can locate any customer by name, email, or phone in under 5 seconds from the Customers list page.
- **SC-002**: An admin can view a customer's complete footprint (all 6 tabs) without leaving the customer detail page.
- **SC-003**: Suspension takes effect immediately — a suspended customer cannot complete a login attempt after the action is confirmed.
- **SC-004**: Force logout takes effect immediately — all previously issued tokens stop working within the same request cycle as the action.
- **SC-005**: Every destructive action (suspend, force logout) is traceable in the audit log within 1 second of completion.
- **SC-006**: Admins without the relevant permission cannot see or execute restricted actions (buttons not rendered, backend returns `403`).

---

## Assumptions

- No schema changes are required; all data is read from existing tables (`users`, `customer_profiles`, `customer_addresses`, `bookings`, `booking_items`, `service_reviews`, `vendor_reviews`, `loyalty_ledger`, `loyalty_programs`, `audit_logs`, `personal_access_tokens`).
- `users.status` column already exists with at least `active` and `suspended` values; if not, this feature adds only that column to `users` (confirmed from DB schema spec §Identity).
- The `audit_logs` table is already populated by existing actions; this feature only reads from it (Activity tab) and writes to it (admin actions).
- `spatie/laravel-activitylog` is the installed package for audit logging (confirmed in `10_Package_List.md`).
- The `filament-shield` package is already installed and generating permissions.
- `personal_access_tokens` is the standard Laravel Sanctum tokens table; revocation is done via `$user->tokens()->delete()`.
- The loyalty balance displayed is a read-only derived value — no write operations on `loyalty_ledger` from this feature.
- Bookings shown in the customer detail are the customer's own bookings only — admin cannot create or modify bookings from this page.
- The feature does not expose customer addresses to editing — addresses are owned and managed by the customer.
- Customer-manager is a named Spatie role already defined or to be added in this phase.
