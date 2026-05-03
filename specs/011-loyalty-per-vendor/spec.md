---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Loyalty (Per-Vendor)

**Feature Branch**: `011-loyalty-per-vendor`
**Phase**: 5.2 — Week 6 (`docs/specs/09_Phasing_Plan.md`)
**Created**: 2026-05-03
**Status**: Draft
**Input**: User description: Phase 5.2 — Loyalty (per-vendor). Each vendor configures their own loyalty rules. Customers earn points per vendor on completed bookings and redeem them on later bookings with the same vendor.

**Tables touched** (`docs/specs/11_DB_Schema.md`): `loyalty_programs`, `loyalty_rules`, `loyalty_ledger` (append-only), `loyalty_redemptions`.

**Locked decisions reaffirmed:**
- Loyalty is **per-vendor**, never platform-global. Customer balances are scoped to `(customer_id, vendor_profile_id)`.
- `loyalty_ledger` is append-only (no UPDATE, no soft delete) — every credit, debit, expiry, and reversal is a new row.
- Money stays in integer minor units (piastres) via `Brick\Money`. Points are integers.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Vendor configures a loyalty program (Priority: P1)

A vendor opens their admin panel, enables a loyalty program for their account, sets earn and redemption rules (e.g., "1 point per EGP spent", "100 points = 10 EGP off", "max 20% of order can be paid with points", "minimum 200 points before redemption"), and activates it. From that moment on, completed bookings with that vendor accrue points for the customer.

**Why this priority**: Without vendor-side configuration, no customer can earn or redeem anything. This is the foundational story.

**Independent Test**: Create a vendor, configure a program with default rules through the Filament admin, and verify the program is persisted, the rules are stored, and the program shows as `active`. No customer activity required.

**Acceptance Scenarios**:

1. **Given** an approved vendor with no existing program, **When** the vendor creates a loyalty program with earn rate 1 point per EGP and redemption ratio 100 points = 10 EGP, **Then** the program is saved with `status = active` and is uniquely scoped to that vendor (one program per vendor enforced).
2. **Given** an existing active program, **When** the vendor edits the earn rate, **Then** the change applies only to bookings completed after the edit; previously credited points are unaffected.
3. **Given** a vendor without a configured program, **When** a booking with that vendor completes, **Then** no points are credited and no error is raised.

---

### User Story 2 — Customer earns points on a completed booking (Priority: P1)

A customer completes a booking with a vendor that runs a loyalty program. After the booking transitions to a completed lifecycle state, the system credits points to the customer's balance with that specific vendor based on the program's earn rules. The customer can see the credit in their loyalty history for that vendor.

**Why this priority**: Earning is the entry point of the loop — without it there is nothing to redeem.

**Independent Test**: Seed an active program for vendor V, complete a booking of EGP 500 between customer C and vendor V, and assert that a `loyalty_ledger` credit row exists for `(customer C, vendor V)` worth 500 points, and that the customer's balance with vendor V is 500 while their balance with any other vendor is 0.

**Acceptance Scenarios**:

1. **Given** an active program with earn rate 1 point per EGP, **When** a booking_item with vendor V completes for a net amount of 500 EGP, **Then** a `loyalty_ledger` row of `+500` points is appended for `(customer, vendor V)` after the transaction commits.
2. **Given** the same customer completes bookings with two different vendors that each have programs, **When** both bookings complete, **Then** the customer accrues a separate balance per vendor — balances never aggregate across vendors.
3. **Given** a booking is fully refunded after points were credited, **When** the refund is finalized, **Then** an offsetting debit row is appended to `loyalty_ledger` so the net balance reflects the reversal (no UPDATE, no DELETE on prior rows).
4. **Given** the platform language is Arabic, **When** the customer views their loyalty history, **Then** all rule labels, vendor names, and reasons render in Arabic with RTL layout.

---

