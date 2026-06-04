# Customer API Audit — Phase 1 (Read-Only)

**Date:** 2026-06-04 (prompt template said 2026-05-25; corrected to actual date)
**Branch:** 057-vendor-mobile-gaps
**Scope:** Customer-facing APIs (`/api/v1/customer/*`, public catalog/discovery/theme/CMS/geography, auth) vs. the 85-endpoint inventory supplied in the task prompt.
**Method:** `php artisan route:list` (150 `api/*` routes total; 72 customer-facing), module route files, controllers, Actions, API Resources, and the Pest test inventory.

---

## ⚠️ Pre-Audit Findings (read first)

1. **Referenced spec files do not exist in the repo.** `docs/specs/` contains **no** `09_Phasing_Plan_v2.md`, `14_PRD_Coverage_Additions.md`, `15_Critical_Risk_Audit.md`, `16_Customer_Frontend_Plan.md`, `17_Customer_Mobile_App_Plan.md`, `17a_Customer_Mobile_Vendor_Section.md`, or `19_Theming_Usage_Backend.md`. (Same situation as spec 18 found in the 2026-06-04 vendor-mobile audit.) The endpoint inventory embedded in the prompt was treated as authoritative.
2. **The inventory's URI conventions differ from the codebase's locked conventions.** The codebase nests public catalog/discovery/geography under `/api/v1/customer/*` (public, no auth) and auth under `/api/v1/{login,register,phone,password}`. Endpoints were matched **semantically**, not by literal URI. Renaming existing live URIs to match the prompt would break the deployed Next.js frontend — **not recommended**.
3. **The inventory's count is off:** the tables contain **98 rows**, not 85 (F10 lists 8 rows labelled "6 endpoints", F12 lists 10 labelled "8", F17 lists 5 labelled "4").
4. **Phase-scope conflicts (pushback per CLAUDE.md):**
   - **F9 Packages (9.2, 9.3):** platform-owned package products are **explicitly Phase 2** (CLAUDE.md §Phase 1 Scope / PRD §5.2). Recommend descoping from this effort.
   - **F1 Facebook OAuth (1.3–1.5):** `laravel/socialite` is **not on the locked package list** (`10_Package_List.md`). Requires a package-list conversation before any build.
   - **F14.2 `POST /customer/chat/send`:** contradicts the **spec 056 Firestore-first architecture** (client writes to Firestore; Cloud Function moderates + mirrors to MySQL via the internal endpoint). Recommend keeping the 056 design and marking 14.2 N/A-by-design.
   - **12.10 tax invoice request:** Tax module exists as *foundational readiness* only; a customer-facing request endpoint is borderline Phase 2 — confirm before building.

---

## Summary Table — All 98 Rows

Legend: ✅ EXISTS (semantic match) · ⚠️ PARTIAL · ❌ MISSING · 🚫 OUT-OF-SCOPE / BY-DESIGN

### Feature 1 — Auth

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 1.1 | POST /auth/otp/request | ⚠️ | `POST /api/v1/phone/otp/send` exists (IP throttle + `OtpRateLimiter` FR-I15) — but used for **phone verification**, not OTP **login**. Current login is email/password (`POST /api/v1/login`). |
| 1.2 | POST /auth/otp/verify | ⚠️ | `POST /api/v1/phone/verify` verifies phone; does **not** issue a token. OTP-login flow missing. |
| 1.3 | POST /auth/facebook/start | 🚫 | Missing AND `laravel/socialite` not on locked package list — needs approval first. |
| 1.4 | POST /auth/facebook/callback | 🚫 | Same. |
| 1.5 | POST /auth/facebook/link-phone | 🚫 | Same. |
| 1.6 | POST /auth/logout | ✅ | `POST /api/v1/logout` (`auth:sanctum` + `ensure.account.active`). |
| 1.7 | GET /auth/me | ⚠️ | Equivalent: `GET /api/v1/customer/profile`. No dedicated `/auth/me` with capability flags. |
| 1.8 | GET /sanctum/csrf-cookie | ✅ | Sanctum built-in registered. |

