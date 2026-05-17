# Contract: Domain Events

**Feature**: `035-service-edit-approval`
**Module**: `App\Modules\Catalog\Domain\Events`

All events fire via `DB::afterCommit(fn () => event(...))` from inside the originating Action. Listeners that mutate state must be queued (`ShouldQueue`).

---

## ServiceChangeRequestSubmitted

```php
namespace App\Modules\Catalog\Domain\Events;

final class ServiceChangeRequestSubmitted
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
    ) {}
}
```

**Payload**: full hydrated `ServiceChangeRequest` model with items + service relation eager-loaded.

**Subscribers**:
- `WriteServiceChangeRequestAuditListener` — append `audit_logs` row.
- (Phase 1.5) `NotifyAdminInboxListener` — surface in admin inbox if >24h pending.

---

## ServiceChangeRequestApproved

```php
final class ServiceChangeRequestApproved
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
        /** @var list<string> */
        public readonly array $appliedFieldPaths,
    ) {}
}
```

**Subscribers**:
- `WriteServiceChangeRequestAuditListener` — append one `audit_logs` row per applied field.
- `DispatchServiceChangeApprovedNotificationListener` — bilingual push + email to vendor using template `service.change_request.approved`.
- (Implicit) `Service` model's `Searchable` observer re-queues Scout index update.

---

## ServiceChangeRequestRejected

```php
final class ServiceChangeRequestRejected
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
    ) {}
}
```

**Subscribers**:
- `WriteServiceChangeRequestAuditListener`.
- `DispatchServiceChangeRejectedNotificationListener` — template `service.change_request.rejected`.

---

## ServiceChangeRequestClarificationRequested

```php
final class ServiceChangeRequestClarificationRequested
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
        public readonly ServiceChangeRequestMessage $message,
    ) {}
}
```

**Subscribers**:
- `WriteServiceChangeRequestAuditListener`.
- `DispatchServiceChangeClarificationNotificationListener` — template `service.change_request.clarification_requested`.

---

## ServiceChangeRequestClarificationReplied

```php
final class ServiceChangeRequestClarificationReplied
{
    public function __construct(
        public readonly ServiceChangeRequest $changeRequest,
        public readonly ServiceChangeRequestMessage $message,
    ) {}
}
```

**Subscribers**:
- `WriteServiceChangeRequestAuditListener`.
- (Phase 1.5) `NotifyAdminInboxListener` — push vendor reply back to admin inbox.

---

## Cross-module contracts

This feature does NOT introduce any new cross-module Contracts. All consumers (Communication, Audit) subscribe to the events above. The Catalog module owns these events; if another module needs to react it does so as an event subscriber, not via a direct model import (Constitution §I).

---

## Listener locations

```
app/Modules/Catalog/Application/Listeners/WriteServiceChangeRequestAuditListener.php
app/Modules/Communication/Application/Listeners/DispatchServiceChangeApprovedNotificationListener.php
app/Modules/Communication/Application/Listeners/DispatchServiceChangeRejectedNotificationListener.php
app/Modules/Communication/Application/Listeners/DispatchServiceChangeClarificationNotificationListener.php
```

The three Communication listeners reuse the existing template-routing pipeline from spec 009. No new gateway code needed.
