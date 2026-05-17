# Contract — GET /api/v1/vendor/withdrawals/{public_id}

**Method**: GET
**Path**: `/api/v1/vendor/withdrawals/{public_id}`
**Auth**: Sanctum token (vendor)
**Permission**: `settlement.view_withdrawals.own`
**Tenant scope**: must own the withdrawal (vendor profile match) — else `404`
**Idempotency**: read-only; no key required
**Cache**: `Cache-Control: private, no-store`

## Path parameters

- `public_id` — CHAR(26) ULID

## Query parameters

- (none)

## Headers

- `Accept-Language: en` or `ar` — drives locale resolution of `admin_payment_note` and `rejected_reason`

## Response 200 — paid withdrawal (EN locale)

```json
{
  "data": {
    "public_id": "01HXY7VWNQTQX5ZP3ZK1NE5K2C",
    "amount": {
      "minor": 50000,
      "currency": "EGP",
      "formatted": "EGP 500.00"
    },
    "status": "paid",
    "timeline": [
      {
        "event": "requested",
        "at": "2026-05-12T08:00:00Z",
        "by_type": "vendor",
        "by_public_id": "01H8M6JK7XYTQQK5R8K1NE5VND"
      },
      {
        "event": "approved",
        "at": "2026-05-14T10:30:00Z",
        "by_type": "admin",
        "by_public_id": "01H9A2BQ8X3K9YK5R8K1NE5KKW"
      },
      {
        "event": "paid",
        "at": "2026-05-16T11:00:00Z",
        "by_type": "admin",
        "by_public_id": "01H9A2BQ8X3K9YK5R8K1NE5KKW"
      }
    ],
    "bank_account": {
      "holder_name": "Acme Events LLC",
      "bank_name": "CIB",
      "iban_masked": "EG**********4421"
    },
    "bank_transfer_reference": "EGTBNK-2026-05-16-00041",
    "admin_payment_note": "Transfer confirmed by branch.",
    "proof_download_url": "https://s3-private.instaparty.example/withdrawals/01HXY…/proof.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Expires=900&…",
    "proof_download_url_expires_at": "2026-05-16T11:15:00Z",
    "rejected_reason": null
  },
  "meta": null,
  "errors": null
}
```

## Response 200 — paid withdrawal (AR locale)

```json
{
  "data": {
    "public_id": "01HXY7VWNQTQX5ZP3ZK1NE5K2C",
    "amount": {
      "minor": 50000,
      "currency": "EGP",
      "formatted": "٥٠٠٫٠٠ ج.م."
    },
    "status": "paid",
    "timeline": [
      { "event": "requested", "at": "2026-05-12T08:00:00Z", "by_type": "vendor", "by_public_id": "01H8M6JK7XYTQQK5R8K1NE5VND" },
      { "event": "approved",  "at": "2026-05-14T10:30:00Z", "by_type": "admin",  "by_public_id": "01H9A2BQ8X3K9YK5R8K1NE5KKW" },
      { "event": "paid",      "at": "2026-05-16T11:00:00Z", "by_type": "admin",  "by_public_id": "01H9A2BQ8X3K9YK5R8K1NE5KKW" }
    ],
    "bank_account": {
      "holder_name": "Acme Events LLC",
      "bank_name": "CIB",
      "iban_masked": "EG**********4421"
    },
    "bank_transfer_reference": "EGTBNK-2026-05-16-00041",
    "admin_payment_note": "تم تأكيد التحويل من الفرع.",
    "proof_download_url": "https://s3-private.instaparty.example/withdrawals/01HXY…/proof.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Expires=900&…",
    "proof_download_url_expires_at": "2026-05-16T11:15:00Z",
    "rejected_reason": null
  },
  "meta": null,
  "errors": null
}
```

## Response 200 — pending withdrawal (sparse)

```json
{
  "data": {
    "public_id": "01HXY7VWNQTQX5ZP3ZK1NE5K2C",
    "amount": { "minor": 50000, "currency": "EGP", "formatted": "EGP 500.00" },
    "status": "pending",
    "timeline": [
      { "event": "requested", "at": "2026-05-12T08:00:00Z", "by_type": "vendor", "by_public_id": "01H8M…" }
    ],
    "bank_account": { "holder_name": "Acme Events LLC", "bank_name": "CIB", "iban_masked": "EG**********4421" },
    "bank_transfer_reference": null,
    "admin_payment_note": null,
    "proof_download_url": null,
    "proof_download_url_expires_at": null,
    "rejected_reason": null
  },
  "meta": null,
  "errors": null
}
```

## Response 200 — rejected withdrawal

```json
{
  "data": {
    "public_id": "01HXY…",
    "amount": { "minor": 50000, "currency": "EGP", "formatted": "EGP 500.00" },
    "status": "rejected",
    "timeline": [
      { "event": "requested", "at": "2026-05-12T08:00:00Z", "by_type": "vendor", "by_public_id": "01H8M…" },
      { "event": "rejected",  "at": "2026-05-13T09:00:00Z", "by_type": "admin",  "by_public_id": "01H9A…" }
    ],
    "bank_account": { "holder_name": "Acme Events LLC", "bank_name": "CIB", "iban_masked": "EG**********4421" },
    "bank_transfer_reference": null,
    "admin_payment_note": null,
    "proof_download_url": null,
    "proof_download_url_expires_at": null,
    "rejected_reason": "Invalid IBAN format — please update bank details and resubmit."
  },
  "meta": null,
  "errors": null
}
```

## Response 404 — not found / not owned

```json
{
  "data": null,
  "meta": null,
  "errors": [
    {
      "code": "withdrawal_not_found",
      "title": "Not found",
      "detail": "No withdrawal matches the supplied identifier."
    }
  ]
}
```

## Response 401 — unauthenticated

Standard Sanctum unauthenticated response.

## Response 403 — missing permission

```json
{
  "data": null,
  "meta": null,
  "errors": [
    {
      "code": "permission_denied",
      "title": "Forbidden",
      "detail": "You do not have permission to view withdrawals."
    }
  ]
}
```

## Scribe / PHPDoc annotations

```php
/**
 * @group Vendor / Settlement
 *
 * Show a single withdrawal.
 *
 * @urlParam public_id string required ULID of the withdrawal. Example: 01HXY7VWNQTQX5ZP3ZK1NE5K2C
 *
 * @response 200 scenario="paid" file=docs/api/responses/vendor/show_withdrawal_paid_en.json
 * @response 200 scenario="paid (ar)" file=docs/api/responses/vendor/show_withdrawal_paid_ar.json
 * @response 200 scenario="pending" file=docs/api/responses/vendor/show_withdrawal_pending.json
 * @response 200 scenario="rejected" file=docs/api/responses/vendor/show_withdrawal_rejected.json
 * @response 404 scenario="not owned" {"data": null, "meta": null, "errors":[{"code":"withdrawal_not_found"…}]}
 */
```

## api-registry.md entry update

The existing row for `GET /api/v1/vendor/withdrawals/{public_id}` is updated:
- Change "Resource" column from `WithdrawalResource` to `WithdrawalResource (extended — adds timeline, bank_transfer_reference, admin_payment_note, proof_download_url)`
- Bump version note column to `2026-05-16 — Phase 4.11 audit fields`

No new endpoint row added.

## Bruno collection update

`docs/api/collections/settlement/05_show_withdrawal.bru` is updated with the new response example block (paid + EN locale).
