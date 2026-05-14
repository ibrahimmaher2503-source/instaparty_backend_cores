# Admin UI Contract: Service Moderation Actions + Per-Type Queues

This feature adds no HTTP API endpoints. The contract below defines the admin-facing Filament behavior that planning and tests must preserve.

## Resources

| Resource | Product Type Scope | Existing Index | Pending Queue Page |
|---|---|---|---|
| Rental Services | `rental` | Shows rental services with normal filters | Shows only rental services with `pending_review` |
| Sale Services | `sale` | Shows sale services with normal filters | Shows only sale services with `pending_review` |
| Digital Services | `digital` | Shows digital services with normal filters | Shows only digital services with `pending_review` |

## Navigation Badge Contract

- Each service resource navigation entry reports pending-review count for its own product type.
- Count query scope: `product_type = resource type` and `status = pending_review`.
- Count excludes soft-deleted rows unless the existing resource convention says otherwise.
- Badge updates after approve, reject, request edits, archive, and material-edit transitions.

## Pending Queue Contract

- The pending queue status scope is built into the page query.
- The pending queue must not expose a removable filter chip that reveals non-pending services.
- The queue shows service name, product type badge, status badge, price, vendor, created/submitted timestamp, and media thumbnail where available.
- Empty state identifies the product type and that the queue is for pending review.

## Row Actions

### Approve & Publish

**Visible when**:
- Service status is `pending_review`.
- Admin has service moderation permission.

**On submit**:
- Status becomes `published`.
- `moderated_by` is the acting admin.
- `moderated_at` is set to current UTC timestamp.
- Publish event is emitted after commit.
- The pending queue and navigation badge no longer count the service.

### Reject

**Visible when**:
- Service status is `pending_review`.
- Admin has service moderation permission.

**Form fields**:
- `reason.en`: required, non-empty, max 1000 characters.
- `reason.ar`: required, non-empty, max 1000 characters, displayed RTL.

**On submit**:
- Status becomes `rejected`.
- `moderation_notes.en` and `moderation_notes.ar` are persisted.
- `moderated_by` and `moderated_at` are set.
- Reject event is emitted after commit.
- The pending queue and navigation badge no longer count the service.

### Request Edits

**Visible when**:
- Service status is `pending_review`.
- Admin has service moderation permission.
- ADR-0018 service change-request action is available for the service type.
- No open change request already exists for the service.

**Form fields**:
- One or more change items.
- Each item requires `field_path`, `requested_change_en`, and `requested_change_ar`.

**On submit**:
- Service enters `changes_requested`.
- A change request with pending items is created.
- Change-request event is emitted after commit.
- The pending queue and navigation badge no longer count the service.

## Bulk Actions

### Approve Selected

- Requires confirmation.
- Applies only to selected services eligible for `pending_review -> published`.
- Uses the same application action as row approve.
- Supports at least 50 selected services.
- Reports conflicts if any selected service changed state before submit.

### Reject Selected

- Requires one shared bilingual reason.
- Applies only to selected services eligible for `pending_review -> rejected`.
- Uses the same application action as row reject.
- No selected service may be rejected if the EN or AR reason is missing.

### Archive Selected

- Requires confirmation.
- Applies only to services eligible for archive transition.
- Uses the same application action as row/archive workflow.
- Reports skipped or conflicted rows clearly.

## Manual Status Editing

The admin workflow should not rely on free-form manual status changes for moderation decisions. Status fields in create/edit forms should be hidden, disabled, or restricted according to existing permission policy so approve/reject/request-edit actions are the normal moderation path.

## Localization Contract

English and Arabic translation keys are required for:

- Approve & Publish
- Reject
- Reject reason EN
- Reject reason AR
- Request Edits
- Approve selected
- Reject selected
- Archive selected
- Pending rental services
- Pending sale services
- Pending digital services
- Empty queue messages
- Confirmation prompts
- Success notifications
- Validation/failure notifications
