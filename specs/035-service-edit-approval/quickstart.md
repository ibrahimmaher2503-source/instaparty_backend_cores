# Quickstart: Service Material Edit Approval Workflow

**Feature**: `035-service-edit-approval`
**For**: Developers picking up this branch after planning.

---

## 0. Prerequisites

- Branch: `035-service-edit-approval` (already checked out)
- Specs read: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/*.md`
- Phase 8.0 (spec 024) shipped — service moderation queues exist and `service.moderate.{type}` permissions are seeded

---

## 1. Before any code

1. Write **`docs/adr/ADR-0035-service-material-edit-approval.md`** using the ADR template. Key sections:
   - Context: Phase 8.0 reverts published services to `pending_review` on material edit → dark window. Unacceptable for revenue listings.
   - Decision: Staged-edits via new `service_change_requests` table; live row untouched until admin approves.
   - Status: Proposed.
   - Consequences: Three new tables; deprecates `MarkServicePendingReviewForMaterialEditAction` for published services; admin queue gets a new Filament page.
2. Open a PR for the ADR alone and get it accepted before migrations.

---

## 2. Migrations (in this order)

```bash
php artisan make:migration --path=app/Modules/Catalog/Database/Migrations create_service_change_requests_table
php artisan make:migration --path=app/Modules/Catalog/Database/Migrations create_service_change_request_items_table
php artisan make:migration --path=app/Modules/Catalog/Database/Migrations create_service_change_request_messages_table
```

Fill schemas per `data-model.md`. Run `php artisan migrate` and verify with:

```sql
SHOW CREATE TABLE service_change_requests;
SHOW INDEX FROM service_change_requests;  -- confirm open_lock_key UNIQUE present
```

---

## 3. Domain layer

1. `Domain/Enums/ServiceChangeRequestStatus.php` — backing enum + labels (bilingual via `__()`).
2. `Domain/Models/ServiceChangeRequest.php` — relations to `Service`, `User` (submittedBy, decidedBy), `ServiceChangeRequestItem`, `ServiceChangeRequestMessage`. Translatable: `vendor_note`, `admin_note`. Cast `proposed_changes`, `before_snapshot` to `array`. Optimistic-lock helper.
3. `Domain/Models/ServiceChangeRequestItem.php` + `Message.php` — append-only model traits (override `save()` to throw on update for `Message`).
4. `Domain/Policies/MaterialFieldRegistry.php` — exhaustive arrays per `ProductType`. Public method `materialFieldsFor(ProductType): array`.
5. `Domain/Policies/ServiceEditApprovalPolicy.php` — constants `MAX_CLARIFICATIONS = 3`.
6. `Domain/Events/*` — five events from `contracts/events.md`.

---

## 4. Application layer (Actions)

Build in this order. Each is **one public `execute()` method**, constructor injection, transactions wrapping mutations, `DB::afterCommit` for events:

1. `DetectMaterialServiceChangesAction` — pure function; returns `ServiceFieldDiff` DTO. Walks current vs. proposed using `MaterialFieldRegistry`. No DB writes.
2. `SubmitServiceChangeRequestAction` — `lockForUpdate` on service, check no open request, snapshot before-state, write parent + items, fire `ServiceChangeRequestSubmitted`.
3. `ApplyServiceChangeToLiveAction` — internal helper. `match(ProductType)` to dispatch into `applyRental`, `applySale`, `applyDigital`. Replays media gallery ops, availability windows, pricing tiers in deterministic order.
4. `ApproveServiceChangeRequestAction` — version-check, call `ApplyServiceChangeToLiveAction`, write decision columns, fire `ServiceChangeRequestApproved`.
5. `RejectServiceChangeRequestAction` — bilingual note required, no apply, fire `ServiceChangeRequestRejected`.
6. `RequestServiceChangeClarificationAction` — round cap check, append message, fire event.
7. `ReplyToServiceChangeClarificationAction` — vendor-side; status back to `pending`, append message, fire event.
8. `CancelServiceChangeRequestAction` — internal; called by suspension/archive flows.

Wire all events in `CatalogServiceProvider::boot()`.

---

## 5. Controller wiring

In the existing per-type `VendorServiceController` update endpoints (`UpdateRentalServiceAction` etc.), branch:

```php
$diff = $this->detectAction->execute($service, $request->validated());

if ($service->status !== ServiceStatus::Published || $diff->isEmpty()) {
    return $this->existingDirectApplyPath($service, $request);
}

if ($diff->hasMaterialChanges()) {
    $cr = $this->submitChangeRequestAction->execute(/* ... */);
    return ServiceChangeRequestResource::make($cr)->response()->setStatusCode(202);
}

