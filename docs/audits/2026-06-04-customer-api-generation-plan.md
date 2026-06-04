# Customer API Generation Plan — Phase 3 (Single-Shot Autonomous)

**Date:** 2026-06-04 · **Branch:** 057-vendor-mobile-gaps
**Input:** `docs/audits/2026-06-04-customer-api-audit.md` (+ recorded rulings)
**Mode:** autonomous; deviations logged, no approval pauses.

## Dropped (per rulings — will NOT be generated)
9.1 wizard-session · 9.2/9.3 packages · 1.3–1.5 Facebook OAuth · 14.2 chat/send (spec-056 Firestore-first)

## Infrastructure verified before planning
| Need | Status |
|---|---|
| `RefundPolicyService` per-type via `match` | ✅ EXISTS — `Payments/Application/Services/RefundPolicyService.php` (reuse, do not recreate) |
| Refund machinery | ✅ `InitiateRefundAction`, `OnBookingForceCancelledInitiateRefundListener`, `ForceCancelBookingAction` (admin) — customer cancel reuses the same event→refund path |
| `user_devices` table + `UserDevice` model | ✅ EXISTS (Identity) — endpoints only |
| `notification_dispatches` | ✅ EXISTS — needs `read_at` column migration for in-app inbox |
| `customer_wishlist_vendors` | ✅ EXISTS + endpoints already live (16.4–16.6 were ✅ in audit; C2 is a no-op, logged as deviation vs. the execution order given) |
| `IdempotencyService` (Shared) | ✅ EXISTS + `idempotency` middleware alias |
| Tax invoice columns on bookings | ❌ migration needed (A1a) |
| `booking_cancellations` table | **DECISION: not created** — cancellation is recorded via existing append-only `booking_state_transitions` + `refunds` + `audit_logs`. A new table would duplicate ledger state (schema is LOCKED at 60 tables). |

## Phase A — Foundations
- A1a `bookings` migration: `requires_tax_invoice` BOOL, `invoice_name` VARCHAR NULL, `invoice_tax_id` VARCHAR NULL (foundational only, ruling #5)
- A1b `notification_dispatches` migration: `read_at` TIMESTAMP NULL + index `(user_id, channel, read_at)`
- A2 `php artisan migrate`
- A3 new models: none (no new tables)
- A4 RefundPolicyService: reuse existing ✅
- A5 (carried from Phase 2) fix 2 red `BookingHoldExpiryTest` cases (UnknownState — test-side payment factory state value)

## Phase B — P0 Critical
- B1 **Cancellation** (Booking+Payments):
  - `GET /customer/bookings/{id}/cancellation-preview` → `PreviewBookingCancellationAction` — per-item `RefundPolicyService->policyFor(match)`, returns per-item refundable flags + totals
  - `POST /customer/bookings/{id}/cancel` → `CancelBookingByCustomerAction` — guards lifecycle states, DB::transaction, state transition, `BookingCancelledByCustomer` event afterCommit (scalar payload), refund initiation listener path, audit log, `idempotency` middleware
- B2 **Notifications inbox** (Communication, on `notification_dispatches` channel=in_app):
  - `GET /customer/notifications` (paginated) · `GET /customer/notifications/unread-count` · `PATCH /customer/notifications/{publicId}/mark-read` · `POST /customer/notifications/mark-all-read` · `DELETE /customer/notifications/{publicId}`
  - DECISION: DELETE = hard delete of the user's own in-app dispatch row (inbox UX, table is not on the append-only list)
- B3 **FCM devices** (Identity): `POST /customer/devices` (upsert by token) · `DELETE /customer/devices/{token}`

## Phase C — P1 High Impact
- C1 **Vendor browsing** (Discovery):
  - 8.1 composite payload upgrade (stats, coverage_summary, today_hours, featured_review, top_services, portfolio_preview) + 5-min cache
  - 8.2 `GET /customer/vendors/{id}/services` · 8.5 `/portfolio` · 8.6 `/coverage` · 8.7 `/availability` · 8.8 `POST /availability/check`
- C3 **Recommendations**: `POST /customer/discovery/recommendations` — service scoring occasion 40 / city 30 / age-range 20 / price-fit 10 (weights from task prompt; spec file 14 absent)
- C4 **Checkout gaps**: 11.1 `POST /customer/checkout/review` (validates draft, returns priced summary) · 11.4 Paymob redirect callback `POST /customer/bookings/{id}/payment/callback` · 12.9 pay-balance — verify `InitiatePaymentController` covers partial payment; close gap or document

## Phase D — P2 Polish
- D1 12.6 `GET .../modifications/{modId}` detail (diff_snapshot exposure)
- D2 12.10 `POST .../request-tax-invoice` (stores 3 columns from A1a; no PDF/authority)
- D3 13.3 `PATCH /customer/reviews/{publicId}` (own, pre-moderation only)
- D4 Catalog/discovery/misc gaps: 5.2 occasion detail · 5.4 category detail · 5.5 field-schemas · 5.6 service-themes · 6.2 suggestions · 6.4 recent-searches · 7.2 check-availability (per-type) · 7.4 similar · 7.5 views · 18.6 cities/{id}/areas · 15.2 loyalty history · 15.3 loyalty rules · 14.1 global chat threads list · 14.3 chat upload-url · 10.1/10.3/10.5/10.7/10.8 draft-booking ergonomics · 2.3–2.5 avatar + change-phone · 3.3/3.5 address update + set-default

## Phase E — Partial closures (12 ⚠️)
Idempotency middleware on draft-booking mutations · locale middleware on public catalog groups · public throttle (`throttle:60,1`) on catalog/discovery · `ensure.account.active` alignment · privacy negative-test extension to remaining resources

## Phase F — Verification
Full suite ≥90% · `route:list` confirmation · final report (`2026-06-04-customer-api-generation-final-report.md`)

## Test policy
Each feature ships with focused Pest tests (happy, 401/403, validation, idempotency where applicable, all 3 types where type-aware, privacy negatives where vendor data). Counts scoped per-user (dev seeders pollute global counts). New tests run per feature via `--filter`/file path; full suite at the end.

## Sequencing note
Phases execute in order; if context/runtime forces triage, the report marks every artifact DONE / PARTIAL / NOT-STARTED honestly — no silent scope shrink.
