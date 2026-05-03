# ADR-0012 — Loyalty Module

- **Status:** Accepted
- **Date:** 2026-05-03
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-5-loyalty
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/adr/0009-settlement-module.md`](0009-settlement-module.md) — net-paid amount source for earn calculations
  - [`docs/adr/0005-payments-module.md`](0005-payments-module.md) — `RefundFinalized` event source for reversals
  - [`docs/adr/0010-communication-module.md`](0010-communication-module.md) — notification template registry for `loyalty.points_earned` / `loyalty.points_redeemed` / `loyalty.points_restored`
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §"Software Description — Loyalty per-vendor (locked)"
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §3 (money), §4 (append-only tables), §10 (events)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §"Loyalty (4) — per vendor"
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) — Phase 5.2, Week 6
  - [`specs/011-loyalty-per-vendor/spec.md`](../../specs/011-loyalty-per-vendor/spec.md) — feature spec
  - [`specs/011-loyalty-per-vendor/plan.md`](../../specs/011-loyalty-per-vendor/plan.md) — implementation plan

---

## 1. السياق / Context

**AR:** فيه قرار مقفول في الـ PRD: كل vendor عنده loyalty program بتاعه، مفيش loyalty على مستوى المنصة. العميل لازم يشوف رصيد نقاط منفصل لكل vendor، ويكسب نقاط لما booking_item يكتمل، ويصرفها على booking تاني مع نفس الـ vendor. مفيش module حالي يقدر يحمل المسؤولية دي بدون ما يخلط حدوده — فا بنفصلها في module مستقل.

**EN:** PRD locks the rule that loyalty is **per-vendor**, never platform-global. A customer must see a separate point balance for every vendor they have transacted with, earn on `booking_item` completion, and redeem on a later booking with the **same** vendor. No existing module can carry this without violating its boundary, so we introduce a dedicated `Loyalty` module. Drives Phase 5.2 deliverables: vendor configures program, customer earns on completion, customer redeems on next booking with that vendor.

**Phase:** 5.2
**Built in:** Week 6, Days 1–2

---

## 2. المسؤوليات / Responsibilities

### الـ module ده مسؤول عن / This module owns:

- Per-vendor loyalty program lifecycle: configure, pause, archive (one program per vendor).
- Versioned earn + redemption rules for each program (forward-only — old rule rows are deactivated, never recomputed).
- Per-vendor point balance for each customer, computed from an append-only `loyalty_ledger`.
- Earn pipeline triggered by `BookingCompleted` (per `booking_item`) — credits points based on the **net paid amount** (refunds-aware) sourced from Settlement.
- Redemption lifecycle (`pending → applied | voided | reversed`) including holding points while pending, debiting on apply, releasing on void, and crediting back on reversal.
- Vendor-facing Filament Resource for self-service program management; admin read-only oversight Page.

### الـ module ده مش مسؤول عن / This module does NOT own:

- Booking creation, totals, or discount slot mechanics → owned by **Booking** (Loyalty asks Booking to apply a discount line via `BookingDiscountWriter` contract).
- "Net paid amount" calculation per `booking_item` → owned by **Settlement** (Loyalty reads via `BookingItemNetAmountReader` contract).
- Refund detection or refund finalization → owned by **Payments** (Loyalty subscribes to `RefundFinalized`).
- Notification delivery (in-app, push, email) → owned by **Communication** (Loyalty publishes events; Communication subscribes via existing template registry).
- Vendor approval/onboarding gating → owned by **Identity** (Shield permission `loyalty.program.manage.own` is granted only to approved vendors).
- Tier mechanics (silver/gold/platinum), referrals, cross-vendor / platform-wide loyalty, expiration cleanup worker — see §10 cut-list and §11 forbidden list.

> **القاعدة:** لو حسيت إنك بتضيف مسؤولية للـ module مش في القائمة فوق، اوقف. اكتب ADR amendment.

---

## 3. الجداول المملوكة / Tables Owned

من [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §"Loyalty (4) — per vendor":

| Table | الغرض / Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `loyalty_programs` | One program per vendor — name (EN+AR), terms, status (active/paused/archived), optional expiration window | No | No |
| `loyalty_rules` | Versioned earn + redemption parameters: earn rate, redemption ratio, min threshold, max-redeem-pct cap | No | No (only `is_active` BOOLEAN updates allowed; rule values themselves never UPDATE) |
| `loyalty_ledger` | Signed-points entries scoped to `(customer_id, vendor_profile_id)` — source of truth for balance | No | **Yes** (`created_at` only; no `updated_at`, no `deleted_at`) |
| `loyalty_redemptions` | A customer's intent to spend points on a specific booking; lifecycle pending → applied/voided/reversed | No | No (only `status` + `applied_at`/`voided_at`/`reversed_at` may be UPDATEd) |

**Foreign key dependencies (jadāwil mawjuda fi modules tania):**

| FK | المرجع / References | Module |
|---|---|---|
| `loyalty_programs.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `loyalty_programs.created_by` / `updated_by` | `users.id` | Identity |
| `loyalty_ledger.customer_id` | `users.id` | Identity |
| `loyalty_ledger.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `loyalty_ledger.booking_id` | `bookings.id` | Booking |
| `loyalty_ledger.booking_item_id` | `booking_items.id` | Booking |
| `loyalty_ledger.reversed_from_ledger_id` | `loyalty_ledger.id` (self) | Loyalty |
| `loyalty_redemptions.customer_id` | `users.id` | Identity |
| `loyalty_redemptions.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `loyalty_redemptions.booking_id` | `bookings.id` | Booking |
| `loyalty_redemptions.loyalty_rule_id` | `loyalty_rules.id` (snapshot of active rule at apply-time) | Loyalty (self) |

