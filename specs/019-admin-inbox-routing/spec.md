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

# Feature Specification: Admin Inbox Routing

**Feature Branch**: `019-admin-inbox-routing`  
**Created**: 2026-05-04  
**Status**: Draft  
**Phase ID**: Phase 6.1 — Admin Ops Dashboard + Inbox Routing  
**PRD Coverage**: FR-28 (Admin monitoring of critical cases), FR-29 (Admin alert routing), FR-30 (Audit trail for admin actions)  
**ADR Required**: None — extends Communication module; no new bounded context introduced  
**Depends On**: Phase 5.0 (Communication + Notifications), Phase 8.1 (Ops Dashboard)  
**Tables Touched**: `admin_inbox_items` (NEW), `admin_inbox_routing_rules` (NEW), `audit_logs` (existing — append-only)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Event Routed to Admin Inbox (Priority: P1)

A critical system event occurs (vendor registers, booking stalls, payment fails, chat is flagged, withdrawal is requested, service is submitted for review). The platform automatically routes an inbox item to every admin whose role matches the active routing rules for that event key. Each matched admin sees a new unread item in their personal inbox.

**Why this priority**: Without routing, critical operational events go unnoticed. This is the core flow that all other inbox actions depend on.

**Independent Test**: Can be fully tested by firing `VendorRegistered` and asserting that all admins with the `vendor-manager` role have a new `unread` inbox item — delivers a verifiable, standalone monitoring capability.

**Acceptance Scenarios**:

1. **Given** a routing rule exists for `vendor.registered` → role `vendor-manager` is active, **When** `VendorRegistered` is dispatched, **Then** every admin with role `vendor-manager` receives an `unread` inbox item with matching `event_key`, `severity`, `title_en`, and `title_ar`.
2. **Given** no active routing rule matches an event key, **When** the event is dispatched, **Then** no inbox items are created and no error is raised.
3. **Given** a routing rule targets a specific admin ID (not a role), **When** the matching event fires, **Then** only that specific admin receives the item.
4. **Given** a routing rule is inactive (`is_active = false`), **When** the matching event fires, **Then** no inbox items are created for that rule.

---

### User Story 2 — Admin Reads and Acknowledges an Inbox Item (Priority: P1)

An admin opens their inbox, reads an item (status transitions to `read`), reviews the linked source entity, then marks it as `resolved`. An audit log row is written for each status transition.

**Why this priority**: Reading and resolving items is the primary workflow — without it, the inbox is a dead-letter queue.

**Independent Test**: Can be fully tested end-to-end by opening an existing unread item, asserting `read` status, then resolving and asserting `resolved` status plus one `audit_logs` row per transition.

**Acceptance Scenarios**:

1. **Given** an unread inbox item assigned to an admin, **When** the admin opens (views) the item, **Then** its status becomes `read` and the item is no longer counted in the unread badge.
2. **Given** a `read` inbox item, **When** the admin triggers `Acknowledge/Resolve`, **Then** the status becomes `resolved` and one `audit_logs` row is appended recording `(actor_id, action="inbox_item_resolved", subject_type, subject_id)`.
3. **Given** an inbox item owned by admin A, **When** admin B tries to resolve it, **Then** a 403 authorization error is returned.

---

### User Story 3 — Admin Snoozes an Inbox Item (Priority: P2)

An admin decides an inbox item needs no immediate action. They snooze it for a standard duration (1h, 4h, 24h). The item disappears from the active inbox view until `snoozed_until` passes, at which point it resurfaces with status `unread`.

**Why this priority**: Snooze prevents clutter and avoids notification fatigue without permanently discarding alerts.

**Independent Test**: Can be fully tested by snoozing an item, asserting status = `snoozed` and `snoozed_until` is set, then asserting the item reappears in the active view once `snoozed_until` is in the past.

**Acceptance Scenarios**:

1. **Given** an unread or read inbox item, **When** the admin snoozes it for 4 hours, **Then** its status becomes `snoozed`, `snoozed_until` is set to `now + 4h`, and the item is excluded from the default active inbox list.
2. **Given** a snoozed item whose `snoozed_until` timestamp has passed, **When** the inbox list is queried, **Then** the item reappears with status `unread`.
3. **Given** the snooze wake-up scheduled job runs, **When** it processes expired snoozes, **Then** all items with `snoozed_until < now()` are reset to `unread`.

---

### User Story 4 — Admin Reassigns an Inbox Item (Priority: P2)

An admin cannot or should not handle a particular inbox item themselves. They reassign it to another admin. The original item is marked `reassigned`; the target admin receives a new `unread` inbox item derived from the same source event. An audit log row is written.

