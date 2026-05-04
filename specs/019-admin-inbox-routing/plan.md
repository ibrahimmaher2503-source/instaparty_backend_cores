# Implementation Plan: Admin Inbox Routing

**Spec**: [spec.md](./spec.md)  
**Phase**: 6.1 — Admin Ops Dashboard + Inbox Routing  
**Module**: `Communication` (extends existing module — no new bounded context)  
**ADR Required**: None (spec confirmed no new module boundary; extends Communication)  
**Estimated effort**: 1 day (Day 1 only — single phase per spec)  
**Created**: 2026-05-04

---

## Constitution Check

| Principle | Applies? | How Satisfied |
|---|---|---|
| I — Modular Monolith | ✅ | New classes live in `app/Modules/Communication/` — no cross-module Model imports; Catalog/Booking/Identity events consumed via event-listener contracts, not direct model access |
| II — Three Product Types | N/A | Inbox routing is product-type-agnostic; no `match($enum)` needed; spec confirmed |
| III — Money Discipline | N/A | No money columns in this feature |
| IV — Bilingual EN+AR | ✅ | `admin_inbox_items.title` and `.body` are JSON translatable columns; both locales required; Filament uses translatable plugin tabs |
| V — Append-Only Tables | ✅ | `admin_inbox_items` is NOT append-only (status is mutable); `audit_logs` IS append-only and used for status history — no attempt to add soft-delete or mutability to audit_logs |
| VI — ADR Before Code | ✅ | No new module — ADR not required; spec explicitly states "None (extends notifications module)" |
| VII — Test-First | ✅ | 3 Pest feature tests specified in spec; tests written same day |
| VIII — Idempotency | N/A | Inbox mutations are admin-only internal actions; no payment-mutating endpoints in this feature |
| IX — Events After Commit | ✅ | `RouteToAdminInboxAction` fires inside `DB::transaction`; any domain events from this action fire via `DB::afterCommit()` |
| X — Vendor Approval Gate | N/A | Feature is admin-internal; does not touch vendor approval flow |
| XI — Document Storage | N/A | No file uploads in this feature |

---

## Event Gap Analysis

The spec references 6 triggering events. Three exist; three must be created as thin stub events:

| Event | Status | Module | Resolution |
|---|---|---|---|
| `VendorRegistered` | ✅ Exists | Identity | Use directly |
| `PaymentFailed` | ✅ Exists | Payments | Use directly |
| `WithdrawalRequested` | ✅ Exists | Settlement | Use directly |
| `BookingStalled` | ❌ Missing | Booking | Create stub — maps to "vendor SLA expired" scenario (sibling of `VendorResponseTimedOut`); fire from Booking module's SLA job |
| `ChatFlagged` | ❌ Missing | Communication | Create stub — fired when a chat moderation flag is raised (stub only; full chat moderation is Phase 1.5) |
| `ServiceSubmittedForReview` | ❌ Missing | Catalog | Create stub — fired when a service transitions to `pending_review` status |