### Feature 2 — Profile

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 2.1 | GET /customer/profile | ✅ | `CustomerProfileController@show`, full middleware stack. |
| 2.2 | PATCH /customer/profile | ✅ | Exists as `PUT` (tested: `CustomerProfileUpdateTest`). |
| 2.3 | POST /customer/profile/avatar | ❌ | No avatar endpoints anywhere in Identity. Media-library foundation exists (ADR-0047). |
| 2.4 | DELETE /customer/profile/avatar | ❌ | — |
| 2.5 | PATCH /customer/profile/change-phone | ❌ | OTP infra exists but no authenticated change-phone flow. |

### Feature 3 — Addresses

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 3.1 | GET /customer/addresses | ✅ | `CustomerAddressController@index`. |
| 3.2 | POST /customer/addresses | ✅ | `@store`. Tested (`CustomerAddressTest`). |
| 3.3 | PATCH /customer/addresses/{id} | ❌ | No update route — only index/store/destroy. |
| 3.4 | DELETE /customer/addresses/{id} | ✅ | `@destroy`. |
| 3.5 | POST .../set-default | ❌ | No set-default route. |

### Feature 4 — Notification Preferences

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 4.1 | GET /customer/notification-preferences | ✅ | `NotificationPreferenceController@indexCustomer`. |
| 4.2 | PATCH /customer/notification-preferences | ✅ | Exists as `PUT .../{channel}/{event_category}` (per-row update — same capability). |

### Feature 5 — Catalog (public)

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 5.1 | GET /catalog/occasions | ✅ | `GET /api/v1/customer/occasions` (public). |
| 5.2 | GET /catalog/occasions/{slug} | ❌ | No occasion detail endpoint. |
| 5.3 | GET /catalog/categories | ✅ | `GET /api/v1/customer/categories` (public). |
| 5.4 | GET /catalog/categories/{id} | ❌ | No category detail endpoint. |
| 5.5 | GET .../field-schemas (FR-20) | ❌ | `category_field_schemas` table + admin CRUD exist (`CategoryFieldSchemaCrudTest`) — **no public endpoint**. |
| 5.6 | GET /catalog/service-themes | ❌ | `service_themes` table + admin CRUD exist — no public endpoint. |

### Feature 6 — Discovery / Search

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 6.1 | GET /discovery/services | ✅ | `GET /api/v1/customer/services` → `ServiceSearchController` (Meilisearch/Scout). |
| 6.2 | GET /discovery/suggestions | ❌ | No autocomplete endpoint. |
| 6.3 | GET /discovery/vendors | ✅ | `GET /api/v1/customer/vendors` → `VendorProfileIndexController`. |
| 6.4 | POST .../recent-searches | ❌ | `saved_searches` + `search_logs` tables exist — no customer endpoint. |

### Feature 7 — Service Detail

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 7.1 | GET /catalog/services/{id} | ✅ | `CustomerServiceDetailResource` — **type-aware** via `serviceTypeBlock()` ✓ (contract tested: `ServiceContractShapeTest`). |
| 7.2 | POST .../check-availability | ❌ | `service_inventory_reservations` machinery exists internally — no public check endpoint. |
| 7.3 | GET .../reviews | ✅ | `GET /api/v1/public/services/{id}/reviews` (+ bonus `rating-summary`). |
| 7.4 | GET .../similar | ❌ | Phase 1.5 per prompt — not built. |
| 7.5 | POST .../views | ❌ | No view-tracking endpoint (`analytics_events` table exists). |

### Feature 8 — Vendor Browsing (customer-facing)

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 8.1 | GET /customer/vendors/{id} | ⚠️ | `VendorProfileShowController` exists, **privacy-clean** ✓, but missing the composite payload (stats block, today_hours, featured_review, top_services, portfolio_preview) and no caching. Bonus: `GET .../badges` (TrustSafety) exists. |
| 8.2 | GET .../services | ❌ | No dedicated vendor-services endpoint (verify whether `ServiceSearchController` accepts a vendor filter). |
| 8.3 | GET .../reviews | ✅ | `GET /customer/vendors/{id}/reviews` + `GET /public/vendors/{id}/reviews`. |
| 8.4 | GET .../reviews/summary | ✅ | As `GET /public/vendors/{id}/rating-summary`. |
| 8.5 | GET .../portfolio | ❌ | No portfolio endpoint (media collections exist). |
| 8.6 | GET .../coverage | ❌ | `vendor_coverage_areas` table exists — no customer endpoint. |
| 8.7 | GET .../availability | ❌ | `vendor_business_hours` table exists — no customer endpoint. |
| 8.8 | POST .../availability/check | ❌ | — |

