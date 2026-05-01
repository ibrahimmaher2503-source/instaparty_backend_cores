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
