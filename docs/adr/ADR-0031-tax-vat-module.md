# ADR-0031 — Tax Module (VAT Resolution and Per-Booking Computation)

- **Status:** Proposed — implemented ahead of plan, ratification required
- **Date:** 2026-05-16
- **Decision-makers:** Ibrahim (pending)
- **Tags:** tax, vat, money, scope-expansion, phase-1-extension
- **Related:**
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §5.2 — "Advanced tax invoicing beyond foundational readiness" listed as out-of-scope
  - [`CLAUDE.md`](../../CLAUDE.md) "Phase 1 Scope" — same exclusion
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) — booking_items / bookings tables now extended
  - [`docs/adr/ADR-0028-financial-ledger-hardening.md`](ADR-0028-financial-ledger-hardening.md) — VAT flows interact with the ledger
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) — new Phase 4.10 entry pending

---

## 1. Context / السياق

**EN:** A new `app/Modules/Tax/` module was scaffolded and partially built (tax_rates table, VAT columns on `bookings` and `booking_items`, resolver contract, Filament resources, tax report page) without a prior ADR or phasing-plan entry. CLAUDE.md §"Phase 1 Scope" explicitly lists "Advanced tax invoicing beyond foundational readiness" as out-of-scope. The question this ADR resolves is whether what was built crosses that line or sits on the correct side of it.

**The answer is: it crosses.** Foundational readiness = storing a vendor's tax ID and VAT registration number for future invoicing. What was built = a per-booking VAT computation engine with effective-date rules, product-type targeting, customer-vs-vendor incidence, a resolver contract injected into Booking, an admin CRUD UI, and a tax report page. That is operational tax computation, not readiness.

This ADR documents the build, names the constitution conflict, and proposes a phasing slot (4.10) for ratification.

**AR:** تم بناء وحدة الضرائب (`app/Modules/Tax/`) جزئياً بدون ADR مسبق. CLAUDE.md يضع "الفوترة الضريبية المتقدمة" خارج النطاق صراحةً، والمبني فعلياً يتجاوز "الاستعداد الأساسي" — فهو محرك حساب VAT لكل حجز مع قواعد تاريخ السريان واستهداف نوع المنتج. هذا الـADR يوثق ما تم بناؤه ويقترح إدخاله في Phase 4.10.

**Implemented in:** working tree of branch `030-vendor-booking-decision-page` (uncommitted at time of writing).

---

## 2. What was actually built (as-of 2026-05-16)

### Tables / migrations (4)
- `tax_rates` — public_id ULID, translatable `name`/`description`, `rate_bps` (basis points), `applies_to` ENUM(`customer`,`vendor`,`all`), `product_types` JSON nullable (most-specific-wins targeting), `is_tax_inclusive`, `is_active`, `effective_from` + `effective_to`. Index `(is_active, effective_from, effective_to)`.
- `booking_items.vat_rate_bps` (BIGINT UNSIGNED, default 0) + `booking_items.vat_amount_minor` (BIGINT UNSIGNED, default 0).
- `bookings.total_vat_minor` (BIGINT UNSIGNED, default 0).
- `booking_items.vat_currency` (CHAR(3)) — separate later migration to pair currency with the amount.

### Domain
- `TaxAppliesTo` enum (Customer / Vendor / All).
- `TaxRate` model (HasTranslations).
- `TaxRateRepository` contract (`Domain/Contracts/`).
- `TaxRatePolicy`.

### Application
- `CreateTaxRateAction`, `UpdateTaxRateAction`
- `ResolveTaxRateForBookingAction` — most-specific match: rate with matching `product_types` array beats null-array (applies-to-all) entry.

### Infrastructure
- `EloquentTaxRateRepository`
- `BookingTaxRateResolver implements App\Modules\Booking\Domain\Contracts\TaxRateResolver` — **new contract added to Booking module just for this purpose**.

### Filament
- `TaxRateResource` + Create/Edit/List pages
- `TaxReportPage` (uses `resources/views/vendor/tax/...` Blade view)

### Translations
- `app/Modules/Tax/Resources/lang/{en,ar}/tax.php` present.

