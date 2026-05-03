# Contract: Vendor Wallet Endpoints

**Module**: Settlement | **Audience**: vendor (sanctum-authenticated)

Two read-only endpoints for vendors to view their wallet balance and ledger history.

---

## GET `/api/v1/vendor/wallet`

Returns the authenticated vendor's wallet summary for the EGP currency.

### Request

| Header | Required | Notes |
|---|---|---|
| `Authorization: Bearer {token}` | yes | Sanctum token |
| `Accept-Language` | no | `en` (default) or `ar` |
| `Idempotency-Key` | no | not applicable (read-only) |

No body. No query parameters.

### Permission
- `settlement.view_wallet.own`

### Response — 200 OK

```json
{
  "data": {
    "public_id": "01HVMR3K8YXEXAMPLEWALLET00",
    "currency": "EGP",
    "balance_minor": 85000,
    "balance_formatted": "850.00 EGP",
    "pending_withdrawal_minor": 0,
    "pending_withdrawal_formatted": "0.00 EGP",
    "available_minor": 85000,
    "available_formatted": "850.00 EGP",
    "is_negative": false,
    "totals": {
      "credits_minor": 100000,
      "credits_formatted": "1,000.00 EGP",
      "debits_minor": 15000,
      "debits_formatted": "150.00 EGP"
    }
  },
  "meta": {},
  "errors": []
}
```

### Response — 401 Unauthenticated

```json
{
  "data": null,
  "meta": {},
  "errors": [{
    "code": "unauthenticated",
    "message_key": "auth.unauthenticated"
  }]
}
```

### Response — 403 Forbidden (no permission)

```json
{
  "data": null,
  "meta": {},
  "errors": [{
    "code": "forbidden",
    "message_key": "settlement.errors.no_wallet_access"
  }]
}
```

### Notes
- Returns 200 with zeros if vendor has no wallet yet (lazy-created on first credit).
- `available_minor` = `balance_minor − pending_withdrawal_minor` (can be 0; cannot be negative — clamped to 0 in the calculation, even when raw `balance_minor` is negative).
- All monetary values returned as both `_minor` (BIGINT) and `_formatted` (locale-formatted display string).

---

## GET `/api/v1/vendor/wallet/ledger`

Returns paginated ledger entries newest-first.

### Request

| Header | Required | Notes |
|---|---|---|
| `Authorization: Bearer {token}` | yes | Sanctum token |
| `Accept-Language` | no | `en` or `ar` |

| Query param | Required | Default | Notes |
|---|---|---|---|
| `per_page` | no | 25 | max 100 |
| `cursor` | no | — | opaque pagination cursor |
| `entry_type` | no | — | filter: `commission_credit` \| `refund_debit` \| `withdrawal_debit` \| `manual_adjustment` |
| `from` | no | — | ISO 8601 date — inclusive lower bound on `created_at` |
| `to` | no | — | ISO 8601 date — inclusive upper bound |

### Permission
- `settlement.view_wallet.own`

### Response — 200 OK

```json
{
  "data": [
    {
      "entry_type": "commission_credit",
      "amount_minor": 85000,
      "amount_formatted": "850.00 EGP",
      "currency": "EGP",
      "description": "Commission credit for booking PIBK-01HVABC",
      "related": {
        "type": "commission",
        "public_id": "01HVMR4K9XEXAMPLECOMMISSION00"
      },
      "created_at": "2026-05-03T14:22:00Z"
    },
    {
      "entry_type": "withdrawal_debit",
      "amount_minor": -80000,
      "amount_formatted": "-800.00 EGP",
      "currency": "EGP",
      "description": "Withdrawal paid to bank account ending 6789",
      "related": {
        "type": "withdrawal",
        "public_id": "01HVMR5K0XEXAMPLEWITHDRAW00"
      },
      "created_at": "2026-05-04T10:00:00Z"
    }
  ],
  "meta": {
    "pagination": {
      "per_page": 25,
      "next_cursor": "eyJjcmVhdGVkX2F0IjoiMjAyNi0wNS0wMVQwMDowMDowMFoiLCJpZCI6MTAwfQ",
      "prev_cursor": null
    }
  },
  "errors": []
}
```

### Response — 422 Validation

```json
{
  "data": null,
  "meta": {},
  "errors": [{
    "code": "validation",
    "field": "per_page",
    "message_key": "settlement.errors.per_page_too_large",
    "message": "per_page must be 100 or less"
  }]
}
```

### Notes
- `description` is locale-resolved server-side from `description_key` + `description_params` against `lang/{en,ar}/settlement.php`.
- Response is in `Accept-Language` locale. Locale falls back to `en` if `Accept-Language` not provided or unsupported.
- `amount_minor` is signed: positive for credits, negative for debits.
- Entries are returned newest-first by `created_at`.

---

## Internal contracts (implementation, not exposed)

| Action | Inputs | Output | Side effects |
|---|---|---|---|
| `WalletQueryService::balance(User $vendor, string $currency = 'EGP')` | vendor + currency | `WalletBalanceDto` | Reads cached `balance_minor` (no SUM query) |
| `WalletQueryService::ledger(User $vendor, LedgerFilters $filters)` | filters | `Paginator<WalletLedgerEntry>` | Cursor-paginated query on `wallet_ledger` |
