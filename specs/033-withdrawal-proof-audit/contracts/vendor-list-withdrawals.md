# Contract — GET /api/v1/vendor/withdrawals

**Method**: GET
**Path**: `/api/v1/vendor/withdrawals`
**Auth**: Sanctum token (vendor)
**Permission**: `settlement.view_withdrawals.own`
**Tenant scope**: only the authenticated vendor's withdrawals
**Pagination**: cursor (existing — unchanged)

## Query parameters (existing — unchanged)

- `cursor` — pagination cursor
- `per_page` — default 20, max 50
- `status` — optional filter: `pending` | `approved` | `paid` | `rejected`

## Response 200 — list (EN locale)

```json
{
  "data": [
    {
      "public_id": "01HXY7VWNQTQX5ZP3ZK1NE5K2C",
      "amount": { "minor": 50000, "currency": "EGP", "formatted": "EGP 500.00" },
      "status": "paid",
      "requested_at": "2026-05-12T08:00:00Z",
      "approved_at": "2026-05-14T10:30:00Z",
      "paid_at":     "2026-05-16T11:00:00Z",
      "bank_transfer_reference": "EGTBNK-2026-05-16-00041",
      "has_proof": true
    },
    {
      "public_id": "01HXX9V…",
      "amount": { "minor": 30000, "currency": "EGP", "formatted": "EGP 300.00" },
      "status": "pending",
      "requested_at": "2026-05-15T12:00:00Z",
      "approved_at": null,
      "paid_at": null,
      "bank_transfer_reference": null,
      "has_proof": false
    }
  ],
  "meta": {
    "cursor": {
      "next": "eyJpZCI6MTMyOX0",
      "prev": null,
      "per_page": 20
    }
  },
  "errors": null
}
```

## Design notes

- The list response includes `has_proof: bool` and the timeline timestamps so the vendor list-screen can render the lifecycle badge without an extra request.
- The `proof_download_url` is NOT included on the list — only on the show endpoint — to avoid generating signed URLs for rows the vendor never opens (cost + cache hygiene).
- `bank_transfer_reference` is included on the list because it is small, frequently scanned by vendors looking for "did this transfer arrive?", and not sensitive.

## api-registry.md entry update

`GET /api/v1/vendor/withdrawals` row: Resource column updated to `WithdrawalListResource (extended — adds approved_at, paid_at, bank_transfer_reference, has_proof)`.