### Feature 9 — Wizard / Packages 🚫 PHASE 2

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 9.1 | POST /customer/wizard/session | ❌ | Not built; no repo spec backs it. |
| 9.2 | GET /customer/packages | 🚫 | **Phase 2** — platform-owned packages explicitly out of Phase 1 scope (CLAUDE.md / PRD §5.2). Pushback. |
| 9.3 | GET /customer/packages/{id} | 🚫 | Same. |

### Feature 10 — Cart (architecture difference: draft-booking pattern)

The codebase has **no cart**; the equivalent is the locked draft-booking flow (spec 005): `POST /bookings` creates a draft, items are added/removed, then `POST /bookings/{id}/submit`. Hold expiry is exposed (`hold_expires_at` on `BookingResource`).

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 10.1 | GET /customer/cart | ⚠️ | Equivalent: `GET /bookings/{id}` on draft. No "current draft" lookup endpoint. |
| 10.2 | POST /customer/cart/items | ✅ | Equivalent: `POST /bookings/{id}/items` (tested incl. per-type: `AddItemToBookingTest`). |
| 10.3 | PATCH /customer/cart/items/{id} | ❌ | No item-update endpoint — only add/remove. |
| 10.4 | DELETE /customer/cart/items/{id} | ✅ | `DELETE /bookings/{id}/items/{itemId}`. |
| 10.5 | PATCH /cart/event-context | ❌ | Event context fixed at draft creation; no update endpoint. |
| 10.6 | POST /cart/apply-promo | ⚠️ | `POST /customer/promo-codes/validate` exists; verify it **applies** to the draft (booking has `discount_promo_minor`). |
| 10.7 | DELETE /cart/promo | ❌ | No promo-removal endpoint. |
| 10.8 | POST /cart/clear | ❌ | No draft-discard endpoint. |

### Feature 11 — Checkout

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 11.1 | POST /checkout/review | ❌ | No pre-submit review endpoint (submit response shape covers some of it). |
| 11.2 | POST /customer/bookings (submit) | ✅ | `POST /bookings/{id}/submit` (tested: `SubmitBookingTest`, `BookingIdempotencyTest`, `SubmitBookingMinOrderTest`). |
| 11.3 | POST .../initiate-payment | ✅ | `POST /bookings/{id}/payments` — **`idempotency` middleware ✓**. |
| 11.4 | POST .../payment/callback | ⚠️ | Gateway webhook exists (`/api/v1/webhooks/...`); **customer redirect callback** route not found — verify Paymob iframe return flow. |
| 11.5 | GET .../payment-status | ✅ | `GET /customer/payments/{paymentPublicId}`. |

### Feature 12 — Bookings

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 12.1 | GET /customer/bookings | ✅ | Status filter + cursor pagination (tested). |
| 12.2 | GET /customer/bookings/{id} | ✅ | Full resource w/ vendors, items, modification proposals, money breakdown. |
| 12.3 | GET .../cancellation-preview | ❌ | **No refund-preview endpoint.** `RefundPolicyService` per-type policy required. |
| 12.4 | POST .../cancel | ❌ | **CRITICAL: customers cannot cancel a booking via API at all.** |
| 12.5 | GET .../modifications | ✅ | `BookingNegotiationController@listModifications`. |
| 12.6 | GET .../modifications/{modId} | ⚠️ | No dedicated detail endpoint; diff data partially exposed via `active_modification_proposal` (uses `diff_snapshot`). `BookingDiffDTO` as specified not found. |
| 12.7 | POST .../modifications/{modId}/accept | ✅ | As `POST .../decide` (accept+reject unified; action-level idempotency in `CustomerConfirmModifiedBookingAction` ✓; tested: `CustomerDecisionTest`). |
| 12.8 | POST .../modifications/{modId}/reject | ✅ | Same `decide` endpoint. |
| 12.9 | POST .../pay-balance | ⚠️ | `InitiatePaymentController` may cover balance payments — verify remaining-balance flow. |
| 12.10 | POST .../request-tax-invoice | ❌ | Tax module is foundational-only; confirm Phase 1 scope before building. |

