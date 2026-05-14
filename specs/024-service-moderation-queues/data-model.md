# Data Model: Service Moderation Actions + Per-Type Queues

## Existing Tables

### `services`

**Purpose**: Customer-facing service listing submitted by a vendor and moderated by admins before publication.

**Relevant fields**:

| Field | Type | Requirement |
|---|---|---|
| `id` | BIGINT unsigned | Internal primary key; never exposed in URLs |
| `public_id` | CHAR(26) | Public ULID |
| `vendor_profile_id` | BIGINT unsigned | Owning vendor profile |
| `category_id` | BIGINT unsigned | Category used for customer discovery and material-edit gate |
| `product_type` | ENUM | `rental`, `sale`, or `digital` |
| `name` | JSON | Translatable EN+AR; material edit |
| `short_description` | JSON nullable | Translatable EN+AR; material edit |
| `long_description` | JSON nullable | Translatable EN+AR; material edit |
| `base_price_minor` | BIGINT unsigned | Material edit; integer minor units |
| `base_price_currency` | CHAR(3) | Currency code, currently EGP |
| `status` | ENUM | Required lifecycle status |
| `moderation_notes` | JSON nullable | Rejection/request guidance with EN+AR values |
| `moderated_at` | TIMESTAMP nullable | Last moderation decision timestamp |
| `moderated_by` | BIGINT unsigned nullable | Admin user who made last moderation decision |
| `created_at`, `updated_at`, `deleted_at` | timestamps | Services are soft-deletable per locked schema |

**Schema alignment note**: Current migrations must be checked and aligned to the locked schema. The codebase currently shows missing `rejected`, `moderation_notes`, `moderated_at`, and `moderated_by` in Catalog migrations.

## Lifecycle States

Canonical Phase 8.0 lifecycle:

```text
draft -> pending_review -> published
draft -> pending_review -> rejected
pending_review -> changes_requested -> pending_review
published -> pending_review     # material edit
published -> archived
rejected -> archived
archived -> draft                # restore path only when explicitly allowed
```

## State Transition Rules

| From | To | Trigger | Metadata |
|---|---|---|---|
| `pending_review` | `published` | Admin approve/publish | Set `moderated_by`, `moderated_at`; clear or preserve notes per ADR decision |
| `pending_review` | `rejected` | Admin reject with EN+AR reason | Set `moderation_notes.en`, `moderation_notes.ar`, `moderated_by`, `moderated_at` |
| `pending_review` | `changes_requested` | Admin request edits | Create/open change request through ADR-0018 workflow |
| `changes_requested` | `pending_review` | Vendor resubmits | Close/resubmit change request, return to queue |
| `published` | `pending_review` | Vendor material edit | Set status only; moderator metadata remains last moderator unless implementation explicitly tracks return actor in audit |
| any eligible non-archived | `archived` | Admin archive | Set status and event metadata according to action contract |

## Material Edit Fields

Material if changed while service is `published`:

- `base_price_minor`
- `base_price_currency`
- `category_id`
- `name`
- `short_description`
- `long_description`
- customer-facing media in the primary service gallery/core media collections

Non-material by default:

- admin-only feature flags
- internal import metadata
- non-customer-facing audit/support metadata

If classification is unclear, use the conservative rule: return the service to `pending_review`.

## Validation Rules

### Reject

- Service must be `pending_review`.
- Reason must include non-empty `en` and `ar` strings.
- Acting admin must have service moderation permission.
- Transition must be valid according to `ServiceStatus`.

### Approve/Publish

- Service must be `pending_review`.
- Acting admin must have service moderation permission.
- Transition must be valid according to `ServiceStatus`.
- Event must fire after commit.

### Request Edits

- Service must be `pending_review` unless ADR-0018 explicitly allows another actionable status.
- At least one change item is required.
- Each change item requires `field_path`, `requested_change_en`, and `requested_change_ar`.
- Duplicate open change requests are blocked.
- Cycle limit from ADR-0018 remains enforced.

### Bulk Actions

- Selected services must be eligible for the requested transition.
- Bulk reject requires one shared EN+AR reason.
- Bulk approve must support 50 services.
- Bulk behavior must match row behavior for status, notes, metadata, and events.

## Relationships

- Service belongs to VendorProfile through `vendor_profile_id`.
- Service belongs to Category through `category_id`.
- Service has one detail row by product type:
  - rental -> `service_rental_details`
  - sale -> `service_sale_details`
  - digital -> `service_digital_details`
- Service has many Shared `change_requests` where `subject_type = service`.
- Service has media in Media Library collection(s), especially `gallery`.

## Test Data Requirements

- At least one rental, sale, and digital service in `pending_review`.
- At least one non-pending service per type to verify pending queues exclude it.
- At least 50 pending services for bulk approve validation.
- Published services for all three product types to validate material and non-material edit gates.
- Rejection notes with both EN and AR values.