> **Migration order rule:** Identity (vendor_profiles, users) and Booking (bookings, booking_items) must already exist. Loyalty migrates **after** Phase 4 (Settlement) so the `BookingItemNetAmountReader` contract has an implementation to bind to.

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

### ☑ Cross-type (مش type-aware)

The earn formula and redemption math are identical for `rental`, `sale`, and `digital`. Loyalty does **not** branch on `product_type` — there is no per-type Form Request, Action, or Resource. The `product_type` value is denormalized onto `loyalty_ledger.product_type` purely as **informational metadata** for analytics queries; it is never read in branching logic.

This matches the cross-type list in `docs/specs/03_Three_Product_Types.md` §15 (cross-cutting infrastructure modules).

**Type-aware verification still applies via Pest:** because earn fires off `booking_item` completion and the three product types use three different completion state machines, the test suite must complete a rental, a sale, and a digital booking and assert credits appear identically. Architecture test `LoyaltyNoIfElseifOnProductTypeTest` rejects any if/elseif/match on `'rental'|'sale'|'digital'` strings or `ProductType::*` cases inside `app/Modules/Loyalty/`.

> **هذا قرار جذري.** Verified against `docs/specs/03_Three_Product_Types.md` §15 — Loyalty is not in the type-aware list.

---

## 5. Layer Layout