return $this->existingDirectApplyPath($service, $request); // non-material only
```

Add three admin endpoints in `AdminServiceChangeRequestController` (`approve`, `reject`, `requestClarification`) — 3-line action bodies delegating to Actions.

Vendor reply endpoint: `POST /api/v1/vendor/service-change-requests/{publicId}/reply`.

---

## 6. Filament admin page

```bash
php artisan make:filament-page PendingServiceEdits --resource=none
```

Place under `app/Modules/Catalog/Filament/Pages/PendingServiceEditsPage.php`. Navigation group: `Services`. Use `Tables\Table` with eager-loaded `service`, `vendor`, `items`.

Row Action buttons: `Approve` (form with optional bilingual `admin_note`), `Reject` (form with required bilingual `admin_note`), `Request Clarification` (form with required bilingual question). Each Action button delegates to the corresponding Application Action class (never inline business logic in the closure).

Diff modal: side-by-side `KeyValue` view fields, items grouped by `field_classification` with per-classification badge colors. Use this color map (consistent with `filament-components.md` §2):

| Classification | Color |
|---|---|
| `shared` | `gray` |
| `rental` | `warning` |
| `sale` | `success` |
| `digital` | `info` |
| `media` | `primary` |
| `availability` | `secondary` |
| `pricing_tier` | `danger` |

Sidebar badge: `PendingServiceEditsBadgeWidget` reuses `ServiceChangeRequest::query()->where('status','pending')->count()`.

Then:

```bash
php artisan shield:generate --all
php artisan filament:cache-components
```

---

## 7. Notifications

Add three notification templates via seeder (or admin UI) with `event_key` namespace `service.change_request.*`. EN+AR bodies. Channels: `push`, `email`. Audience: vendor.

---

## 8. Tests

```bash
./vendor/bin/pest tests/Feature/Modules/Catalog/ServiceChangeRequest --parallel
./vendor/bin/pest tests/Unit/Modules/Catalog/Policies/MaterialFieldRegistryTest.php
./vendor/bin/pest --group=rental
./vendor/bin/pest --group=sale
./vendor/bin/pest --group=digital
```

Required tests (per `plan.md` structure):

- `SubmitMaterialEditTest` — for each `ProductType`: assert live row unchanged, change request exists, second submit yields 409.
- `ApproveEditTest` — for each `ProductType`: assert atomic apply across base + detail tables, audit log per field, event fired (use `Event::fake`), Scout queue dispatched.
- `RejectEditTest` — bilingual reason required (422 if one locale missing), live row byte-identical to before, event fired.
- `RequestClarificationTest` — bilingual question required, status transitions, round cap at 3.
- `VendorReplyClarificationTest` — status returns to `pending`, message appended.
- `NonMaterialEditBypassTest` — internal-notes-only edit applies directly; no change request created.
- `WrongVendorAuthTest` — Vendor A cannot submit/cancel/reply for Vendor B's request (403).
- `PublishedServiceSafetyTest` — discovery endpoint returns old values while change request `pending`.
- `ArchivedServiceCancellationTest` — archiving the service while a change request is pending transitions the CR to `cancelled_service_unavailable`.
- `ConcurrentDecisionConflictTest` — two admin approvals on the same request → second gets 409.

All tests run on the real test database (no DB mocking — per project's testing memory).

---

## 9. Verification checklist (pre-PR)

- [ ] ADR-0035 merged
- [ ] Three migrations run cleanly forward AND rollback
- [ ] `MaterialFieldRegistry` covers every field path enumerated in `research.md` R2
- [ ] All 5 Actions exist with single `execute()`, constructor DI, `DB::transaction` + `DB::afterCommit`
- [ ] `DetectMaterialServiceChangesAction` returns `ServiceFieldDiff` with zero false negatives on the exhaustive Pest test
- [ ] `PendingServiceEditsPage` renders for each product type with correct badge colors
- [ ] `php artisan shield:generate --all` rerun and committed
- [ ] Three notification templates exist with EN+AR bodies
- [ ] All Pest tests green; coverage groups `rental`, `sale`, `digital` all pass
- [ ] `php artisan pint` clean
- [ ] `vendor/bin/phpstan analyse` clean
- [ ] API registry updated with the three admin endpoints + vendor reply + (existing) update endpoint behavior note
- [ ] Bruno collection entries committed under `docs/api/collections/`
- [ ] `MarkServicePendingReviewForMaterialEditAction` annotated `@deprecated for published services — use SubmitServiceChangeRequestAction`
- [ ] Backfill PRs queued for `01_PRD.md`, `09_Phasing_Plan.md`, `11_DB_Schema.md`, `schema-cheatsheet.md`, `project-index.md`

---

## 10. Out of scope (Phase 1.5 cut-list)

- Stale-edit reminder cron
- `MAX_CLARIFICATIONS` configurable via `app_settings`
- Vendor-initiated cancel of a pending request
- Public mobile-app endpoint
- Bulk approve from the admin queue (Phase 1 ships row-level only)
