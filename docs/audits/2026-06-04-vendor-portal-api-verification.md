# Vendor Portal API — Phase 4 Verification

> **Superseded in part by the 2026-06-05 "do all" addendum at the bottom** —
> the remainder + schema rulings were subsequently approved and built.

**Date:** 2026-06-05 · **Branch:** 057-vendor-mobile-gaps
**Audit baseline:** `docs/audits/2026-06-04-vendor-portal-api-audit.md` (116 rows)
**Rulings applied:** Ibrahim approved the audit's 🟡 recommendations verbatim ("go", 2026-06-05); 🔒 schema items remain excluded pending explicit schema rulings.

## Outcome

| Metric | Before | After |
|---|---|---|
| `/api/v1/vendor/*` routes | 67 | **82** |
| Routes without role/permission guard | **27** | **0** |
| Inventory ✅ | 48 | **65** |
| Inventory ⚠️ | 12 | 7 |
| Inventory ❌ (buildable) | 39 | 22 |
| 🔒 awaiting schema ruling | 10 | 10 |

## Commits (this phase)

| Commit | Content |
|---|---|
| `ccb0494` | **P0 security fix** — `role:vendor` on Catalog (both groups incl. template download) + Loyalty program routes. Before: a customer token got 200 on `/vendor/categories`, `/vendor/occasions`, `/vendor/loyalty/program` and reached loyalty PUT validation. Contract change: roleless users now 403 (was 404); `VendorServicesListTest` updated with a vendor-role-no-profile 404 case kept. Pinned by `VendorRoleGuardTest` (17 cases) + `VendorCrossIsolationTest` (6 cases). |
| `7144e08` | **Quick wins (8)** — business-hours GET (8.1; DayOfWeek enum-safe), coverage-areas GET/PATCH/DELETE/available-cities (9.1/9.3/9.4/9.5), services submit-review + clone (4.10/4.12 — existing Filament Actions exposed per spec 12 §12), import status (5.3), booking-item report-issue (6.12 — existing Action, 409 contract aligned with the transition endpoint). |
| `b492142` | **Feature endpoints (9)** — vendor notifications inbox 17.1–17.5 (G11, unblocked by the customer-F17 `read_at` column), commission-rates + calculator (10.3/10.4 via canonical `CommissionRateResolver`), vendor field-schemas (10.2/4.18 — all form fields), service stats (4.16 — views/booked/conversion). |

## Tests

**37 new tests, all green (74 assertions)** across `VendorRoleGuardTest`, `VendorCrossIsolationTest`, `VendorPortalQuickWinsTest`, `VendorPortalFeatureEndpointsTest`. Coverage per endpoint: happy path, 401, customer-role 403, cross-vendor isolation (404/403), validation, money-integer assertions. Regression: previously-passing vendor catalog/media/loyalty suites re-run — the only failures are the pre-existing classes (seeder count pollution, WIP `getJson()` rot, pre-existing media row assertion), each verified pre-existing by stash-bisect.

## Mobile parity rulings applied (per audit recommendations, approved)

- **(B) web-only:** 2.8 business-details, 3.5 apply-for-type, 5.1/5.2 Excel template+upload, 12.3 ledger export, 15.5 report export, 16.2 loyalty rule edits → mobile shows "Manage on web portal". Status/read counterparts stay mobile-visible.
- **(A) as-is:** 3.3 document upload, 4.13 images (sequential per-file), 9.2 coverage add (already one-city-per-call), 13.2/13.3 bank accounts *(contingent on G13 table approval; effectively web-only today via profile)*.
- **(C) variants pending:** 1.4 resumable registration steps, 7.3 calendar `?range=` shape verification, 8.2 single-day `PATCH business-hours/days/{day}`, 15.2/15.3 `?granularity=summary` — **NOT built this phase** (see remainder).

## Honest remainder (22 buildable ❌ + 7 ⚠️ not closed this phase)

Settlements read (12.4/12.5), reports 15.2–15.4 + booking timeline (6.11), loyalty customers/ledger/adjust (16.3–16.5), profile logo/cover upload (2.3–2.6), portfolio (2.9/2.10 — needs ADR-0047 collection ruling), document DELETE (3.4 — needs ADR-0021 confirmation), chat 11.1–11.3 (spec-056 owner), multi-step registration (1.1–1.4 redesign), availability toggle (4.17 — possibly N/A: `is_active` derives from publish state), the (C) mobile variants above, compliance audit-log (3.6), reviews stats (14.4).

## Schema rulings still required from Ibrahim (unchanged)