```
app/Modules/Loyalty/
├── Domain/
│   ├── Models/
│   │   ├── LoyaltyProgram.php
│   │   ├── LoyaltyRule.php
│   │   ├── LoyaltyLedgerEntry.php           # booted(): rejects updating + deleting
│   │   └── LoyaltyRedemption.php
│   ├── Enums/
│   │   ├── LedgerEntryType.php              # Earn | Redeem | Reversal | VoidRelease
│   │   └── ProgramStatus.php                # Active | Paused | Archived
│   ├── Events/
│   │   ├── LoyaltyProgramConfigured.php
│   │   ├── LoyaltyPointsEarned.php
│   │   ├── LoyaltyRedemptionApplied.php
│   │   ├── LoyaltyRedemptionVoided.php
│   │   ├── LoyaltyRedemptionReversed.php
│   │   └── LoyaltyLedgerEntryAppended.php
│   ├── States/
│   │   └── Redemption/                      # spatie/laravel-model-states
│   │       ├── RedemptionState.php (abstract)
│   │       ├── Pending.php  Applied.php  Voided.php  Reversed.php
│   ├── Contracts/
│   │   └── PointsBalanceReader.php          # consumed by Communication for "you have X points" notifications
│   └── Services/
│       └── BalanceCalculator.php            # signed-sum ledger + held-pending-redemption deduction
├── Application/
│   ├── Actions/
│   │   ├── ConfigureLoyaltyProgramAction.php
│   │   ├── CalculateLoyaltyPointsAction.php       # invoked by listener after BookingCompleted
│   │   ├── ApplyRedemptionToBookingAction.php
│   │   └── FinalizeRedemptionAction.php           # pending → applied | voided | reversed
│   ├── Services/
│   │   └── (none — Actions cover everything in Phase 5.2)
│   ├── DTOs/
│   │   ├── ProgramDraft.php  RuleDraft.php  RedemptionRequest.php
│   └── Listeners/
│       ├── CreditPointsOnBookingCompleted.php     # ShouldQueue
│       ├── VoidRedemptionOnBookingCancelled.php   # ShouldQueue
│       └── ReverseRedemptionOnBookingRefunded.php # ShouldQueue
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentLoyaltyProgramRepository.php
│   │   ├── EloquentLoyaltyRuleRepository.php
│   │   ├── EloquentLoyaltyLedgerRepository.php   # append() only — no update(), no delete()
│   │   └── EloquentLoyaltyRedemptionRepository.php
│   └── Gateways/
│       └── (none — no external systems in Phase 5.2)
├── Http/
│   ├── Controllers/
│   │   ├── Vendor/LoyaltyProgramController.php
│   │   ├── Vendor/LoyaltyRuleController.php
│   │   ├── Customer/LoyaltyBalanceController.php
│   │   └── Customer/LoyaltyRedemptionController.php
│   ├── Requests/
│   │   ├── Vendor/StoreLoyaltyProgramRequest.php
│   │   ├── Vendor/UpdateLoyaltyProgramRequest.php
│   │   ├── Vendor/StoreLoyaltyRuleRequest.php
│   │   └── Customer/ApplyRedemptionRequest.php
│   └── Resources/
│       ├── LoyaltyProgramResource.php
│       ├── LoyaltyBalanceResource.php
│       └── LoyaltyRedemptionResource.php
├── Filament/
│   ├── Resources/
│   │   └── LoyaltyProgramResource.php             # vendor-scoped (own program only)
│   └── Pages/
│       └── LoyaltyOverviewPage.php                # admin read-only oversight
├── Routes/
│   ├── customer.php
│   ├── vendor.php
│   └── admin.php
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Resources/
│   └── lang/
│       ├── en/loyalty.php
│       └── ar/loyalty.php
└── Providers/
    └── LoyaltyServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 Append-only `loyalty_ledger`; reversal is a new offsetting row

**القرار:** `loyalty_ledger` immutable. Refund-driven reversal appends a new row of opposite sign with `entry_type='reversal'` and `reversed_from_ledger_id` pointing back at the original credit. No UPDATE, no DELETE on prior rows.

**البديل:** Mutable `loyalty_ledger` with a `voided_at` column flipped on refund.

**ليه؟:** Constitution V mandates append-only for ledger tables. Auditors and support staff need a complete forward-only history of every point movement; soft-flip-flag designs make "what was the balance at time T?" expensive and ambiguous. Append-only also makes the earn pipeline trivially idempotent against replay.

### 6.2 Forward-only rule changes — old credits are NOT revalued

**القرار:** When a vendor edits a rule (e.g., changes redemption ratio from 100→1000 to 100→500), the new rule row is inserted with `is_active=true` and the prior active row is set to `is_active=false`. **Previously credited points are not revalued**; redemption math reads the currently-active redemption ratio at redemption time.

**البديل:** Snapshot the active rule's redemption ratio onto every credit row so points retain their "earn-time" purchasing power.

**ليه؟:** Per-row historical valuation doubles ledger size and creates customer confusion ("why is this batch worth more than that one?"). The forward-only stance is documented in the spec Assumptions and is the simpler Phase 1 choice. Vendors who change rules must communicate the change to customers — the platform does not promise retention of historical value.

### 6.3 Earn input is "net paid amount" from Settlement, not raw subtotal

**القرار:** `CalculateLoyaltyPointsAction` reads the `BookingItemNetAmountReader` contract (implemented in Settlement) to determine the minor-unit basis for the earn calculation. Refunds therefore reduce eligible earnings without Loyalty needing to know about the refund pipeline; commission deductions are excluded.

**البديل:** Read `booking_items.subtotal_minor` directly and write a separate adjustment pipeline to claw points back on refund.

**ليه؟:** Centralizes the "what did the customer effectively pay net of refunds and platform commission" calculation in Settlement, where it already exists for wallet/commission logic. Avoids duplicating refund math in Loyalty. Cleaner module boundary — Loyalty does not import Booking or Payments models.

### 6.4 `Idempotency-Key` required on redemption endpoints; earn idempotency enforced at DB layer

**القرار:** All HTTP mutating endpoints (`POST /vendor/loyalty/program`, `PUT /vendor/loyalty/program`, `POST /vendor/loyalty/rules`, `POST /customer/bookings/{id}/redemptions`, `DELETE .../redemptions/{rid}`) require the `Idempotency-Key` header per Constitution §VIII. Earning is event-driven (no HTTP) and is made idempotent by a UNIQUE `(booking_item_id, entry_type='earn')` constraint on `loyalty_ledger` plus a status-check guard in the listener.

**البديل:** Optional Idempotency-Key + a service-layer dedupe based on a hash of payload.

**ليه؟:** Consistent with Constitution §VIII and with the Payments / Booking / Settlement modules' policies. The DB-level UNIQUE on the earn path is the strongest possible guarantee: even a misbehaving listener cannot double-credit.

---

## 7. Inter-Module Communication

### Events بنطلقها / Events we publish:

| Event | When | Payload |
|---|---|---|
| `Loyalty\\LoyaltyProgramConfigured` | Vendor creates or updates their program | `program_id`, `vendor_profile_id`, `status` |
| `Loyalty\\LoyaltyPointsEarned` | After `loyalty_ledger` credit row commits | `customer_id`, `vendor_profile_id`, `points`, `booking_item_id` |
| `Loyalty\\LoyaltyRedemptionApplied` | `pending → applied` (booking paid) | `redemption_id`, `customer_id`, `vendor_profile_id`, `points_held`, `discount_minor` |
| `Loyalty\\LoyaltyRedemptionVoided` | `pending → voided` (booking cancelled/expired) | `redemption_id`, `customer_id`, `vendor_profile_id`, `points_released` |
| `Loyalty\\LoyaltyRedemptionReversed` | `applied → reversed` (booking refunded) | `redemption_id`, `customer_id`, `vendor_profile_id`, `points_returned` |
| `Loyalty\\LoyaltyLedgerEntryAppended` | Every ledger insert (lower-level) | `entry_id`, `entry_type`, `customer_id`, `vendor_profile_id`, `points` |

All published with `DB::afterCommit(fn () => event(...))`.

### Events بنستهلكها / Events we consume:

| Event | Source Module | Action |
|---|---|---|
| `Booking\\BookingCompleted` (per `booking_item_id`) | Booking | `CreditPointsOnBookingCompleted` (queued) → `CalculateLoyaltyPointsAction::execute()` |
| `Booking\\BookingCancelled` (with active pending redemption) | Booking | `VoidRedemptionOnBookingCancelled` (queued) → `FinalizeRedemptionAction::execute()` (`pending → voided`) |
| `Payments\\RefundFinalized` (per `booking_item_id`) | Payments | `ReverseRedemptionOnBookingRefunded` (queued) → `FinalizeRedemptionAction::execute()` (`applied → reversed`) + earn-side proportional reversal |

### Public Contracts (interfaces بنوفرها):

| Contract | الغرض / Purpose | Implementation |
|---|---|---|
| `Loyalty\\Domain\\Contracts\\PointsBalanceReader` | Communication module reads a customer's per-vendor balance to render notification bodies and SMS templates | `EloquentPointsBalanceReader` (wraps `BalanceCalculator`) |

### Public Contracts (interfaces بنستخدمها من غيرنا):

| Contract | From Module | Why we need it |
|---|---|---|
| `Settlement\\Domain\\Contracts\\BookingItemNetAmountReader` | Settlement | Source of truth for the minor-unit basis of earn calculations |
| `Booking\\Domain\\Contracts\\BookingDraftReader` | Booking | Read draft `(vendor_profile_id, subtotal_minor, currency, customer_id)` to validate redemption requests |
| `Booking\\Domain\\Contracts\\BookingDiscountWriter` | Booking | Apply a redemption-derived discount line to a draft booking — Loyalty never mutates `bookings.*` directly |
| `Identity\\Domain\\Contracts\\VendorLookup` | Identity | Resolve `vendor_profile_id` from a `booking_vendor_id` and confirm vendor approval status |

> **القاعدة:** لو احتجت تقرأ Eloquent model من module تاني → ده signal تطلب Contract منه، مش `use App\Modules\OtherModule\Domain\Models\X`.

---

## 8. Filament Footprint

| Resource / Page | الغرض / Purpose | Translatable? |
|---|---|---|
| `Loyalty/Filament/Resources/LoyaltyProgramResource.php` | Vendor self-service: create/update one program (name, terms, status, expiration_days) and manage rules through nested form | Yes (name, terms, rule label) |
| `Loyalty/Filament/Pages/LoyaltyOverviewPage.php` | Admin read-only oversight: any vendor's program, ledger entries, redemptions, with filters by vendor + status | Read display only |

**Navigation group:** `Vendors` (within the vendor panel — same group as `VendorProfileResource`); `Reporting` (within the admin panel for `LoyaltyOverviewPage`).

**Permissions generated by Shield:**

- `view_any_loyalty_program`, `view_loyalty_program`, `create_loyalty_program`, `update_loyalty_program`, `delete_loyalty_program`
- Custom: `loyalty.program.manage.own` (vendor self-scope), `loyalty.view_any` (admin read), `loyalty.view_ledger` (admin oversight), `loyalty.rule.manage.own` (vendor rule edits)

> **Reminder:** بعد إنشاء Resources الجديدة، شغل `php artisan shield:generate --all`.

---

## 9. Testing Strategy

**Pest groups:** `loyalty` + per-type verification groups `rental`, `sale`, `digital` on the earn + reversal paths (cross-type module, but completion paths differ per product type so all three must be exercised).

**Test files location:**
```
tests/
├── Feature/Modules/Loyalty/
│   ├── ConfigureProgramTest.php
│   ├── EarnAfterCompletionTest.php          # rental + sale + digital scenarios
│   ├── PerVendorIsolationTest.php
│   ├── RedemptionMathTest.php
│   ├── RedemptionCapAndThresholdTest.php
│   ├── RedemptionLifecycleTest.php          # pending → applied | voided | reversed
│   ├── RefundReversalTest.php               # rental + sale + digital scenarios
│   ├── IdempotentEarnReplayTest.php
│   ├── ConcurrentRedemptionTest.php
│   └── LoyaltyLocaleTest.php                # EN + AR rendering
└── Unit/Modules/Loyalty/
    ├── BalanceCalculatorTest.php
    └── RedemptionStateTransitionsTest.php