### Feature 13 — Reviews

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 13.1 | GET /customer/reviews | ✅ | `ListMyReviewsController`. |
| 13.2 | POST /bookings/{id}/reviews | ⚠️ | Different granularity (locked schema: per `booking_item` + per `booking_vendor`): `POST /booking-items/{id}/review` + `POST /booking-vendors/{id}/review`. No batch per-booking endpoint; photo upload support unverified. |
| 13.3 | PATCH /customer/reviews/{id} | ❌ | No edit-own-review endpoint. |
| 13.4 | DELETE /customer/reviews/{id} | ✅ | `DELETE /reviews/{reviewType}/{publicId}`. |

### Feature 14 — Chat

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 14.1 | GET /customer/chat/threads | ⚠️ | Per-booking only: `GET /bookings/{booking}/chat-threads`. No global thread list. Bonus: `GET /chat/identity` (Firebase custom-token bridge) ✓. |
| 14.2 | POST /customer/chat/send | 🚫 | **By design** (spec 056): client writes to Firestore directly; Cloud Function moderates and mirrors via internal endpoint. Building a REST send endpoint would contradict the accepted architecture. |
| 14.3 | POST /customer/chat/upload-url | ❌ | No signed-URL endpoint for chat attachments — confirm whether 056 scopes attachments. |

### Feature 15 — Loyalty

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 15.1 | GET /customer/loyalty/balances | ✅ | Tested (`CustomerLoyaltyBalancesTest`). Bonus: per-vendor balance + redemption endpoints (idempotency ✓, throttled ✓). |
| 15.2 | GET .../vendors/{id}/history | ❌ | `loyalty_ledger` exists — no customer history endpoint. |
| 15.3 | GET .../vendors/{id}/rules | ❌ | `loyalty_rules` exists — no customer rules endpoint (verify whether balance response embeds rules). |

### Feature 16 — Wishlist

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 16.1 | GET /wishlist/services | ✅ | As `GET /customer/wishlist`. |
| 16.2 | POST /wishlist/services | ✅ | As `POST /customer/wishlist/items`. |
| 16.3 | DELETE /wishlist/services/{id} | ✅ | As `DELETE /customer/wishlist/items/{servicePublicId}`. |
| 16.4 | GET /wishlist/vendors | ✅ | `VendorWishlistController` (tested: `VendorWishlistTest`). |
| 16.5 | POST /wishlist/vendors | ✅ | ✓ |
| 16.6 | DELETE /wishlist/vendors/{id} | ✅ | ✓ |

⚠️ **Middleware gap:** services-wishlist routes (16.1–16.3) have only `auth:sanctum` — **no `role:customer`, no locale, no `ensure.account.active`** (vendor wishlist routes have the full stack). A vendor token can mutate a services wishlist.

### Feature 17 — Notifications (in-app)

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 17.1 | GET /customer/notifications | ❌ | **Entire feature missing.** `notification_dispatches` table exists; no customer read API. |
| 17.2 | GET .../unread-count | ❌ | — |
| 17.3 | PATCH .../{id}/mark-read | ❌ | — |
| 17.4 | POST .../mark-all-read | ❌ | — |
| 17.5 | DELETE .../{id} | ❌ | — |

### Feature 18 — Public Misc

| # | Spec endpoint | Status | Actual / gap |
|---|---|---|---|
| 18.1 | GET /cms/homepage | ✅ | `HomepageController`. |
| 18.2 | GET /cms/pages/{slug} | ✅ | `CmsPageController@show`. |
| 18.3 | GET /theme/tokens | ✅ | `DesignTokenController`. |
| 18.4 | GET /theme/branding | ✅ | `BrandingController` (+ bonus `theme/menus`). |
| 18.5 | GET /geography/cities | ✅ | `GET /customer/cities` (+ bonus `governorates`). |
| 18.6 | GET /geography/cities/{id}/areas | ❌ | `regions` exist in Geography schema — no endpoint. |
| 18.7 | POST /customer/devices | ❌ | `user_devices` table exists — **no FCM device registration API** (blocks mobile push). |
| 18.8 | DELETE /customer/devices/{token} | ❌ | — |