### User Story 3 — Customer redeems points on a new booking (Priority: P1)

A customer creating a new booking with vendor V sees their available point balance with V and an option to apply points toward the order. They choose how many points to redeem (within the program's limits), and the booking total decreases accordingly. After payment is captured, the redeemed points are debited and recorded as a redemption.

**Why this priority**: Redemption is the user-visible payoff; without it the program has no perceived value.

**Independent Test**: Pre-credit a customer with 1,000 points for vendor V via the ledger. Create a draft booking with vendor V worth 200 EGP. Apply 500 points (worth 50 EGP at the configured ratio). Verify the booking total drops by 50 EGP, the redemption record is created in `pending` state, and after the booking is paid, the points are debited and the redemption marks `applied`.

**Acceptance Scenarios**:

1. **Given** a customer has 1,000 points with vendor V and a draft booking of 200 EGP with V, **When** the customer applies 500 points and the program ratio is 100 points = 10 EGP with `max_redeem_pct = 20%`, **Then** the redemption is accepted (50 EGP discount = 25% of 200 EGP — exceeds cap → reject with localized error; 400 points = 40 EGP = 20% → accept).
2. **Given** a customer has 100 points and `min_points_to_redeem = 200`, **When** the customer attempts to redeem any amount, **Then** the request is rejected with a localized validation error and no ledger row is written.
3. **Given** a redemption is `pending` and the customer abandons the booking, **When** the booking expires or is cancelled before payment, **Then** the redemption is voided and no debit is appended (points remain spendable).
4. **Given** a redemption is `applied` and the booking is later refunded, **When** the refund is finalized, **Then** the redeemed points are credited back to the customer with that vendor as a new ledger row referencing the original redemption.
5. **Given** a customer attempts to redeem points earned with vendor A on a booking with vendor B, **When** the redemption is submitted, **Then** the system rejects it because balances are vendor-scoped.

---

### Edge Cases

- Customer has exactly the minimum redemption threshold — must be allowed (boundary inclusive).
- Vendor disables the program after points have been earned — earned balance must remain redeemable on bookings with that vendor; only new earnings stop.
- Multiple booking items in one booking with the same vendor — earnings sum across items but produce a single ledger entry per booking_item completion event.
- Booking spans multiple vendors — each vendor's portion of the booking earns into its own per-vendor balance independently.
- Concurrent redemption attempts (two devices) — only one can succeed; the other must fail with insufficient-balance after the ledger is checked under lock.
- Refund of a partially-redeemed booking — credit-back amount must be proportional to the refund share, not the full redemption.
- Program ratio change between earning and redemption — already-earned points retain their value at redemption time per the **current** active rule (locked decision: rule changes are forward-looking and apply at time of redemption, not retroactively).
- Booking total after redemption discount would go below zero — cap the discount at the booking subtotal.

## Requirements *(mandatory)*

### Functional Requirements

**Vendor program configuration**

- **FR-LOY-001**: Each vendor MUST be able to create at most one loyalty program (uniqueness on `loyalty_programs.vendor_profile_id`).
- **FR-LOY-002**: A loyalty program MUST support an `active` / `paused` / `archived` status; only `active` programs accrue or allow redemption.
- **FR-LOY-003**: A vendor MUST be able to define earn rules in `loyalty_rules` including: earn rate (points per EGP minor unit), redemption ratio (points per EGP minor unit of discount), `min_points_to_redeem`, and `max_redeem_pct` (basis points cap on order subtotal).
- **FR-LOY-004**: Vendors MUST be able to optionally set a points expiration window in days (deferred cleanup job per cut-list — only the field and read-side filtering apply in Phase 5.2).
- **FR-LOY-005**: All vendor-facing labels in the program (program name, terms, reason text) MUST be translatable EN + AR per `docs/specs/04_Bilingual_Spec.md`.

**Earning**

- **FR-LOY-010**: When a booking_item completes for a vendor that has an `active` program, the system MUST credit the customer's balance with that vendor according to the program's earn rule.
- **FR-LOY-011**: Earning MUST be triggered by the `BookingCompleted` domain event and executed only after the originating database transaction commits.
- **FR-LOY-012**: Each credit MUST be a new append-only row in `loyalty_ledger` carrying `customer_id`, `vendor_profile_id`, `booking_item_id`, signed `points` amount, `reason`, and `created_at`.
- **FR-LOY-013**: Earning MUST be idempotent per `booking_item_id` — replaying the event MUST NOT double-credit.
- **FR-LOY-014**: Earning calculations MUST be based on the booking_item's net paid amount in minor units (excluding refunded portions and excluding the platform-side commission split).

**Redemption**

- **FR-LOY-020**: Customers MUST be able to view their available point balance per vendor (sum of `loyalty_ledger` rows for `(customer, vendor)` minus any `pending` redemptions held against them).
- **FR-LOY-021**: Customers MUST be able to apply points to a draft booking with the same vendor whose points they hold, subject to `min_points_to_redeem` and `max_redeem_pct`.
- **FR-LOY-022**: A redemption MUST be created in `loyalty_redemptions` with status `pending` at apply-time, transition to `applied` after the booking is paid, `voided` if the booking is cancelled or expires, and `reversed` if the booking is refunded.
- **FR-LOY-023**: While a redemption is `pending`, the held points MUST be unavailable for any other booking by the same customer with the same vendor.
- **FR-LOY-024**: When a redemption transitions to `applied`, a debit row MUST be appended to `loyalty_ledger` referencing the redemption.
- **FR-LOY-025**: When a redemption is `voided`, no ledger debit MUST be written; the held points return to the available balance immediately.
- **FR-LOY-026**: When a redemption is `reversed`, a credit row MUST be appended to `loyalty_ledger` referencing the original debit.
- **FR-LOY-027**: Redemption MUST reject any attempt to spend points earned with one vendor on a booking with a different vendor.
- **FR-LOY-028**: Redemption MUST reject any attempt that would discount the booking subtotal below zero or above `max_redeem_pct`.

**Audit, integrity, and platform conventions**

- **FR-LOY-030**: `loyalty_ledger` MUST be append-only — no UPDATE, no DELETE, no soft delete (per `docs/specs/02_Tech_Decisions.md` §4 and `CLAUDE.md` §15).
- **FR-LOY-031**: All loyalty-mutating endpoints MUST honor the `Idempotency-Key` header per platform convention.
- **FR-LOY-032**: All vendor and customer admin actions on programs and rules MUST be recorded in `audit_logs`.
- **FR-LOY-033**: All API responses MUST follow the standard `{ data, meta, errors }` envelope and respect the caller's locale at the Resource layer.
- **FR-LOY-034**: External identifiers MUST use ULID `public_id`; internal joins use BIGINT `id`.

**Filament admin (per `.claude/rules/filament.md`)**

- **FR-LOY-040**: Vendors MUST manage their loyalty program through a dedicated Filament Resource scoped to their own vendor profile.
- **FR-LOY-041**: Platform admins MUST be able to view (read-only) any vendor's program, rules, ledger, and redemptions for support purposes.
- **FR-LOY-042**: Money columns in admin tables MUST display via `->money('EGP', divideBy: 100)`; raw `_minor` values MUST NOT be exposed.

### Out of scope (Phase 1.5 / 7.0 — do not build now)

- Referral rules (deferred to Phase 1.5 per cut-list).
- Background expiration cleanup job (deferred to Phase 7.0 per cut-list). The expiration **field** is captured; the **enforcement job** is not built.
- Cross-vendor or platform-wide loyalty.
- Tier mechanics (silver/gold/etc.) — out of Phase 1 entirely (`docs/specs/01_PRD.md` §5.2 / §11).
- Loyalty as a marketing channel (campaigns, broadcast credits) — Phase 1.5+.

### Key Entities

- **Loyalty Program** (`loyalty_programs`): One per vendor. Holds program-level metadata (name EN+AR, status, currency, optional expiration window).
- **Loyalty Rule** (`loyalty_rules`): Earn and redemption parameters for a program — earn rate, redemption ratio, `min_points_to_redeem`, `max_redeem_pct`. Versioned by row to allow forward-only rule changes.
- **Loyalty Ledger Entry** (`loyalty_ledger`): Append-only signed-points entries scoped to `(customer_id, vendor_profile_id)`. Source of truth for balances. Carries `reason`, optional `booking_item_id`, optional `redemption_id`.
- **Loyalty Redemption** (`loyalty_redemptions`): A customer's intent to spend points on a specific booking. Has its own status lifecycle (`pending` → `applied` | `voided` | `reversed`) and references the booking, the customer, the vendor, the program, the points held, and the equivalent discount in minor units.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-LOY-001**: A vendor can configure an active loyalty program from a clean state in under 3 minutes.
- **SC-LOY-002**: For 100% of completed bookings whose vendor has an active program, the corresponding `loyalty_ledger` credit row exists within 5 seconds of the booking-completed event commit.
- **SC-LOY-003**: A customer can apply points and see the updated booking total in under 2 seconds.
- **SC-LOY-004**: A customer's per-vendor balance, computed as the signed sum of their `loyalty_ledger` rows minus active `pending` redemptions, equals the displayed balance for 100% of accounts at any time (zero drift).
- **SC-LOY-005**: Replaying any `BookingCompleted` event for the same `booking_item_id` produces zero additional ledger rows (idempotency holds in 100% of replay tests).
- **SC-LOY-006**: No redemption is ever accepted that would push a booking subtotal below zero or above the program's `max_redeem_pct` cap (0 violations across the test suite).
- **SC-LOY-007**: A full refund of a redemption-bearing booking restores the redeemed points to the customer's per-vendor balance within 10 seconds of refund finalization.
- **SC-LOY-008**: Cross-vendor redemption attempts are rejected in 100% of cases and produce a localized (EN/AR) error message.

## Assumptions

- The Booking module already emits a `BookingCompleted` domain event after `DB::commit` per `docs/specs/02_Tech_Decisions.md`. If the event is not yet wired, Phase 5.2 wires it as part of Day 1.
- The Settlement module owns the calculation of "net paid amount" per booking_item (after refunds, before commission). Loyalty consumes that figure via a contract from `Settlement\Domain\Contracts`, never by reaching into Settlement models directly (per `.claude/rules/modules.md`).
- Vendors may exist without a loyalty program; absence of a program is a normal state, not an error.
- The "default" example in the deliverable (1 point per EGP, 100 points = 10 EGP) is illustrative only — actual values are vendor-configurable within sane platform-enforced bounds (lower bound: 1 point per minor unit; upper bound: 1 point per 100 minor units; `max_redeem_pct` capped at 5000 bps = 50%).
- Rule changes are **forward-looking only**: previously credited points are valued at redemption time using the **currently active** redemption ratio. This is consistent with Phase 1 simplicity and avoids per-row historical valuation.
- The Filament Resource lives at `app/Modules/Loyalty/Filament/Resources/LoyaltyProgramResource.php` per the modular layout in `CLAUDE.md`. A new `Loyalty` module is introduced; cross-module access goes through `Domain/Contracts/`.
- Translations follow `spatie/laravel-translatable` JSON columns for all customer-facing strings; the package is already on `docs/specs/10_Package_List.md`.
- Notifications about earned/redeemed points (in-app + push) are delivered through the existing Communication module via templates keyed by `loyalty.points_earned` and `loyalty.points_redeemed`. Template authoring is included; channel rollout follows existing notification preferences (per FR-23–FR-26).
