# InstaParty — API Registry

> **Single source of truth for all InstaParty API endpoints.**
> Used by the Flutter mobile team and the Next.js web team as their canonical contract.
> Updated after each phase by `/speckit.implement` (or by hand when an endpoint is added/changed outside spec-kit).

---

## How to maintain this file

1. After every `/speckit.implement` run that adds or modifies an HTTP route, append (or update) one row per endpoint in the table below.
2. Order rows by **Phase** ascending, then by **Module**, then by **Endpoint** path.
3. **Method** uses the standard verb in uppercase (`GET`, `POST`, `PATCH`, `DELETE`).
4. **Endpoint** is the full path including the `/api/v1/` prefix and the role segment (`customer`, `vendor`, `admin`, `public`).
5. **Module** is the owning module under `app/Modules/{Name}` (e.g., `Identity`, `Catalog`, `Booking`).
6. **Phase** is the phase ID from `docs/specs/09_Phasing_Plan.md` (e.g., `1.0`, `3.2`).
7. **Auth** is one of `none`, `sanctum-cookie`, `sanctum-token`, `sanctum-cookie+token`.
8. **Roles** is the comma-separated list of Spatie permission/role names allowed (e.g., `customer`, `vendor.rental`, `admin`). Use `—` if not role-gated.
9. **Request Body** is a one-line shape hint or `—` for `GET`/no-body endpoints. Reference a Form Request class when it exists (e.g., `CreateRentalServiceRequest`).
10. **Response** is a one-line shape hint or the API Resource class name (e.g., `RentalServiceResource`, `BookingResource[]`). Always wrapped in the standard `ApiResponse` envelope `{ data, meta, errors }`.
11. **Documented** is one of `✅ openapi`, `✅ postman`, `📝 partial`, `❌ todo` — track that the contract is published to the mobile team somewhere (OpenAPI YAML, Postman collection, or inline doc block).

If a row needs more nuance than the table can carry (multi-step flows, idempotency keys, locale-specific behaviors, RTL hints), add a footnote section below the table — never widen the columns.

---

## Endpoint Registry

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response | Documented |
|---|---|---|---|---|---|---|---|---|
| <!-- first row goes here once Phase 1.0 ships --> | | | | | | | | |
| POST | /api/v1/customer/bookings/{bookingPublicId}/payments | Payments | 4.0 | sanctum-token | customer | InitiatePaymentRequest | ApiResponse{data: Payment init payload, meta, errors} | 📝 partial |
| GET | /api/v1/customer/payments/{paymentPublicId} | Payments | 4.0 | sanctum-token | customer | — | ApiResponse{data: PaymentResource, meta, errors} | 📝 partial |
| POST | /api/v1/webhooks/paymob | Payments | 4.0 | none | — | PaymobWebhookRequest | ApiResponse{data, meta, errors} | 📝 partial |
| POST | /api/v1/admin/bookings/{bookingPublicId}/refunds | Payments | 4.1 | sanctum-token | admin | InitiateRefundRequest | ApiResponse{data: Refund init payload, meta, errors} | 📝 partial |
| GET | /api/v1/admin/refunds/{refundPublicId} | Payments | 4.1 | sanctum-token | admin | — | ApiResponse{data: RefundResource, meta, errors} | 📝 partial |
| GET | /api/v1/vendor/wallet | Settlement | 4.2 | sanctum-token | vendor | — | ApiResponse{data: WalletResource, meta, errors} | ✅ postman |
| GET | /api/v1/vendor/wallet/ledger | Settlement | 4.2 | sanctum-token | vendor | — | ApiResponse{data: WalletLedgerEntryResource[], meta(cursor), errors} | ✅ postman |
| GET | /api/v1/vendor/withdrawals | Settlement | 4.2 | sanctum-token | vendor | — | ApiResponse{data: WithdrawalResource[], meta(cursor), errors} | ✅ postman |
| POST | /api/v1/vendor/withdrawals | Settlement | 4.2 | sanctum-token | vendor | RequestWithdrawalRequest | ApiResponse{data: WithdrawalResource, meta, errors} | ✅ postman |
| GET | /api/v1/vendor/withdrawals/{public_id} | Settlement | 4.2 | sanctum-token | vendor | — | ApiResponse{data: WithdrawalResource, meta, errors} | ✅ postman |
| GET | /api/v1/customer/notification-preferences | Communication | 5.0 | sanctum-token | customer | — | ApiResponse{data: NotificationPreferenceResource[], meta, errors} | ✅ postman |
| PUT | /api/v1/customer/notification-preferences/{channel}/{event_category} | Communication | 5.0 | sanctum-token | customer | UpdateNotificationPreferenceRequest | ApiResponse{data: NotificationPreferenceResource, meta, errors} | ✅ postman |
| GET | /api/v1/vendor/notification-preferences | Communication | 5.0 | sanctum-token | vendor | — | ApiResponse{data: NotificationPreferenceResource[], meta, errors} | ✅ postman |
| PUT | /api/v1/vendor/notification-preferences/{channel}/{event_category} | Communication | 5.0 | sanctum-token | vendor | UpdateNotificationPreferenceRequest | ApiResponse{data: NotificationPreferenceResource, meta, errors} | ✅ postman |
| POST | /api/v1/vendor/services/sale/import | Catalog | 6.1 | sanctum-token | vendor (sale-approved) | ImportSaleServicesRequest {store_id, file} | ApiResponse{data: {status, imported_rows, total_rows} \| {status, imported_rows, total_rows, errors[]}, meta, errors} | 📝 partial |
| POST | /api/v1/vendor/services/digital/import | Catalog | 6.1 | sanctum-token | vendor (digital-approved) | ImportDigitalServicesRequest {store_id, file} | ApiResponse{data: {status, imported_rows, total_rows} \| {status, imported_rows, total_rows, errors[]}, meta, errors} | 📝 partial |
| GET | /api/v1/cms/pages/{slug} | Shared | 6.2 | none | — | — (slug: terms\|privacy\|about\|contact) | ApiResponse{data: CmsPageResource (slug, title, body, meta_description, published_at), meta, errors} | 📝 partial |

---

## Footnotes

<!-- Add per-endpoint notes here as needed. Number them and reference from the table via superscript, e.g. `POST /api/v1/customer/bookings ¹`. -->

---

## Conventions reference (do not duplicate elsewhere)

- **Standard envelope:** every response is `{ "data": ..., "meta": {...}, "errors": [...] }` per CLAUDE.md §12.
- **Locale conversion:** translatable fields are returned in `App::getLocale()` only — clients pass `Accept-Language: en` or `ar`. Locale conversion happens in the API Resource layer, never in business logic.
- **Idempotency:** state-changing endpoints (booking submit, payment initiate, refund initiate, withdrawal request, booking modification confirm) accept an `Idempotency-Key` header; duplicate keys within 24h replay the cached response.
- **Pagination:** list endpoints use cursor pagination by default; `meta.next_cursor` and `meta.prev_cursor` are populated when applicable.
- **Money:** all money fields are returned as integer minor units paired with a currency code (e.g., `total_minor: 12500, total_currency: "EGP"`). Clients format for display.
- **Timestamps:** all timestamps are ISO 8601 in UTC (`2026-05-01T14:32:00Z`); clients convert to user timezone for display.
- **ULIDs in URLs:** all path parameters use `public_id` (CHAR(26) ULID), never internal `id`.