**Why this priority**: Reassignment enables team escalation and prevents items from being silently dropped.

**Independent Test**: Can be fully tested by asserting the original item status becomes `reassigned`, a new inbox item exists for the target admin, and an `audit_logs` row is written.

**Acceptance Scenarios**:

1. **Given** an inbox item assigned to admin A, **When** admin A reassigns it to admin B, **Then** the original item's status becomes `reassigned` and `assigned_to_admin_id` is set to admin B's ID, and a new `unread` inbox item is created for admin B with the same `source_type` and `source_id`.
2. **Given** a reassignment, **Then** one `audit_logs` row is appended recording `(actor_id=adminA, action="inbox_item_reassigned", subject=new_item_for_adminB)`.
3. **Given** admin A attempts to reassign an item to a non-admin user, **Then** a validation error is returned.

---

### User Story 5 — Batch Resolve Inbox Items (Priority: P3)

An admin selects multiple inbox items and resolves them in one action. All selected items transition to `resolved` and audit rows are written for each.

**Why this priority**: Batch operations reduce friction during high-volume events (e.g., mass vendor registrations).

**Independent Test**: Select 3 unread items, trigger batch resolve, assert all 3 are `resolved` and 3 audit rows exist.

**Acceptance Scenarios**:

1. **Given** multiple inbox items owned by the current admin, **When** they select all and trigger "Batch Resolve", **Then** every selected item status becomes `resolved` and one audit log row per item is appended.
2. **Given** a batch that includes an item owned by a different admin, **When** the current admin attempts batch resolve, **Then** only the items belonging to the current admin are resolved; the others are skipped without error.

---

### User Story 6 — Super-Admin Manages Routing Rules (Priority: P2)

A super-admin can view, create, edit, and toggle active/inactive routing rules. Each rule maps an `event_key` + `severity` to a `role_id` or a specific `admin_id`. Rules take effect immediately on the next matching event.

**Why this priority**: Without rule management, routing is hardcoded and cannot adapt to team changes.

**Independent Test**: Create a new routing rule, fire the matching event, assert inbox items are created per the new rule.

**Acceptance Scenarios**:

1. **Given** a super-admin creates a routing rule `(event_key="booking.stalled", severity="high", route_to_role_id=<ops-manager-role>)`, **When** `BookingStalled` fires next, **Then** all ops-manager admins receive an inbox item.
2. **Given** a super-admin sets a rule to `is_active = false`, **When** the matching event fires, **Then** no inbox items are created for that rule.

---

### Edge Cases

- What happens when an admin is deleted while having unread inbox items? Items remain for audit purposes; the admin's items are not reassigned automatically (manual escalation via super-admin).
- What happens when the same event fires simultaneously for the same admin (race condition on bulk routing)? Each insert uses a DB transaction; duplicate detection is handled by unique constraint on `(source_type, source_id, admin_id)` if the same event routes to the same admin via multiple overlapping rules — only one item is created (upsert or ignore-on-conflict).
- What happens if `snoozed_until` is in the past when the snooze job hasn't run yet? The item stays `snoozed` in the DB but the query filter for active inbox items checks both status AND `snoozed_until < now()`, so it reappears immediately without waiting for the job.
- What happens when a routing rule routes to a role that has no members? `RouteToAdminInboxAction` resolves role → admins. If the result is empty, no inbox items are created and no error is raised.
- What happens when `severity` on the routing rule doesn't match the event's severity? Rules filter on exact severity match; mismatched rules are skipped.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST automatically route inbox items to matching admins whenever one of the following domain events fires: `VendorRegistered`, `BookingStalled`, `PaymentFailed`, `ChatFlagged`, `WithdrawalRequested`, `ServiceSubmittedForReview`.
- **FR-002**: System MUST evaluate all active routing rules and create one inbox item per matched admin per event, using a single DB transaction per routing batch.
- **FR-003**: Each inbox item MUST carry: source entity reference (`source_type`, `source_id`), `severity` (info / warning / critical), bilingual title and body (`title_en`, `title_ar`, `body_en`, `body_ar`), and initial `status = unread`.
- **FR-004**: Admin MUST be able to mark an inbox item as `read` (opening/viewing it), `resolved` (explicit action), `snoozed` (with configurable duration), or `reassigned` (to another admin).
- **FR-005**: System MUST write one append-only `audit_logs` row for every status transition: read, resolved, snooze, reassign.
- **FR-006**: Snoozed items MUST be excluded from the active inbox list query until `snoozed_until < now()`, at which point they reappear as `unread`.
- **FR-007**: A scheduled job MUST reset expired snoozed items (`snoozed_until < now()`) back to `unread`.
- **FR-008**: Reassigning an item MUST create a new `unread` inbox item for the target admin derived from the same source event, and MUST set the original item's status to `reassigned`.
- **FR-009**: Batch resolve MUST transition all selected items owned by the current admin to `resolved` in a single transaction, with one audit row per item.
- **FR-010**: Super-admin MUST be able to create, update, and toggle active/inactive routing rules via Filament.
- **FR-011**: The Filament admin bell icon MUST display a real-time unread badge count scoped to the authenticated admin.
- **FR-012**: Routing rules MUST support routing to a role (all current members) OR to a specific admin ID (not both on the same rule).
- **FR-013**: `admin_inbox_items` table MUST have a unique constraint on `(source_type, source_id, admin_id)` to prevent duplicate routing from overlapping rules.
- **FR-014**: All inbox item titles and bodies MUST be stored in both EN and AR; empty string for either locale is a validation failure.