---

## Totals

| Status | Count |
|---|---|
| ✅ EXISTS | **40** |
| ⚠️ PARTIAL | **12** |
| ❌ MISSING | **40** |
| 🚫 OUT-OF-SCOPE / BY-DESIGN (needs your ruling) | **6** (9.2, 9.3, 1.3–1.5, 14.2) |
| **Total rows** | **98** |

---

## Privacy Audit (Step 1.3) — Result: CLEAN ✅

Inspected: `Discovery\VendorProfileResource`, `Booking\BookingResource`, `Booking\BookingVendorResource`, `Reviews\Public*ReviewResource`, `Catalog\CustomerServiceDetailResource`.

- ✅ `VendorProfileResource`: exposes only public_id, business_name, bio, is_verified (derived boolean), city, ratings, logo/cover, response hours, product types, services count, member_since. **No commission / tier / bank / documents / owner PII / wallet / locale / admin notes.**
- ✅ `BookingResource` / `BookingVendorResource`: money breakdown + vendor display info only. No vendor bank/commission. Address block is the customer's own booking snapshot — correct.
- ✅ Public review resources mask reviewer names to **first name + initial** (`PublicServiceReviewResource`, `PublicVendorReviewResource`).
- ⚠️ Not exhaustively verified: `ServiceSearchResultResource`, `WishlistItemResource`, `MyReviewResource` — recommend negative-assertion tests in Phase 2 regardless (cheap insurance).

**No CRITICAL privacy leaks found.**

## Three Product Types Audit (Step 1.4)

- ✅ `CustomerServiceDetailResource` emits a per-type block via `BuildsServiceContract::serviceTypeBlock()`.
- ✅ Booking item add tested per type (`VendorPortal/Modification/AddItemPerTypeTest`, `AddItemToBookingTest`).
- ⚠️ When 7.2 (check-availability) and 12.3/12.4 (cancel + preview) are built they MUST go through per-type slot resolvers and `RefundPolicyService->policyFor($productType)` — flagged for Phase 3.

## Bilingual Audit (Step 1.5)

- ✅ Identity/Shared/Booking routes carry `SetLocaleMiddleware` / `locale`.
- ⚠️ **MEDIUM:** Catalog, Discovery, Geography, Reviews-public route groups have only `api` middleware — **no locale middleware**. Resources fall back to `Accept-Language` parsing inline (`VendorProfileResource`) or `app()->getLocale()` (`CustomerServiceDetailResource`), which is inconsistent. Default-locale behavior (prompt says default **ar**) needs one canonical middleware on every public group.
- ⚠️ Inconsistent default: `VendorProfileResource` defaults to **en**, `BookingVendorResource` defaults to **ar**. Pick one (spec says ar) and normalize.

## Money Audit (Step 1.6) — Result: CLEAN ✅

- ✅ All inspected resources return `*_minor` + `currency`; `BookingVendorResource` uses `Brick\Money::ofMinor()->formatTo($locale)` for formatted strings.
- ✅ Pricing snapshot at booking time (subtotal/discount/total columns on booking; `commission_bps` snapshot per locked schema).
- ⚠️ `due_minor` is computed arithmetic in `BookingResource:52` (raw int subtraction). Works for same-currency, but consider `Money` ops for consistency.

## Other Findings

| Finding | Severity |
|---|---|
| Customer cannot cancel a booking (12.4 missing entirely) | **CRITICAL (functional)** |
| Services wishlist routes missing `role:customer` (vendor tokens accepted) | **HIGH** |
| No FCM device registration (18.7–18.8) — blocks mobile push for the Flutter app | **HIGH** |
| In-app notifications feature fully missing (F17) | **HIGH** |
| `POST /customer/support/tickets` has **no auth middleware** (`api` only) — confirm guest tickets are intentional | **MEDIUM** |
| Idempotency middleware only on Payments + Loyalty routes; booking submit relies on action-level handling (tested) but `POST /bookings` + `POST items` have none | **MEDIUM** |
| No throttle on public catalog/discovery routes (prompt wants `throttle:public_api` 60/min) | **MEDIUM** |
| Booking customer routes lack `ensure.account.active` (Identity routes have it) | **LOW** |
| Scribe docs last generated 2026-05-13 (63 endpoints documented vs 150 routes) | **MEDIUM** |