```

**التغطية المطلوبة / Required coverage:**

- [x] Happy path (configure → earn → redeem)
- [x] Auth (unauthenticated → 401)
- [x] Authorization (non-vendor cannot configure; cross-vendor redemption rejected)
- [x] Validation (`min_points_to_redeem`, `max_redeem_pct_bps` cap, `discount > subtotal` rejection)
- [x] Idempotency (HTTP `Idempotency-Key` replay; `BookingCompleted` event replay produces zero double-credits)
- [x] Locale (EN response, AR response, AR validation error body)
- [x] **All three product types** on earn + reversal paths (cross-type module, but state machines differ)
- [x] Architecture test: no imports of Booking/Settlement/Identity/Payments models
- [x] Architecture test: append-only enforcement on `loyalty_ledger`
- [x] Architecture test: no if/elseif/match on `product_type` strings or `ProductType::*` cases
- [x] Architecture test: no direct mutation of `bookings`/`booking_items` from Loyalty

**Architecture test pattern:**

```php
test('Loyalty does not import other module models')
    ->expect('App\Modules\Loyalty')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
        'App\Modules\Identity\Domain\Models',
        'App\Modules\Payments\Domain\Models',
    ]);
```

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) — Phase 5.2 cut-list:

- [ ] Referral rules (refer-a-friend point credits) → defer to **Phase 1.5**
- [ ] Background expiration cleanup job → defer to **Phase 7.0** (schema captures `expiration_days`; only the worker is deferred)
- [ ] Admin "manual point grant" tool → defer to **Phase 6.0** (admin can use raw DB inserts for support cases in the meantime)
- [ ] Loyalty-specific email digest ("you earned X points this month") → defer to **Phase 1.5** (ship in-app + push only via existing Communication templates)

---

## 11. Open Questions

> لما يتحلوا، حول كل واحد لـ "Internal Decision" في القسم 6 أو لـ ADR منفصل.

- [x] **Earn timing on multi-vendor bookings.** **Decision:** Booking module emits `BookingCompleted` per `booking_item` (one event per item, carrying `booking_item_id` and `vendor_profile_id`). The `CreditPointsOnBookingCompleted` listener handles one item per invocation. If the Booking module ever changes to a per-booking event it must include all `booking_item_id`s; the listener would iterate them. No architectural change needed in Loyalty for Phase 5.2.
- [x] **Cancellation semantics for `paused` programs.** **Decision:** A `paused` program freezes **new earnings** only. Existing earned points remain redeemable while the program is paused (consistent with spec edge case "Vendor disables the program after points have been earned"). A future "freeze redemptions too" flag is deferred to Phase 1.5 — not built now. `ApplyRedemptionToBookingAction` checks `program.status IN ('active', 'paused')` for redemption eligibility and only `status = 'active'` for earning eligibility.
- [x] **Floor vs round on partial-refund reversal points.** **Decision:** Use `floor()` — it slightly favors the customer and avoids over-crediting on reversal (platform-friendly bias when flooring the earn direction vs ceiling the reverse). This is intentional and consistent with spec §"Refund of a partially-redeemed booking".

> ✅ Open Questions resolved 2026-05-03. ADR status promoted to `Accepted`.

---

## 12. Implementation Checklist

عند تنفيذ الـ module:

- [ ] Migrations في `Loyalty/Database/Migrations/` بالترتيب: `loyalty_programs` → `loyalty_rules` → `loyalty_redemptions` → `loyalty_ledger` (last because it FKs to redemptions)
- [ ] Models تحت `Domain/Models/` (relationships, casts, scopes ONLY — مفيش business logic)
- [ ] `LoyaltyLedgerEntry::booted()` registers `updating` / `deleting` rejection handlers
- [ ] Cross-type Actions (no per-type variants) — `match($enum)` not used
- [ ] API Resources مع locale conversion (EN+AR)
- [ ] Filament `LoyaltyProgramResource` (vendor scope) + `LoyaltyOverviewPage` (admin read-only)
- [ ] `LoyaltyServiceProvider` مسجل في `bootstrap/providers.php`
- [ ] Pest tests كاملة per requirement فوق + per-type coverage on earn + reversal
- [ ] `php artisan shield:generate --all`
- [ ] Translations في `Resources/lang/en/loyalty.php` و `Resources/lang/ar/loyalty.php`
- [ ] 4 architecture tests pass (no-cross-import, append-only, no-if-elseif-on-product-type, no-direct-booking-mutation)
- [ ] هذا الـ ADR محدّث إلى `Accepted` بعد إجابة الـ Open Questions في §11
- [ ] `docs/adr/README.md` محدّث في القائمة الرئيسية