### Key Entities

- **AdminInboxItem**: Represents a single routed alert for a specific admin. Tracks status lifecycle (`unread → read → snoozed / reassigned / resolved`), source entity reference, severity, bilingual content, snooze timestamp, and target admin for reassignment. Append-only status history via `audit_logs`. NOT soft-deleted (items are resolved, not deleted).
- **AdminInboxRoutingRule**: Configuration record mapping `(event_key, severity)` to either `route_to_role_id` or `route_to_admin_id`. Togglable (`is_active`). Evaluated at event-dispatch time by `RouteToAdminInboxAction`.
- **RouteToAdminInboxAction**: Cross-module Application Action that listens to domain events, resolves active rules, expands role → admin members, and inserts inbox items in a single transaction.
- **AcknowledgeInboxItemAction**: Transitions an item to `read`.
- **SnoozeInboxItemAction**: Transitions an item to `snoozed` with a `snoozed_until` timestamp.
- **ReassignInboxItemAction**: Marks original item `reassigned`, creates a new `unread` item for target admin.
- **ResolveInboxItemAction**: Transitions an item to `resolved`; writes audit log.
- **BatchResolveInboxItemsAction**: Bulk-resolves selected items owned by current admin.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of configured event types (`VendorRegistered`, `BookingStalled`, `PaymentFailed`, `ChatFlagged`, `WithdrawalRequested`, `ServiceSubmittedForReview`) route inbox items to the correct admin role within 1 minute of event dispatch (queue worker latency).
- **SC-002**: The admin unread badge count is accurate to ±0 — it reflects the exact number of active unread items for that admin at any given time.
- **SC-003**: All Pest tests green — vendor registration test, snooze-until test, reassign-audit test.
- **SC-004**: Snooze expiry is self-healing — an item snoozed 4 hours ago reappears in the inbox without any manual intervention beyond the scheduled job running.
- **SC-005**: Batch resolve of 50 items completes without timeout or partial-commit failure.
- **SC-006**: Every status transition (read, resolved, snooze, reassign) produces exactly one `audit_logs` row — no duplicates, no missing entries.
- **SC-007**: The inbox and routing-rules UI is fully navigable in both EN and AR without layout breakage.

---

## Assumptions

- Admin roles are managed by `spatie/laravel-permission` — `RouteToAdminInboxAction` resolves role members via `Role::findByName(...)->users`.
- The `Communication` module already exists (Phase 5.0) and owns the `notification_templates` table; this feature extends `Communication` with the admin inbox sub-domain rather than creating a new module.
- `admin_inbox_items` is NOT append-only: status column is mutable (unread → read → snoozed → resolved / reassigned). However, status history is captured in `audit_logs` (append-only).
- The scheduled job for snooze wake-up runs via Laravel's task scheduler every 5 minutes.
- Severity levels: `info`, `warning`, `critical` — stored as ENUM. Routing rules filter on exact severity match.
- The unique constraint `(source_type, source_id, admin_id)` prevents duplicate items for the same source entity + admin. If a second routing rule also matches, the insert is silently ignored (no second item for the same admin for the same source).
- Email digest of unread inbox items is explicitly deferred to Phase 1.5 (out of scope).
- Mobile push of inbox items to admin app is explicitly deferred to Phase 1.5 (out of scope).
- `assigned_to_admin_id` on `admin_inbox_items` is only set on `reassigned` items; for items routed from rules it is NULL (the item *belongs to* the admin it was created for).
- The Filament bell badge uses a `StatsOverviewWidget` or a custom header widget querying `AdminInboxItem::query()->where('admin_id', auth()->id())->whereIn('status', ['unread'])->count()`.