**Decision**: Create 3 stub event classes in their home modules. Each carries only the minimum payload: `$sourceId` (the entity's BIGINT id). Wire them to `RouteToAdminInboxAction` in `CommunicationServiceProvider`. Document them as stubs pending full feature integration in a later phase.

---

## Tables to Create

### Table 1 — `admin_inbox_routing_rules`

**Purpose**: Admin-configurable rules mapping an event key + severity to a target role or specific admin. Evaluated at dispatch time by `RouteToAdminInboxAction`.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | Internal PK |
| `public_id` | CHAR(26) UNIQUE | ULID — exposed in Filament route model binding |
| `event_key` | VARCHAR(100) | e.g. `vendor.registered`, `payment.failed` |
| `severity` | ENUM(info,warning,critical) | Must match event severity at dispatch time |
| `route_to_role_id` | BIGINT UNSIGNED NULL FK→roles | NULL when routing to specific admin |
| `route_to_admin_id` | BIGINT UNSIGNED NULL FK→users | NULL when routing to role |
| `is_active` | BOOLEAN default true | Toggle without deleting the rule |
| `created_by` | BIGINT UNSIGNED NULL FK→users | Audit: who created this rule |
| `updated_by` | BIGINT UNSIGNED NULL FK→users | Audit: who last edited |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

**Indexes**:
- `(event_key, severity, is_active)` — the primary lookup query in `RouteToAdminInboxAction`

**Constraint**: exactly one of `route_to_role_id` / `route_to_admin_id` is non-null — enforced at application layer (Action + FormRequest validation), not DB CHECK (avoids raw SQL in migration).

**Soft deletes**: No — rules are toggled, not deleted.

---

### Table 2 — `admin_inbox_items`

**Purpose**: One routed alert item per admin per source event. Status is mutable; history captured in `audit_logs`.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `admin_id` | BIGINT UNSIGNED FK→users (restrict) | Owner — the admin this item is routed to |
| `source_type` | VARCHAR(100) | Polymorphic class or slug, e.g. `vendor_profile` |
| `source_id` | BIGINT UNSIGNED | ID of the source entity |
| `severity` | ENUM(info,warning,critical) | Copied from the routing rule that fired |
| `title` | JSON | `{"en":"...", "ar":"..."}` — spatie/laravel-translatable |
| `body` | JSON | `{"en":"...", "ar":"..."}` — spatie/laravel-translatable |
| `status` | ENUM(unread,read,snoozed,resolved,reassigned) default unread | Mutable status |
| `snoozed_until` | TIMESTAMP NULL | Set on snooze; NULL otherwise |
| `assigned_to_admin_id` | BIGINT UNSIGNED NULL FK→users (null on delete) | Set only on `reassigned` items — records the target |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

**Indexes**:
- `(admin_id, status)` — main inbox list query
- `(admin_id, status, snoozed_until)` — snoozed items filter (status=snoozed AND snoozed_until < now)
- `(status, snoozed_until)` — scheduled job: reset expired snoozes
- `(source_type, source_id, admin_id)` UNIQUE — prevents duplicate items from overlapping routing rules

**Soft deletes**: No — items are resolved, not deleted.

---

## Tables Modified

| Table | Module | Change |
|---|---|---|
| `audit_logs` | Cross-cutting | Receives new rows (append-only — no schema change needed) |

---

## Migration Dependency Order

```
1. admin_inbox_routing_rules   (depends on users, roles)
2. admin_inbox_items            (depends on users)
```

Both go in `app/Modules/Communication/Database/Migrations/` with timestamps:
- `2026_05_08_100001_create_admin_inbox_routing_rules_table.php`
- `2026_05_08_100002_create_admin_inbox_items_table.php`

---

## Domain Layer

### Enums

| Enum | Values | Location |
|---|---|---|
| `AdminInboxSeverity` | `Info`, `Warning`, `Critical` | `Communication/Domain/Enums/AdminInboxSeverity.php` |
| `AdminInboxStatus` | `Unread`, `Read`, `Snoozed`, `Resolved`, `Reassigned` | `Communication/Domain/Enums/AdminInboxStatus.php` |

### Models

**`AdminInboxItem`** — `Communication/Domain/Models/AdminInboxItem.php`
- Relationships: `belongsTo(User, 'admin_id')`, `belongsTo(User, 'assigned_to_admin_id')`, `morphTo(source)`
- Casts: `status` → `AdminInboxStatus`, `severity` → `AdminInboxSeverity`, `snoozed_until` → `datetime`
- Translatable: `['title', 'body']` (spatie/laravel-translatable)
- Scopes: `scopeActive()` (status IN [unread,read] OR (status=snoozed AND snoozed_until < now)), `scopeUnread()`, `scopeForAdmin(int $adminId)`

**`AdminInboxRoutingRule`** — `Communication/Domain/Models/AdminInboxRoutingRule.php`
- Relationships: `belongsTo(Role, 'route_to_role_id')`, `belongsTo(User, 'route_to_admin_id')`
- Casts: `severity` → `AdminInboxSeverity`, `is_active` → `boolean`
- Scopes: `scopeActiveForEvent(string $eventKey, AdminInboxSeverity $severity)`

### Stub Events (in their home modules)

| Event | Location | Payload |
|---|---|---|
| `BookingStalled` | `Booking/Domain/Events/BookingStalled.php` | `public int $bookingId` |
| `ChatFlagged` | `Communication/Domain/Events/ChatFlagged.php` | `public int $chatThreadId` |
| `ServiceSubmittedForReview` | `Catalog/Domain/Events/ServiceSubmittedForReview.php` | `public int $serviceId` |

---

## Application Layer

### Actions

All live in `app/Modules/Communication/Application/Actions/`.

| Action | Method signature | What it does |
|---|---|---|
| `RouteToAdminInboxAction` | `execute(string $eventKey, AdminInboxSeverity $severity, string $sourceType, int $sourceId, array $titleTranslations, array $bodyTranslations): void` | Loads active rules matching `(eventKey, severity)`. Expands role → user list. Inserts one `AdminInboxItem` per admin in a single `DB::transaction`. Ignores inserts that violate the UNIQUE `(source_type, source_id, admin_id)` constraint (insertOrIgnore). Fires no domain events (routing is infrastructure-level). |
| `AcknowledgeInboxItemAction` | `execute(AdminInboxItem $item, User $actor): AdminInboxItem` | Transitions status `unread → read`. Writes `audit_logs` row. Wraps in `DB::transaction`. |
| `SnoozeInboxItemAction` | `execute(AdminInboxItem $item, User $actor, int $hours): AdminInboxItem` | Validates `$hours` in [1,4,24]. Sets `status=snoozed`, `snoozed_until = now() + hours`. Writes `audit_logs`. Wraps in `DB::transaction`. |
| `ReassignInboxItemAction` | `execute(AdminInboxItem $item, User $actor, User $target): AdminInboxItem` | Validates `$target` has an admin role. Sets original item `status=reassigned`, `assigned_to_admin_id=$target->id`. Creates new `unread` item for `$target` copying `source_*`, `severity`, `title`, `body`. Writes `audit_logs` row. All in one `DB::transaction`. |
| `ResolveInboxItemAction` | `execute(AdminInboxItem $item, User $actor): AdminInboxItem` | Sets `status=resolved`. Writes `audit_logs` row. Wraps in `DB::transaction`. |
| `BatchResolveInboxItemsAction` | `execute(array $itemIds, User $actor): int` | Loads items owned by `$actor`. Resolves each with `ResolveInboxItemAction` inside a single outer `DB::transaction`. Returns count resolved. Silently skips items not owned by `$actor`. |

### Listeners (in `CommunicationServiceProvider`)

All call `RouteToAdminInboxAction` after resolving the payload:

| Listener | Subscribes to | Severity |
|---|---|---|
| `OnVendorRegisteredInbox` | `VendorRegistered` | `info` |
| `OnPaymentFailedInbox` | `PaymentFailed` | `critical` |
| `OnWithdrawalRequestedInbox` | `WithdrawalRequested` | `warning` |
| `OnBookingStalledInbox` | `BookingStalled` | `warning` |
| `OnChatFlaggedInbox` | `ChatFlagged` | `warning` |
| `OnServiceSubmittedForReviewInbox` | `ServiceSubmittedForReview` | `info` |

Each listener is a thin wrapper:
```php
public function handle(VendorRegistered $event): void
{
    $this->router->execute(
        eventKey:    'vendor.registered',
        severity:    AdminInboxSeverity::Info,
        sourceType:  'vendor_profile',
        sourceId:    $event->vendorProfileId,
        title:       ['en' => 'New vendor registration', 'ar' => 'تسجيل مورد جديد'],
        body:        ['en' => "Vendor #{$event->vendorProfileId} awaiting review.", 'ar' => "المورد #{$event->vendorProfileId} في انتظار المراجعة."],
    );
}
```

### Scheduled Command

**`WakeupSnoozedInboxItemsCommand`** — `Communication/Console/WakeupSnoozedInboxItemsCommand.php`
```php
AdminInboxItem::query()
    ->where('status', AdminInboxStatus::Snoozed)
    ->where('snoozed_until', '<', now())
    ->update(['status' => AdminInboxStatus::Unread, 'snoozed_until' => null]);
```
Registered in `CommunicationServiceProvider::boot()` via `$this->app->booted(fn () => Schedule::command(...)->everyFiveMinutes())`.  
No audit log written for snooze wake-up (automatic system action, not an actor decision).

---

## Filament Layer

### `AdminInboxResource`

**Location**: `app/Modules/Communication/Filament/Resources/AdminInboxResource.php`  
**Navigation group**: `Admin Inbox`  
**Navigation icon**: `heroicon-o-inbox`  
**Navigation badge**: `AdminInboxItem::query()->where('admin_id', auth()->id())->where('status', 'unread')->count()` — returns unread count for authenticated admin  
**Record scope**: filtered to `admin_id = auth()->id()` in `getEloquentQuery()`

**Table columns**:
- Severity badge (color: info=info, warning=warning, critical=danger)
- Title (current locale, truncated)
- Source type + ID
- Status badge
- `created_at` (sortable, default desc)

**Filters**:
- `SelectFilter` on `status`
- `SelectFilter` on `severity`

**Row actions**:
- `Action::make('read')` → `AcknowledgeInboxItemAction` — visible when status = unread
- `Action::make('snooze')` → form with Select (1h, 4h, 24h) → `SnoozeInboxItemAction`
- `Action::make('reassign')` → form with Select of admin users → `ReassignInboxItemAction`
- `Action::make('resolve')` → `ResolveInboxItemAction` → requiresConfirmation

**Bulk actions**:
- `BulkAction::make('batch_resolve')` → `BatchResolveInboxItemsAction`

---

### `InboxRoutingRulesResource`

**Location**: `app/Modules/Communication/Filament/Resources/InboxRoutingRulesResource.php`  
**Navigation group**: `Admin Settings`  
**Visible to**: super-admin role only (`canAccess` check via filament-shield)

**Table columns**:
- `event_key` (searchable)
- `severity` badge
- Route target: role name OR admin display name (computed column)
- `is_active` toggle (inline `ToggleColumn`)
- `created_at`

**Form**:
- `TextInput::make('event_key')` with datalist hint of known event keys
- `Select::make('severity')` from `AdminInboxSeverity`
- `Select::make('route_to_role_id')` → relationship to roles, nullable, clearable
- `Select::make('route_to_admin_id')` → relationship to users with admin role, nullable, clearable
- `Toggle::make('is_active')`

---

## Locale Coverage

| Element | EN | AR |
|---|---|---|
| `admin_inbox_items.title` | ✅ JSON key `en` | ✅ JSON key `ar` |
| `admin_inbox_items.body` | ✅ JSON key `en` | ✅ JSON key `ar` |
| Filament labels | `communication.php` en/ar lang files | ✅ AR lang file updated |
| Listener title/body strings | Inline per-listener EN defaults | ✅ Inline AR strings |

---

## Domain Events Fired

| Trigger | Event | Fires After Commit? |
|---|---|---|
| `RouteToAdminInboxAction` | None — routing is infrastructure; no domain event needed | N/A |
| Status transitions (acknowledge, snooze, reassign, resolve) | None — transitions write to `audit_logs` directly; no downstream listeners needed | N/A |

No cross-module domain events are fired by this feature. It only *consumes* events from other modules.

---

## Idempotency

No idempotency keys required. All mutations are admin-only Filament actions; no external API endpoints with payment-level idempotency requirements.

---

## Architecture Tests

Add assertions to the existing architecture test suite:

1. **`NoCrossModuleModelImportsTest`** — already exists; the new `AdminInboxItem` model must not be imported by any class outside `Communication`.
2. **`AppendOnlyTablesHaveNoSoftDeletesTest`** — verify `admin_inbox_items` migration has no `softDeletes()` call (it's mutable, not append-only, but also must not soft-delete per spec).

No new architecture test files needed.

---

## Cut-List (Phase 1.5)

| Item | Rationale |
|---|---|
| Email digest of unread inbox items | Explicitly deferred in spec — out of scope Phase 1 |
| Mobile push of inbox items to admin app | Explicitly deferred in spec — out of scope Phase 1 |
| Full chat moderation flag flow (creating `ChatFlagged` events from real chat events) | `ChatFlagged` stub only in Phase 1; wired to real chat moderation in Phase 1.5 |
| Routing rule conflict resolution (when two rules match same event + admin) | Current: UNIQUE constraint silently ignores; Phase 1.5 may add priority ordering |
| Routing rule audit log (who created/edited a rule) | `created_by`/`updated_by` columns stored; Filament display of rule history deferred |

---

## Day 1 Build Order

```
1. Stub events: BookingStalled, ChatFlagged, ServiceSubmittedForReview
2. Enums: AdminInboxSeverity, AdminInboxStatus
3. Migrations: admin_inbox_routing_rules, admin_inbox_items
   → php artisan migrate
4. Models: AdminInboxRoutingRule, AdminInboxItem
   → relationships, casts, scopes, $translatable
5. Factories: AdminInboxRoutingRuleFactory, AdminInboxItemFactory
6. Actions: RouteToAdminInboxAction, AcknowledgeInboxItemAction,
            SnoozeInboxItemAction, ReassignInboxItemAction,
            ResolveInboxItemAction, BatchResolveInboxItemsAction
7. Listeners: OnVendorRegisteredInbox, OnPaymentFailedInbox, OnWithdrawalRequestedInbox,
              OnBookingStalledInbox, OnChatFlaggedInbox, OnServiceSubmittedForReviewInbox
8. Console command: WakeupSnoozedInboxItemsCommand + scheduler registration
9. CommunicationServiceProvider: wire listeners + scheduler
10. Filament: AdminInboxResource (with bell badge)
11. Filament: InboxRoutingRulesResource
12. Shield: php artisan shield:generate --all
13. Lang files: en + ar keys for new UI strings
14. Pest tests:
    - vendor registration creates inbox items for vendor-manager role
    - snooze hides item until snoozed_until passed
    - reassign moves ownership + audit log written
```

---

## Exit Criteria

- [ ] `php artisan migrate` runs cleanly with the 2 new tables
- [ ] `VendorRegistered` event → all vendor-manager admins receive `unread` inbox item
- [ ] Snooze: item excluded from active list; reappears after `snoozed_until` passes (in-query check)
- [ ] Reassign: original `reassigned` + new `unread` for target + 1 audit_logs row
- [ ] Bell badge count matches unread item count for authenticated admin
- [ ] `./vendor/bin/pest --group=admin-inbox` all green
- [ ] Filament inbox page renders in EN and AR without layout breakage