### Tests
- **None.** No `tests/Feature/Modules/Tax/` or `tests/Unit/Modules/Tax/` directories exist. Constitution violation.

---

## 3. Why this conflicts with Phase 1 scope

CLAUDE.md §"Phase 1 Scope (DO NOT BUILD Phase 2)" lists:

> - Advanced tax invoicing beyond foundational readiness

The reasonable reading of "foundational readiness" (from prior conversations referenced in PRD §11):

| Foundational readiness (in scope) | Built here (out of scope) |
|---|---|
| Store `vendor_profiles.tax_id` and VAT registration number | A `tax_rates` table with effective-date ranges |
| Surface tax ID on invoices when present | A resolver service that picks the rate per booking |
| No runtime tax computation | Per-item `vat_rate_bps` + `vat_amount_minor` + booking aggregate `total_vat_minor` |
| No admin tax CRUD | Full Filament Resource + tax report page |
| No coupling between Tax and Booking | Booking module gained a new `TaxRateResolver` contract specifically to wire Tax in |

This is operational VAT computation. It cannot ship without certainty that:
- The rates entered are correct under Egyptian law (currently nothing prevents an admin from entering an invalid 0% or 50% rate).
- The rounding rules match the Egyptian Tax Authority's expectations (rate × line is computed via integer minor units — direction-of-rounding is unspecified).
- Tax-inclusive vs tax-exclusive flag flows correctly through pricing tiers.
- Refunds reverse the VAT proportionally (no logic for this in `ProcessRefundAction` yet).

---

## 4. Proposed phasing slot

If kept, the work belongs at **Phase 4.10 — Tax / VAT Computation**, sitting immediately after Phase 4.9 (Financial Ledger Hardening). Rationale:

- VAT is a money-mutating concern → must use `Brick\Money` and integer minor units (already does).
- VAT amounts must post to the ledger as their own line items (currently they do not — the booking-item VAT is just a sidecar column).
- It depends on Phase 4.9 idempotency + ledger groups for refund VAT reversal.
- 4 working days estimated to bring to ship-ready (see §6).

A companion entry will be added to `docs/specs/09_Phasing_Plan.md`.

---

## 5. Decisions to ratify

### 5.1 Foundational vs operational scope split

**Decision needed:** Confirm that operational VAT computation is now in Phase 1 scope (not Phase 2).

**Implication of Yes:** PRD §5.2 must be amended — "Advanced tax invoicing beyond foundational readiness" becomes "Tax invoicing covering multi-jurisdiction / withholding tax / tax authority e-filing" (a much narrower exclusion).

**Implication of No:** Revert the Tax module and the three migrations that touched `bookings` / `booking_items`. Keep only the `vendor_profiles.tax_id` column if it exists elsewhere.

### 5.2 Rate-source authority

**Decision needed:** Where do canonical Egyptian VAT rates come from?

**Options:**
- **A.** Admin enters and maintains rates manually via Filament. Risk: human error, no audit of "is this the legally correct rate."
- **B.** Seeder ships canonical rates (14% standard VAT, etc.) with `is_active=true, effective_from='2026-01-01'`. Admin can only deactivate or add — never edit historical rates. Lower risk.
- **C.** Pull from a tax authority API. Out of scope for Phase 1 — defer.

**Recommendation:** **B** — seed canonical rates, restrict Filament Edit on historical rows (`->disabled()` after the row has been used on any settled booking).

### 5.3 Refund VAT reversal

**Decision needed:** When a refund is processed, does the VAT proportional-reverse?

Currently: no. `ProcessRefundAction` (newly refactored under Phase 4.9) does not touch `vat_amount_minor`. This breaks tax accounting — a partial refund leaves overstated VAT collected on the platform's books.

**Required:** `ProcessRefundAction` must compute `refund_vat_minor = refunded_amount_minor × (vat_rate_bps / 10000)` and post a debit to the `platform_vat_payable` suspense account in the same ledger transaction group. Adds dependency on ADR-0028 §2.3 chart of accounts (new suspense account needed).

### 5.4 Rounding

