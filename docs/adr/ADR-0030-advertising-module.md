# ADR-0030 — Advertising Module (Vendor Paid Promotion)

- **Status:** Proposed — implemented ahead of plan, ratification required
- **Date:** 2026-05-16
- **Decision-makers:** Ibrahim (pending)
- **Tags:** advertising, monetization, scope-expansion, phase-2
- **Related:**
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §5.2 — Phase 2 out-of-scope list (currently forbids vendor page slider / subscription tiers)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) — new Phase 5.5 entry pending
  - [`docs/adr/ADR-0013-subscription-tiers-module.md`](ADR-0013-subscription-tiers-module.md) — companion monetization module (Phase 1.7)
  - [`CLAUDE.md`](../../CLAUDE.md) "Phase 1 Scope (DO NOT BUILD Phase 2)" — this ADR exists because the implementation crossed that line

---

## 1. Context / السياق

**EN:** A new `app/Modules/Advertising/` module was scaffolded and partially built (migrations, models, enums, actions, Filament resources, widgets, analytics page) without a prior ADR or phasing-plan entry. The module implements paid vendor placements (homepage banner, category banner, sponsored search, featured listing), subscription lifecycle (`pending_payment → active → expired → cancelled`), and impression/click tracking. This crosses the line drawn in CLAUDE.md §"Phase 1 Scope" which explicitly lists "Vendor page slider" and "Vendor subscription tiers" as **out of scope for Phase 1**. This ADR exists to (a) honestly document what was built, (b) propose a phasing slot that legitimises it, and (c) define the minimal acceptance criteria before it can ship.

**AR:** تم بناء وحدة الإعلانات (`app/Modules/Advertising/`) جزئياً بدون ADR مسبق أو إدخال في خطة المراحل. الوحدة تنفذ إعلانات مدفوعة للبائعين (لافتة الصفحة الرئيسية، لافتة الفئة، البحث المُموّل، القائمة المُميّزة) ودورة حياة الاشتراك وتتبع المشاهدات والنقرات. هذا يتجاوز الخط الموضوع في CLAUDE.md §"Phase 1 Scope" الذي يضع "Vendor page slider" و "Vendor subscription tiers" خارج النطاق صراحةً. هذا الـADR يوثق ما تم بناؤه ويقترح فتحة مراحل تشرعنه ويحدد معايير القبول الدنيا قبل الإطلاق.

**Implemented in:** working tree of branch `030-vendor-booking-decision-page` (uncommitted at time of writing).

---

## 2. What was actually built (as-of 2026-05-16)

### Tables (2)
- `advertisement_packages` — public_id ULID, translatable `name`/`description`, `placement_type` ENUM(`homepage_banner`,`category_banner`,`search_sponsored`,`featured_listing`), `duration_days`, `price_minor` + `price_currency`, `impression_limit`, `is_active`. Index `(placement_type, is_active)`.
- `vendor_ad_subscriptions` — public_id ULID, FK to `vendor_profiles`, FK to `advertisement_packages`, `status` ENUM(`pending_payment`,`active`,`expired`,`cancelled`), `starts_at`/`ends_at`, `total_minor` + `total_currency`, `impression_count`, `click_count`. Indexes `(vendor_profile_id, status)` and `(status, ends_at)`.

### Domain
- `PlacementType` enum (4 cases) with `label()` and `color()` helpers.
- `AdSubscriptionStatus` enum.
- `AdvertisementPackage`, `VendorAdSubscription` models (HasTranslations on the package).
- Policies: `AdvertisementPackagePolicy`, `VendorAdSubscriptionPolicy`.

### Application
- `CreateAdvertisementPackageAction`, `UpdateAdvertisementPackageAction`
- `ActivateAdSubscriptionAction`, `CancelAdSubscriptionAction`
- `TrackAdImpressionAction`

### Filament
- `AdvertisementPackageResource` + Create/Edit/List pages
- `VendorAdSubscriptionResource` + List page
- `AdvertisingStatsWidget`, `AdRevenueChartWidget`
- `AdvertisingAnalyticsPage` custom page (uses `resources/views/filament/pages/advertising-analytics.blade.php`)

### Providers
- `AdvertisingServiceProvider` registered in `bootstrap/providers.php`.

### Translations
- `app/Modules/Advertising/Resources/lang/{en,ar}/advertising.php` present.

### Tests
- **None.** No `tests/Feature/Modules/Advertising/` or `tests/Unit/Modules/Advertising/` directories exist. This is a hard blocker per CLAUDE.md testing conventions.

---

## 3. Why this conflicts with Phase 1 scope

CLAUDE.md §"Phase 1 Scope (DO NOT BUILD Phase 2)" explicitly enumerates as out-of-scope:

> - Vendor subscription tiers (silver/gold/bronze)
> - Vendor page slider
> - Vendor QR / barcode catalog

The Advertising module is a **paid promotional placement system with vendor subscriptions** — it is the same product line that the cut-list bans, just renamed. Any approval to keep it must either:

1. **Amend the PRD §5.2 cut-list** to remove paid placements from Phase 2, **or**
2. **Defer the module to Phase 2** and revert the working-tree files until then.

This ADR proposes path (1) **only if** Ibrahim has a commercial reason to ship paid placements alongside Phase 1 (e.g., investor demo, pilot vendor monetization, founding-vendor incentive). Absent that reason, path (2) is the default.

---

## 4. Proposed phasing slot

If kept, the work belongs at **Phase 5.5 — Vendor Promotion & Paid Placements**, sitting between Phase 5.3 (Marketing Campaigns) and Phase 6.0 (Reporting). Rationale:

- It depends on Phase 4 (Payments — paid subscriptions must charge via Paymob).
- It depends on Phase 5.0 (Communications — vendors must be notified of expiry).
- It feeds Phase 6 (Reporting — ad revenue stats).
- 5 working days estimated to bring it to ship-ready (see §6).

A companion entry will be added to `docs/specs/09_Phasing_Plan.md`.

---

## 5. Decisions to ratify (each requires a Yes/No from Ibrahim)

### 5.1 Payment integration

**Decision needed:** How does a vendor pay for a package?

**Options:**
- **A.** Reuse the existing Subscriptions module's `VendorSubscription` machinery (treat ad packages as a subscription product type). Requires adding `ad_package` to the subscription product taxonomy.
- **B.** Stand-alone Paymob checkout that creates a one-off `Payment` linked to `vendor_ad_subscriptions.id` via morphable `payable`. Simpler but duplicates subscription expiry logic.
- **C.** Defer payment — admin manually activates after off-system bank transfer. Lets the module ship without Paymob coupling but requires admin operational load.

**Recommendation:** **A** — the Subscriptions module already handles renewal, expiry, and Paymob webhooks. Adding `ad_package` as a subscription kind is one enum + one factory branch.

### 5.2 Placement enforcement

**Decision needed:** Where in customer-facing code do active subscriptions get *consumed*?

**Currently:** `TrackAdImpressionAction` increments the counter but **no code anywhere reads `vendor_ad_subscriptions` to decide what to render**. The module collects data but does not influence the storefront — a hollow shell.

**Required before ship:**
- `app/Modules/Discovery/Application/Services/SponsoredResultsService` (or equivalent) that injects active `search_sponsored` subscriptions into Meilisearch results.
- `app/Modules/Catalog/Application/Services/FeaturedListingService` for `featured_listing` placements on category pages.
- Homepage/category banner rendering hooks on the customer Next.js side (not in this backend repo, but documented in the API contract).

Until those exist, the module ships zero customer-visible value.

### 5.3 Impression rate-limiting and fraud

**Decision needed:** How are impressions counted honestly?

`TrackAdImpressionAction` is currently trivial — it just increments. Without rate-limiting or session deduplication, a malicious vendor (or a competitor) can drain another vendor's `impression_limit` by replaying requests. Minimum bar:

- Server-side `vendor_ad_subscription_id × session_id` dedup window (15 min).
- Bot user-agent filter.
- Rate-limit per IP per subscription per minute.

### 5.4 Money handling

**Decision needed:** Confirm that `price_minor` / `total_minor` use `Brick\Money` casts and never float.

Models currently lack a `MoneyCast` cast for `price_minor` (compare with `AdvertisementPackage` model §2). This is a constitution violation (CLAUDE.md §6). Must be fixed.

### 5.5 Soft deletes

**Decision needed:** Neither table has `softDeletes()`. Confirm this is correct (subscriptions are append-once mutable state, packages are admin-managed — both fine to hard-delete from Filament). Already consistent with constitution.

---

## 6. Minimum acceptance criteria (before any merge)

1. **ADR ratified or module reverted.** Yes/No on §3 path (1) vs (2) from Ibrahim. This file lives or dies here.
2. **`AdvertisementPackage.price_minor` cast via `MoneyCast`** — constitution §6.
3. **At least one Pest test per Action** under `tests/Feature/Modules/Advertising/`:
   - happy path, auth (401), authz (vendor cannot create package), validation, locale (EN+AR).
4. **`php artisan shield:generate --all` run** after the two new Resources — confirm permissions appear in `database/seeders/IdentityRolesSeeder.php`.
5. **Payment integration decision (§5.1) implemented** — either Subscriptions integration or admin-manual activation flow, picked and shipped.
6. **Placement enforcement contract (§5.2) drafted** — at minimum a `Domain/Contracts/SponsoredResultsProvider` interface that Discovery can depend on, even if the implementation is a stub.
7. **Impression dedup logic (§5.3) implemented** — even a naive session-based one.
8. **`docs/specs/09_Phasing_Plan.md` Phase 5.5 entry merged.**
9. **Schema cheat sheet updated** (`.claude/rules/schema-cheatsheet.md`) — add 2 new tables to the inventory, count goes from 60 → 62.
10. **PRD §5.2 cut-list amended** if path (1) was chosen — remove "Vendor page slider" / "Vendor subscription tiers" from the out-of-scope enumeration, or scope them tighter.

---

## 7. Consequences

**If accepted (path 1):**
- Table count moves from 60 → 62; schema cheat sheet must be re-locked.
- Phase 1 critical path stretches by ~5 days.
- Sets precedent that monetization features can land outside the original Phase 1 envelope — future scope creep risk.
- Adds a customer-visible promotional surface that the customer/vendor frontends must implement (Next.js + Flutter work).

**If rejected (path 2):**
- Revert: `git checkout -- app/Modules/Advertising bootstrap/providers.php` (and the relevant lines in CLAUDE.md / phasing plan if added).
- 0 production impact (nothing is committed).
- ~1 day of implementation work lost.

**Default:** path (2) — defer. The module is not test-covered, not payment-wired, and not customer-facing. Shipping it now adds attack surface (impression endpoint) without revenue.

---

## 8. Open questions

- Is there a business commitment (founding vendor agreement, investor demo, marketing campaign) that requires paid placements by GA?
- If Subscriptions module is reused for billing, does the existing `subscription_plans` taxonomy need a `kind` column to distinguish tier-subs from ad-subs?
- Should ad revenue post to `wallet_ledger` via the new Phase 4.9 double-entry path (suspense account: `platform_advertising_revenue`)? If yes, ADR-0028 needs an addendum.