---

## Estimated Effort to Close Gaps (rough)

| Work block | Endpoints | Est. |
|---|---|---|
| Booking cancel + cancellation-preview (per-type `RefundPolicyService`, refund ledger, Paymob refund call, events, tests ×3 types) | 12.3, 12.4 | 10–14 h |
| In-app notifications (list/unread/mark-read/mark-all/delete + tests) | F17 (5) | 6–8 h |
| FCM devices register/unregister | 18.7, 18.8 | 2–3 h |
| Vendor browsing pack (services, portfolio, coverage, availability, availability-check, composite 8.1 upgrade, caching) | F8 (6) | 12–16 h |
| Catalog detail pack (occasion/category detail, field-schemas, service-themes public) | 5.2, 5.4, 5.5, 5.6 | 6–8 h |
| Profile pack (avatar up/down, change-phone w/ OTP) | 2.3–2.5 | 5–7 h |
| Address update + set-default | 3.3, 3.5 | 2–3 h |
| Draft-booking ergonomics (current-draft GET, item PATCH, event-context PATCH, promo apply/remove, discard) | F10 gaps (5) | 8–12 h |
| Checkout review + payment redirect callback + pay-balance verification | 11.1, 11.4, 12.9 | 5–8 h |
| Reviews edit + photo support verification | 13.2, 13.3 | 4–6 h |
| Discovery extras (suggestions, recent-searches, similar, views) | 6.2, 6.4, 7.4, 7.5 | 6–10 h |
| Loyalty history + rules | 15.2, 15.3 | 3–4 h |
| Service availability check (per-type slot resolvers) | 7.2, 8.8 | 6–8 h |
| Geography areas | 18.6 | 1–2 h |
| Chat thread global list + upload-url (056-consistent) | 14.1, 14.3 | 3–5 h |
| Middleware hardening (wishlist role, locale normalization, public throttle) | cross-cutting | 3–4 h |
| OTP-login flow (1.1/1.2 semantics) — **needs product decision** (replaces or complements password login) | 1.1, 1.2, 1.7 | 6–8 h |
| **Total (excluding 🚫 items)** | | **~88–126 h** |

Excluded pending your ruling: Facebook OAuth (package list), Packages/Wizard (Phase 2), chat REST send (contradicts 056), tax-invoice request (scope).

---

## Rulings — Ibrahim, 2026-06-04

| Item | Ruling |
|---|---|
| 9.2 + 9.3 Packages | **DESCOPED** to Phase 2. Replaced by lightweight `POST /customer/discovery/recommendations` scoring existing **services** (occasion 40 / city 30 / age-range 20 / price-fit 10 — weights from the task prompt; file 14 §1 absent from repo). No packages table in Phase 1. |
| 1.3–1.5 Facebook OAuth | **DEFERRED** to Phase 1.5. `laravel/socialite` requires a package-list conversation first. Phone OTP sufficient for launch. |
| 14.2 chat send | **N/A by design** — spec-056 Firestore-first stands. Moderation stays in the Firebase Function. 14.1 (threads list mirror) + 14.3 (signed upload URL) remain in scope. |
| 1.1 + 1.2 OTP-as-login | **KEEP** current email/password login; OTP remains phone-verification only. Backlog item for Phase 1.5: consider OTP-as-primary. |
| 12.10 Tax invoice | **FOUNDATIONAL ONLY** — store `{requires_tax_invoice, invoice_name, invoice_tax_id}` on booking. No PDF generation, no tax-authority integration (Phase 2). |
| 9.1 Wizard session | Recommended **DROP** (client-side state passed as params to recommendations endpoint) — awaiting confirmation. |

**Phase 3 scope after rulings:** 40 ❌ − 9.1 + 1 new recommendations endpoint ≈ **40 endpoints** + closure of the 12 ⚠️ partials.
