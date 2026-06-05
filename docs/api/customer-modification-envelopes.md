# Customer Modification Envelopes — Client Contract

> **Status:** authoritative as of 2026-06-05 (branch `057-vendor-mobile-gaps`).
> Pinned by `tests/Feature/Modules/Booking/BookingModificationEnvelopeContractTest.php` —
> a failure there means a breaking client change.
> Audience: the Flutter diff-viewer team. The viewer was built on assumed shapes;
> this document replaces those assumptions.

All responses use the standard envelope:

```json
{ "data": ..., "meta": ..., "errors": null }
```

## 1. `GET /api/v1/customer/bookings/{bookingPublicId}/modifications`

`data` is an array of modification objects, newest first:

```json
{
  "public_id": "01JX…",
  "booking_vendor_id": "01JX…",        // public_id of the booking_vendor
  "proposed_by_role": "vendor",         // "vendor" | "admin"  (admin = admin-proposed alternative)
  "proposal_kind": "change_price",      // add_item | remove_item | change_quantity | change_price | change_slot | add_surcharge | add_note
  "status": "pending",                  // draft | pending | customer_accepted | customer_rejected | withdrawn | expired
  "vendor_explanation": {"en": "…", "ar": "…"},   // nullable, bilingual JSON
  "diff_snapshot": { … },               // see §3 — TWO variants
  "created_at": "2026-06-05T12:00:00+00:00"
}
```

Drafts (`status=draft`) are vendor work-in-progress and may appear in the list;
the client should only surface `pending` for decision UI.

## 2. `GET …/modifications/{modificationPublicId}`

`data` is a single object, same shape as §1.

## 3. `diff_snapshot` — ⚠️ TWO variants exist

The column is written by two different server paths. **The diff viewer must
detect the variant by key presence** (`before`/`after` vs `totals`).

### Variant B — before/after item snapshots
Written by the vendor **modify** and **preview** paths
(`BookingModificationDiffService`). The common case for `pending` proposals:

```json
{
  "before": {
    "items": [
      {
        "public_id": "01JX…",
        "unit_price_minor": 50000,
        "unit_price_currency": "EGP",
        "quantity": 1,
        "effective_starts_at": "2026-07-01T10:00:00+00:00",  // nullable
        "effective_ends_at": "2026-07-01T15:00:00+00:00"     // nullable
      }
    ],
    "subtotal_minor": 50000
  },
  "after": { "items": [ … ], "subtotal_minor": 60000 }
}
```

- Added items appear only in `after.items` (fresh `public_id`).
- Removed items appear only in `before.items`.
- All money is integer minor units (piastres).

### Variant A — totals + per-change deltas
Written by the vendor-portal **draft** flow
(`CreateBookingModificationAction` / `RecalculateBookingModificationTotalsAction`):

```json
{
  "totals": {
    "price_delta_minor": 10000,
    "currency": "EGP",
    "item_count": 2,
    "by_change_kind": {"update": 1, "add": 1}
  },
  "items": [
    {
      "modification_item_id": 12,
      "target_booking_item_id": 7,      // null for adds
      "change_kind": "update",           // add | remove | update
      "change_type": "change_price",     // nullable
      "price_delta_minor": 10000,
      "quantity_delta": 0,
      "time_delta": null
    }
  ]
}
```

> **Recommendation (backend backlog):** normalize on write or expose a derived
> `diff` field with a single canonical shape. Until then, handle both.

## 4. `POST …/modifications/{modificationPublicId}/decide`

Request body: `{"decision": "accepted"}` or `{"decision": "rejected"}` —
anything else is a 422 on `decision`.

⚠️ **`Idempotency-Key` header is REQUIRED and must be a valid UUID** —
missing or non-UUID keys are rejected with
`422 {"errors": ["Idempotency-Key header is required and must be a valid UUID"]}`.
Replays within 24 h return the cached result.

`data` is the **full refreshed booking** (same shape as booking detail) with
**server-authoritative totals** — never recompute client-side:

```json
{
  "public_id": "01JX…",
  "reference_no": "INP-2026-000123",
  "lifecycle_status": "confirmed",
  "display_status": "confirmed",        // see booking-status notes below
  "rejection_reason": null,
  "vendors_summary": {"total": 1, "pending": 0, "accepted": 1, "modified": 0, "rejected": 0, "cancelled": 0, "in_progress": 0, "completed": 0, "timed_out": 0},
  "payment_status": "unpaid",
  "fulfillment_status": "not_started",
  "subtotal_minor": 0,
  "total_minor": 60000,
  "due_minor": 60000,
  "currency": "EGP",
  "requires_customer_approval": false,
  "…": "all other booking-detail fields"
}
```

⚠️ **After a decision, display `total_minor`/`due_minor` — NOT the booking-level
`subtotal_minor`.** Accepting a modification recalculates the per-vendor
subtotals (`vendors[].subtotal_minor`) and the booking `total_minor`, but the
booking-level `subtotal_minor` is not recomputed by this path (pre-existing
backend behavior, pinned by the contract test).

State effects (server-enforced; client is display-only):

| Decision | Modification | booking_vendor | Booking |
|---|---|---|---|
| `accepted` | `customer_accepted` | `modified → accepted`; items + totals recalculated | `confirmed` when ALL vendors accepted, else stays `customer_review` |
| `rejected` | `customer_rejected` | unchanged | unchanged |

Errors: 404 unknown/foreign booking or modification · 409 modification not
`pending` (already decided/withdrawn/expired) · 422 invalid `decision`.

## 5. Booking `display_status` (added 2026-06-05)

`lifecycle_status` is unchanged. `display_status` adds two derived values:
`rejected` (cancelled because every vendor rejected) and
`modification_requested` (a pending proposal awaits the customer). Per-vendor
`rejection_reason` (localized via `Accept-Language`) is on each entry of
`vendors[]`; a booking-level `rejection_reason` appears when
`display_status=rejected`.