**Decision needed:** Pick a rounding mode. Egyptian tax authority defaults to **HALF_UP** at the line-item level, summed to booking total. PHP's `intdiv($amount * $bps, 10000)` truncates — wrong.

**Required:** Use `Brick\Money\Money::multipliedBy($rate, RoundingMode::HALF_UP)` consistently. Cannot defer.

### 5.5 Tax inclusive flag

**Decision needed:** Does `is_tax_inclusive=true` mean "the rate row's `rate_bps` is the tax already baked into the price" (i.e., back out tax from the displayed price) or "this rate is computed inclusive of itself"? Currently the flag has no consumer — it's stored but unread.

**Required:** Pick a definition, document it on the column, write a unit test for each direction.

### 5.6 Money column convention

`booking_items.vat_amount_minor` is **paired with `vat_currency` in a separate migration** (006), which is unusual — convention is to pair them in the same migration. Confirm intent or merge.

### 5.7 Cross-module coupling

The Booking module gained `app/Modules/Booking/Domain/Contracts/TaxRateResolver.php` — a contract that exists only for Tax to implement. This is the right pattern (Booking depends on its own contract; Tax provides the binding in `TaxServiceProvider`). Verify the binding exists in `TaxServiceProvider::register()`. If absent, Booking will crash at runtime.

---

## 6. Minimum acceptance criteria (before any merge)

1. **§5.1 ratified** — Yes/No on scope expansion.
2. **Canonical Egyptian VAT seeder** shipped (`database/seeders/CanonicalTaxRatesSeeder.php` or equivalent).
3. **Refund VAT reversal** implemented in `ProcessRefundAction` with a Pest test asserting partial-refund VAT proportionality.
4. **Rounding mode** chosen, applied via `Brick\Money`, unit test for HALF_UP at the line level.
5. **`is_tax_inclusive` semantics** documented + tested both directions.
6. **`TaxRateResolver` binding** registered in `TaxServiceProvider` — verified by an architecture test that `app(TaxRateResolver::class)` resolves.
7. **Test coverage** under `tests/Feature/Modules/Tax/` and `tests/Feature/Modules/Booking/Tax/`:
   - Happy path: rate resolves and VAT computes on a Rental, a Sale, a Digital booking.
   - Auth, authz (only admin can CRUD rates), validation.
   - Effective-date edges: rate inactive before/after window.
   - Most-specific match wins (product-type targeting).
   - Refund proportional VAT reversal.
8. **`php artisan shield:generate --all`** run after `TaxRateResource` — confirm permissions in `IdempotencyRolesSeeder`.
9. **Schema cheat sheet** updated — table count 60 → 61 (tax_rates), new columns on bookings + booking_items documented.
10. **PRD §5.2** amended to narrow the tax-scope exclusion.
11. **ADR-0028** addended with the `platform_vat_payable` suspense account (if §5.3 decision is Yes).

---

## 7. Consequences

**If accepted:**
- VAT becomes a first-class money concern coupled to the ledger.
- `wallet_ledger` chart of accounts grows by one suspense account.
- Booking total semantics shift: `total_minor` now excludes VAT, `total_vat_minor` adds it — frontend and API consumers must update displays.
- Egyptian Tax Authority compliance becomes a thing the codebase claims; auditability burden goes up.

**If rejected:**
- Revert: `git checkout -- app/Modules/Tax bootstrap/providers.php` plus the three Tax migrations that altered `bookings`/`booking_items`.
- `vendor_profiles.tax_id` column (if added elsewhere) stays — that's foundational and in scope.
- ~1 day of implementation work lost.

**Default:** path (2) — defer to a properly-scoped Phase 2 effort with legal review of rates.

---

## 8. Open questions

- Does VAT incidence (`applies_to`) flow through commission calculation? If a vendor charges customer-VAT, does the commission base include or exclude VAT?
- Does the `tax_rates.is_active=false` flag prevent the resolver from picking the rate, or does the effective-date window do that? Currently both checks happen — pick one source of truth.
- For digital products, is Egyptian VAT applicable when the customer is outside Egypt? Currently no jurisdiction logic exists.
