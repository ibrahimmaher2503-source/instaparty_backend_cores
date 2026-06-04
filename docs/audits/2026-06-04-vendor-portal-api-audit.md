# Vendor Portal API Audit — Phase 1 (Read-Only)

**Date:** 2026-06-04 (evening) · **Branch:** 057-vendor-mobile-gaps
**Scope:** `/api/v1/vendor/*` vs. the 116-row inventory in the task prompt (header says ~100; the tables contain 116 rows).
**Method:** live `route:list` (67 vendor routes), spec `12 vendor portal plan.MD` v1.2, today's vendor-mobile audit (`2026-06-04-vendor-mobile-api-audit.md`), controllers/requests/actions spot-checks.

---

## ⚠️ Pre-Audit Findings (read first)

1. **Heavy overlap with work completed TODAY.** The vendor-mobile effort (same branch, earlier today) closed G1–G12: `/vendor/me`, dashboard summary, compliance view, booking-vendor detail (privacy-masked), preview-modification token handshake (FR-13, Redis TTL 30 min), schedule, booking-item detail + per-type transitions, condition photos, reviews inbox + respond, shared `/api/v1/devices`. **This prompt's "Not in scope: vendor-mobile prompt" framing is misleading — that effort built into the SAME `/api/v1/vendor/*` namespace** (per its ruling: no `vendor-mobile` prefix). This audit reflects the post-G12 state.
2. **Missing spec files:** `14_PRD_Coverage_Additions.md`, `15_Critical_Risk_Audit.md`, `18_Vendor_Mobile_App_Plan.md` do not exist in the repo (18 confirmed missing in this morning's audit too). `12 vendor portal plan.MD` v1.2 **exists** and is authoritative.
3. **The Filament portal is server-rendered (Livewire), not API-driven.** "Portal parity" = the API surface mirrors Filament features; several features exist as Filament resources with Actions but have **no REST route** (clone, archive flows partially, business-hours GET, etc.). Spec 12 §12 anticipated exactly this (Action class + API endpoint per feature).
4. **URI conventions differ** (same as the customer audit): bookings are modeled as `booking-vendors` (locked schema — one row per vendor per booking), excel lives under `catalog/import`, per-type create/update/delete under `services/{type}`. Matched semantically; renaming live URIs would break in-flight Flutter work.
5. **Schema-locked blockers carried over from the mobile audit:** G13 `bank_accounts` table (F13.1–13.5) and G14 DND hours are **not in the locked 60-table schema** — they need your explicit schema conversation, not autonomous generation. NOTE: withdrawals currently pay out via bank fields **on `vendor_profiles`** (bank_name/iban/swift…), i.e., single implicit bank account.
6. **Open questions answered from code:** `vendor` ability exists (1 route uses `ability:vendor`); approval enum = spatie states (Pending/Approved/Rejected/…); `vendor_approved_product_types` exists; `BookingDiffDTO` per se doesn't exist — `diff_snapshot` JSON is the implemented equivalent; `RefundPolicyService` exists (Payments); commission_rates seeded via `DefaultCommissionRatesSeeder`; Filament resources delegate to Actions (spec-12 pattern); Excel import job exists (strict no-partial-commit, tested); Reverb vendor channels not verified.

---

## Summary — 116 rows

Legend: ✅ EXISTS · ⚠️ PARTIAL · ❌ MISSING · 🔒 SCHEMA-LOCKED (needs your ruling) · Ⓕ exists in Filament only (no API route)

### F1 Auth & Onboarding (8)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 1.1–1.4 | register initiate/verify-email/verify-phone/complete | ⚠️ | Single-step `POST /register/vendor` + shared `phone/otp` verify exist (tested today, `4ac12ef`). Multi-step initiate/complete split ❌. Email-OTP verification ❌ (email verify exists via link?— verify). |
| 1.5 | login | ✅ | Shared `POST /api/v1/login` serves vendors (G2 proof tests). |
| 1.6/1.7 | forgot/reset password | ✅ | Shared `password/reset/*` (spec 054). |
| 1.8 | logout | ✅ | Shared `/logout`. Bonus: `account/email`, `account/password`, `account/phone` mutations exist. |

### F2 Profile (10)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 2.1/2.2 | GET/PATCH profile | ✅ | `GET/PUT /vendor/profile`. |
| 2.3–2.6 | logo/cover upload+delete | ❌ | `logo_path`/`cover_path` columns consumed by customer APIs; no vendor upload endpoints (Filament uploads only Ⓕ). |
| 2.7/2.8 | business-details GET/PATCH | ⚠️ | Folded into profile GET/PUT; no separate endpoint. 🟡 |
| 2.9/2.10 | portfolio upload/delete | ❌ | No vendor portfolio media collection exists (same finding as customer 8.5 — customer portfolio currently aggregates service galleries). Needs ADR-0047 collection decision. |

### F3 Compliance & Documents (6)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 3.1 | GET compliance | ✅ | G3 — per-type approval + 30-day expiry alerts. |
| 3.2/3.3 | documents GET/POST | ✅ | + bonus `GET documents/{id}/signed-url` (5-min, ADR-0047). |
| 3.4 | DELETE document | ❌ | Compliance lifecycle (ADR-0021) may intentionally forbid deletes — confirm before building. |
| 3.5 | apply-for-type | ⚠️ | `POST vendor-profiles/{id}/resubmit` exists (re-application); per-type apply ❌. 🟡 |
| 3.6 | compliance audit-log | ⚠️ | `GET /vendor/change-requests` exists (admin change requests); full moderation history ❌. |

### F4 Services (18)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 4.1/4.2 | list + show | ✅ | Show is per-type resource via `match` (G10). |
| 4.3–4.5 | create per type | ✅✅✅ | Per-type FormRequests with `authorize()` checking `approvedTypes` ✓ (verified). 🟡 ruling still needed for mobile UI. |
| 4.6–4.8 | update per type | ✅✅✅ | + material-edit approval flow (spec 035) on top. 🟡 |
| 4.9 | delete | ✅ | Per-type DELETE routes (semantic match; archive action exists too). |
| 4.10/4.11 | publish/unpublish | ⚠️ | `POST services/{id}/resubmit` exists (moderation resubmit); explicit publish/unpublish API ❌ (Filament-only Ⓕ — `PublishServiceAction` exists). |
| 4.12 | clone | Ⓕ | `CloneServiceTest` exists; Filament action only, no route. |
| 4.13–4.15 | images upload/delete/reorder | ✅✅✅ | `services/{service}/media` suite (Media Phase 1, 74 tests). 🟡 ruling on multi-image mobile UX only. |
| 4.16 | stats | ❌ | Views (analytics_events now exists) + bookings counts available; endpoint missing. |
| 4.17 | availability toggle | ❌ | No is_active toggle separate from publish state (derived `is_active` per contract). Possibly N/A-by-design — confirm. |
| 4.18 | field-schemas | ❌ | Customer variant built today; vendor variant (incl. non-filterable fields for the create form) missing. Note: `GET /vendor/categories` exposes `allowed_product_types` already. |

### F5 Excel (4)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 5.1 | template download | ✅ | `catalog/import/template/{type}`. 🟡 |
| 5.2 | upload per type | ✅ | `services/{type}/import` ×3 (strict no-partial, tested). 🟡 |
| 5.3 | import status | ⚠️ | `failed-rows` exists; dedicated status endpoint ❌ (status embedded in import history Ⓕ). |
| 5.4 | per-row errors | ✅ | `catalog/import/{id}/failed-rows`. |

### F6 Bookings (12)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 6.1/6.2 | list + detail | ✅ | `booking-vendors` (+ privacy: phone last-4, street only post-accept — G5). |
| 6.3/6.4 | accept/reject | ✅ | |
| 6.5/6.6 | preview-modification + modify | ✅ | G6 today — token handshake, FR-13. |
| 6.7/6.8 | item detail + transition | ✅ | G7 — per-type state machines via `match`. |
| 6.9 | condition photos | ✅ | G8 — ADR-0047 collection. |
| 6.10 | customer-info | ⚠️ | Masked customer block embedded in 6.2 detail; no separate endpoint (arguably better). |
| 6.11 | timeline | ❌ | `booking_state_transitions` exist; vendor read endpoint missing (admin timeline exists, ADR-0041). |
| 6.12 | report-issue | Ⓕ | `ReportFulfillmentIssueAction` exists + tested; Filament-only, no route. |

### F7 Schedule (3)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 7.1/7.2 | today + upcoming | ✅ | `GET /vendor/schedule` (G7) covers both via range params — verify param shape. |
| 7.3 | calendar | ⚠️ | Same endpoint can serve from/to; dedicated calendar shape ❌. 🟡 |

### F8 Business Hours & Availability (7)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 8.1 | GET business-hours | ❌ | **Only `PUT` exists — no read endpoint** (odd asymmetry). |
| 8.2 | PATCH business-hours | ✅ | As PUT (full week). 🟡 (single-day variant question). |
| 8.3–8.5 | blocked-dates CRUD | 🔒 | **No vendor-holiday table in the locked schema** (same finding blocked customer 8.7 blocked_dates). Schema conversation required. |
| 8.6/8.7 | lead-times GET/PATCH | 🔒 | No per-vendor lead-time columns in locked schema (lead time lives on sale detail rows per service). Schema conversation or N/A. |

### F9 Coverage (5)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 9.1 | GET coverage | ❌ | Only `POST /vendor/coverage-areas` exists (write without read!). |
| 9.2 | add city | ✅ | `POST coverage-areas`. 🟡 (batch vs progressive). |
| 9.3/9.4 | update fee / remove city | ❌ | |
| 9.5 | available-cities | ⚠️ | Public geography endpoints exist; vendor-specific "not yet covered" filter ❌. |

### F10 Pricing & Categories (4)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 10.1 | categories | ✅ | With `allowed_product_types`. |
| 10.2 | field-schemas | ❌ | (= 4.18). |
| 10.3 | own commission rates | ❌ | `commission_rates` seeded; vendor-visible endpoint missing (allowed: own rates only). |
| 10.4 | pricing calculator | ❌ | Commission resolution logic exists (most-specific match); preview endpoint missing. |

### F11 Chat (3)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 11.1 | threads list | ⚠️ | Vendor Filament chat panel exists (spec 037); REST threads list for mobile ❌. Customer has per-booking variant. |
| 11.2 | upload-url | ❌ | Same open item as customer 14.3 — pending spec-056 owner review of attachments. |
| 11.3 | mark-read | ❌ | Firestore-first: read state arguably belongs client-side in Firestore — confirm with 056 design before building. |

### F12 Wallet & Settlements (5)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 12.1/12.2 | wallet + ledger | ✅ | Permission-gated (`settlement.view_wallet.own`). |
| 12.3 | ledger export | ❌ | 🟡 |
| 12.4/12.5 | settlements list/detail | ❌ | `settlement_runs` table exists (admin-side); vendor read missing. |

### F13 Withdrawals & Bank Accounts (8)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 13.1–13.5 | bank accounts CRUD + default | 🔒 | **G13: no `bank_accounts` table in locked schema.** Current design: single implicit account via `vendor_profiles.bank_*` columns (editable through profile/Filament). Multi-account = schema conversation. 🟡 |
| 13.6–13.8 | withdrawals list/create/show | ✅✅✅ | Permission + idempotency middleware ✓; two-step approve→paid (ADR-0032). |

### F14 Reviews (4)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 14.1/14.2 | list + detail | ✅ | G9 today. |
| 14.3 | respond | ✅ | Admin-moderated response flow (spec 010). |
| 14.4 | stats | ⚠️ | Rating aggregates live on services + vendor_profiles (`rating_avg/count`); dedicated stats endpoint ❌. |

### F15 Reports (5)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 15.1 | overview KPIs | ✅ | `dashboard/summary` (G4, 5-min cache, minor units). |
| 15.2/15.3 | bookings/revenue reports | ❌ | Filament widgets exist Ⓕ; REST ❌. 🟡 |
| 15.4 | services-performance | ❌ | |
| 15.5 | export | ❌ | 🟡 |

### F16 Loyalty (5)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 16.1/16.2 | program GET/update | ✅ | GET/POST/PUT `loyalty/program`. 🟡 (rule editor on mobile). |
| 16.3 | customers' balances | ❌ | Privacy note: expose points + masked customer identity only. |
| 16.4 | loyalty ledger | ❌ | |
| 16.5 | manual adjust | ❌ | Needs audit log + reason (append-only ledger entry — supported by schema). |

### F17 Notifications (9)
| # | Endpoint | Status | Notes |
|---|---|---|---|
| 17.1–17.5 | inbox (5) | ❌ | **Now cheap to build**: `read_at` column + customer inbox pattern shipped this morning (F17 customer) — vendor variant is a role-scoped clone. G11 unblocked. |
| 17.6/17.7 | preferences | ✅ | GET + per-row PUT. |
| 17.8/17.9 | devices | ✅ | Shared `/api/v1/devices` (G12 ruling). |

---

## Totals

| Status | Count |
|---|---|
| ✅ EXISTS | **48** |
| ⚠️ PARTIAL | **12** |
| ❌ MISSING | **39** |
| 🔒 SCHEMA-LOCKED (your ruling required) | **10** (8.3–8.7 = 5, 13.1–13.5 = 5) |
| Ⓕ Filament-only (Action exists, route missing — cheap to expose) | 3 of the ❌ (4.12 clone, 6.12 report-issue, 4.10/4.11 publish) |
| **Total** | **116 rows** (prompt said ~100) |

## Privacy audit (Step 1.3)

- ✅ Booking-vendor detail masks customer contact (phone last-4; street address only after accept — built today with tests, G5).
- ✅ Wallet/withdrawals permission-gated per-vendor (`settlement.*.own`).
- ✅ Reviews inbox scoped to own vendor profile.
- ⚠️ **MEDIUM hardening:** 27 of 67 vendor routes have **no route-level `role:vendor`/permission middleware** (only `auth:sanctum`) — Catalog services/media/import/loyalty-program/change-requests groups. Mitigation verified: mutations gate in FormRequest `authorize()` (`approvedTypes` check → 403 for customers/unapproved), reads scope by `user()->vendorProfile`. Recommend adding route-level `role:vendor` for defense-in-depth (exactly the customer-wishlist P0 pattern).
- Not yet verified exhaustively: media endpoints' cross-vendor isolation, loyalty program PUT scoping. Phase 2 negative tests will pin these.

## Approval-gate audit (Step 1.5)

- ✅ Service create/update: per-type `approvedTypes` in `authorize()` (verified on rental).
- ✅ Withdrawals: permission-gated.
- ✅ Documents upload: allowed pre-approval (correct — onboarding).
- ⚠️ Not verified: booking accept/modify approval gate (G5/G6 work scoped by ownership; approval-status gate unconfirmed), suspended-vendor read-only mode (**no suspended-state middleware found anywhere** — flag HIGH: a suspended vendor likely retains full API access).

## Per-product-type audit (Step 1.4)

- ✅ Exemplary: per-type create/update/delete/import routes, per-type FormRequests/Actions/Resources, `match` in service show and item transitions. No violations found in the API layer.

## Mobile parity — 🟡 recommendations (24 items)

| # | Endpoint | Rec | Reasoning |
|---|---|---|---|
| 1.4 | register/complete | **(C)** | Split into resumable steps server-side (status-driven onboarding checklist already exists — spec 034); mobile posts step-by-step. |
| 2.8 | business-details PATCH | **(B)** | Legal identity data; low frequency; admin re-approval implications — web. |
| 3.3 | document upload | **(A)** | Mobile camera→PDF is standard now; single multipart works; signed-url infra exists. |
| 3.5 | apply-for-type | **(B)** | Form + docs + compliance flow; low frequency — web. |
| 4.3–4.8 | service create/update ×6 | **(C)** | Keep APIs as-is (they're step-agnostic JSON); mobile does a multi-screen wizard with draft persistence — needs NO new endpoints (draft status exists). Effectively (A) at the API layer. |
| 4.13 | images upload | **(A)** | Media suite already supports per-file upload + reorder — mobile uploads sequentially. |
| 5.1 | excel template | **(B)** | File round-trip through external apps on mobile is hostile; bulk import is a desk task. |
| 5.2 | excel upload | **(B)** | Same. Status/errors (5.3/5.4) stay mobile-visible (A). |
| 7.3 | calendar | **(C)** | Same data, mobile gets `?range=week` compact shape (already parameterized — cheap). |
| 8.2 | business-hours | **(C)** | Add `PATCH /vendor/business-hours/days/{day}` for single-day mobile edits; PUT full-grid stays for web. |
| 9.2 | coverage add | **(A)** | Existing endpoint already takes one city per call — progressive UX works as-is. |
| 12.3 | ledger export | **(B)** | Download flow; ledger itself paginated for mobile (A). |
| 13.2/13.3 | bank account add/update | **(A) + re-auth** | If/when G13 table approved; until then bank edits go through profile (web). Today: effectively (B) by absence. |
| 15.2/15.3 | bookings/revenue reports | **(C)** | One endpoint, two shapes: `?granularity=summary` for mobile cards, full series for web charts. |
| 15.5 | report export | **(B)** | Download flow — web. |
| 16.2 | loyalty rules editor | **(B)** | Read-only on mobile (16.1 stays); rule edits on web. |

## Recommended Phase 3 scope (pending your rulings)

- **Quick wins (Filament Action → route):** publish/unpublish, clone, report-issue, business-hours GET, coverage GET/PATCH/DELETE, import status.
- **Vendor notifications inbox (5)** — clone of this morning's customer F17.
- **New build:** service stats, field-schemas (vendor), commission-rates + calculator, settlements read, booking timeline, loyalty customers/ledger/adjust, reports 15.2–15.4, profile logo/cover upload.
- **Blocked on your ruling:** 8.3–8.7 + 13.1–13.5 (🔒 schema), 2.9/2.10 portfolio (ADR-0047 collection), 11.x chat (spec-056 owner), 3.4 doc delete (ADR-0021), multi-step registration (1.1–1.4 redesign).
- **Hardening:** route-level `role:vendor` on the 27 unguarded routes; suspended-vendor gate design.