1. `bank_accounts` table (G13) — multi-account support; today the single implicit account lives on `vendor_profiles.bank_*`.
2. Vendor blocked-dates table (8.3–8.5) — also feeds customer-facing `blocked_dates` (currently `[]`).
3. Per-vendor lead-time columns (8.6/8.7) — or rule N/A (lead time lives per sale-service detail).
4. DND hours (G14).
5. Suspended-vendor gate — no suspension middleware exists anywhere; needs a state→capability matrix decision before enforcement.

## Verification checklist status

- [x] Route inventory regenerated (82 routes; all new ones registered)
- [x] 0 unguarded vendor routes
- [x] Cross-vendor isolation tested (services, media, coverage, imports, stats, inbox)
- [x] Per-type discipline maintained (no new violations; existing per-type pattern untouched)
- [x] Money: integer minor units in calculator/stats
- [x] No business logic in controllers beyond committed read-controller idiom
- [ ] Scribe docs regeneration — still pending (now 82+115 routes vs the deployed 63-endpoint build)
- [ ] ≥90% full-suite pass rate — blocked by the same pre-existing branch-WIP failure mass documented in the customer-API final report §8a

---

# Addendum — "do all" phase (2026-06-05)

Ibrahim approved the full remainder INCLUDING the schema rulings ("do all").
Lead-times (8.6/8.7) ruled **N/A** per the audit's own recommendation — lead
time lives per sale-service detail row, no vendor-level columns added.

## Delivered (~32 endpoints + cross-cutting gate, commits `6340b29`…`b24c530`)

| Batch | Commit | Content |
|---|---|---|
| B1 reads | `6340b29` | wallet/settlements (+detail — own commissions view; settlement_runs are platform-wide), booking-vendor timeline (own items only), reviews/stats, compliance/audit-log (narrow projection — admin-internal changes payloads never exposed, pinned) |
| B2 | `a2a148e` | loyalty customers (masked first-name+initial) / transactions / manual adjust (existing Action, bilingual reason, members-only) + reports bookings/revenue/services-performance (`?granularity=summary` per ruling C) |
| B3 | `a665f5c` | profile logo/cover upload+delete (path-based) + portfolio GET/POST/DELETE — **ADR-0047 amendment: VendorProfile gains a `portfolio` s3-public media collection** |
| B4 schema | `c724a96` | **`vendor_blocked_dates`** (8.3–8.5; wired into customer availability — `blocked_dates` no longer `[]`, check denies blocked dates) + **`vendor_bank_accounts`** (13.1–13.5/G13; IBAN masked everywhere, first-is-default, default-undeletable, single-default invariant). Legacy `vendor_profiles.bank_*` stays authoritative for withdrawals until payout selection migrates (follow-up). |
| B5 | `9cecfbf` + `b24c530` | day-PATCH business-hours (8.2 C), schedule from/to range (7.3 C, max 62d), onboarding-status (1.4 C-lite via spec-034 checklist), document DELETE (rejected-only — the pre-existing `document_not_deletable` lang contract WAS the ADR-0021 answer), vendor chat threads mirror (11.1; 11.2/11.3 N/A per spec 056), **`vendor.not_suspended` middleware on every vendor group**: suspended vendors read-only, mutations 403 bilingual (Communication inbox deliberately un-gated — mark-read is part of reading) |

## Final state

| Metric | Morning | Post-G12 | Final |
|---|---|---|---|
| Vendor routes | ~51 | 67 | **110** |
| Unguarded | — | 27 | **0** |
| Suspension-gated | 0 | 0 | **95+** (all groups except Communication inbox, deliberate) |

**Tests:** 31 new this phase (68 total across the vendor effort). Final regression batch: **124/125** across 17 suites incl. the 32-test customer guardrail — the single failure (`VendorBookingDetailTest` commission-breakdown auth) **passes in isolation**; order-dependent flake in untouched code (Spatie permission-cache class), pre-existing.

## Still open (small)

1. Withdrawals→`vendor_bank_accounts` payout-selection migration (legacy profile columns still authoritative).
2. Multi-step registration endpoints 1.1–1.3 as specced (onboarding-status covers resume; OTP-login remains ruled out per ruling #4).
3. Excel 5.1/5.2 ruled web-only (B); exports 12.3/15.5 ruled web-only (B); business-details 2.8 + apply-for-type 3.5 web-only (B); loyalty rule edit 16.2 mobile read-only (B).
4. 4.17 availability toggle ruled N/A (derives from publish state).
5. Scribe regeneration for the deployed docs (now ~230 routes vs 63 documented).
