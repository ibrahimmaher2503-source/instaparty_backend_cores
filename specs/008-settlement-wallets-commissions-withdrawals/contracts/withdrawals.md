# Contract: Vendor Withdrawal Endpoints

**Module**: Settlement | **Audience**: vendor (sanctum-authenticated)

Three endpoints for vendors to request and view their own withdrawals. Admin operations (approve/reject) live in Filament, not API.

---

## POST `/api/v1/vendor/withdrawals`

Request a new withdrawal.

### Request

| Header | Required | Notes |
|---|---|---|
| `Authorization: Bearer {token}` | yes | Sanctum |
| `Accept-Language` | no | `en` (default) or `ar` |
| `Idempotency-Key` | **yes** | UUID v4; 24h TTL via `IdempotencyKeyMiddleware` |
| `Content-Type: application/json` | yes | |

### Body

```json
{
  "amount_minor": 80000,
  "currency": "EGP",
  "bank_account": {
    "account_holder": "Ibrahim Maher",
    "iban": "EG800001000000000123456789012",
    "bank_name": "Banque Misr",
    "swift_bic": "BMISEGCXXXX"
  }
}
```

### Permission
- `settlement.request_withdrawal.own`

### Response — 201 Created

```json
{
  "data": {
    "public_id": "01HVMR5K0XEXAMPLEWITHDRAW00",
    "status": "pending",
    "requested_amount_minor": 80000,
    "requested_amount_formatted": "800.00 EGP",
    "currency": "EGP",
    "bank_account": {
      "account_holder": "Ibrahim Maher",
      "iban": "EG80**********************012",
      "bank_name": "Banque Misr",
      "swift_bic": "BMISEGCXXXX"
    },
    "requested_at": "2026-05-04T10:00:00Z",
    "processed_at": null,
    "paid_at": null,
    "rejected_reason": null
  },
  "meta": {},
  "errors": []
}
```

### Response — 422 Validation

Possible error codes (each with locale-resolved message):
- `validation` (per-field for IBAN/SWIFT/account_holder format)
- `insufficient_balance` — requested > available
- `below_minimum_amount` — requested < 100 EGP
- `existing_pending_withdrawal` — vendor has an in-flight pending withdrawal
- `negative_balance_blocked` — vendor's balance is negative; cannot withdraw

```json
{
  "data": null,
  "meta": {},
  "errors": [{
    "code": "existing_pending_withdrawal",
    "message_key": "settlement.errors.existing_pending_withdrawal",
    "message": "You already have a pending withdrawal request (01HVMR5K0XEXAMPLEWITHDRAW00). Wait until it's processed before requesting another.",
    "params": {
      "existing_withdrawal_public_id": "01HVMR5K0XEXAMPLEWITHDRAW00"
    }
  }]
}
```

### Response — 401 / 403

Standard `unauthenticated` / `forbidden` envelope.

### Notes
- `Idempotency-Key` is required; replays within 24h return the cached 201 response without creating a duplicate withdrawal.
- IBAN is **masked in response** (first 4 + last 3 chars visible), even to the requesting vendor (defence in depth — server logs / response sniffing).
- `bank_account_snapshot` is stored as-is in the `withdrawals` row; immune to vendor editing their bank details later.
- Vendor's `wallets.pending_withdrawal_minor` increments by `amount_minor` on success; decrements on `paid` or `rejected`.

---

## GET `/api/v1/vendor/withdrawals`

List the authenticated vendor's withdrawals (paginated).

### Request

| Query param | Required | Default | Notes |
|---|---|---|---|
| `per_page` | no | 25 | max 100 |
| `cursor` | no | — | opaque pagination |
| `status` | no | — | filter: `pending` \| `paid` \| `rejected` |

### Permission
- `settlement.view_withdrawals.own`

### Response — 200 OK

Same shape as `POST` response but wrapped in array under `data`, plus `meta.pagination`.

```json
{
  "data": [
    {
      "public_id": "01HVMR5K0XEXAMPLEWITHDRAW00",
      "status": "paid",
      "requested_amount_minor": 80000,
      "requested_amount_formatted": "800.00 EGP",
      "paid_amount_minor": 80000,
      "paid_amount_formatted": "800.00 EGP",
      "currency": "EGP",
      "bank_account": { "account_holder": "...", "iban": "EG80**********************012", ... },
      "requested_at": "2026-05-04T10:00:00Z",
      "processed_at": "2026-05-04T15:30:00Z",
      "paid_at": "2026-05-04T15:30:00Z",
      "rejected_reason": null
    }
  ],
  "meta": {
    "pagination": {
      "per_page": 25,
      "next_cursor": "eyJyZXF1ZXN0ZWRfYXQiOiIyMDI2LTA1LTAxIn0",
      "prev_cursor": null
    }
  },
  "errors": []
}
```

### Notes
- Vendor only sees their own; no `vendor_profile_id` query parameter exists.
- Newest-first by `requested_at`.

---

## GET `/api/v1/vendor/withdrawals/{public_id}`

View a single withdrawal.

### Request
- Path param: `public_id` (CHAR(26) ULID)

### Permission
- `settlement.view_withdrawals.own` + ownership check (`vendor_profile_id` matches authenticated vendor)

### Response — 200 OK

Same shape as a single item from the list response.

### Response — 404 Not Found

```json
{
  "data": null,
  "meta": {},
  "errors": [{
    "code": "not_found",
    "message_key": "settlement.errors.withdrawal_not_found"
  }]
}
```

Returned both when the withdrawal doesn't exist AND when it belongs to a different vendor (avoid information disclosure).

### Notes
- `rejected_reason` is locale-resolved from JSON via `Accept-Language` at the API Resource layer.
- `bank_account.iban` is masked the same way as the create response.

---

## Internal contracts (implementation)

| Action | Inputs | Output | Events |
|---|---|---|---|
| `RequestWithdrawalAction::execute(RequestWithdrawalDto $dto, User $requester)` | DTO + auth user | `Withdrawal` | `WithdrawalRequested` (after commit) |
| `ApproveAndMarkWithdrawalPaidAction::execute(Withdrawal $w, UploadedFile $proof, User $admin)` | withdrawal + file + admin | `Withdrawal` (status=paid) | `WithdrawalPaid` (after commit) |
| `RejectWithdrawalAction::execute(Withdrawal $w, array $reason_jsonb, User $admin)` | withdrawal + EN/AR reason + admin | `Withdrawal` (status=rejected) | `WithdrawalRejected` (after commit) |

All actions wrap in `DB::transaction` and dispatch events via `DB::afterCommit()`.
