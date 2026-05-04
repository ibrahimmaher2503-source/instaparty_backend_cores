# Feature Specification: Vendor Subscription Tiers (Phase 1.7)

**Feature Branch**: `018-subscriptions-tiers`
**Created**: 2026-05-03
**Status**: Draft
**Input**: User description: "PHASE 1.7 — Subscriptions. Affected modules: Subscriptions (NEW), Identity, Catalog, Settlement, Discovery, Payments. 8 tables, 4 tiers (free/silver/gold/premium), feature gating, billing, auto-renewal, admin override."

> **Phase scope (confirmed 2026-05-03):** Approved as **Phase 1.7**, an authorized extension of Phase 1 ahead of the original Phase 2 placement in `docs/specs/01_PRD.md` §5.2 / §11. `09_Phasing_Plan.md` is to be updated during `/speckit.plan` to reflect the new Phase 1.7 slot. All other locked-stack constraints still apply (no new packages outside `10_Package_List.md`, money in minor units, EN+AR translatable, append-only ledger discipline, etc.).

## Clarifications

### Session 2026-05-03

- Q: Phase placement — proceed as Phase 1.7, defer to Phase 2, or spike-only? → A: Approved as Phase 1.7, ahead of original PRD schedule.
- Q: Admin override vs. active paid subscription — suspend / layer / refuse / replace? → A: Override layers on top; base subscription keeps billing and renewing normally. Override wins for gating and commission until it expires; on expiry, vendor returns to the underlying paid (or Free) state.
- Q: Recurring-token availability in the Paymob adapter — present, absent, or both behind a flag? → A: Build both behind a feature flag — implement saved-token renewal AND vendor-initiated fallback; runtime choice via `feature_flags`, defaulted to the adapter's current capability.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A new vendor is auto-enrolled on the Free tier and operates within its limits (Priority: P1)

When a vendor account is approved (or self-registers), the platform automatically attaches a Free-tier subscription so the vendor can immediately start operating with a clearly capped baseline (limited active services, no featured placement, no Excel import, standard commission rate). All gated actions across Catalog, Discovery, and Settlement consult the vendor's current subscription before allowing or pricing the action.

**Why this priority**: Without this, every new vendor either (a) gets unlimited capabilities by default — defeating the entire monetisation feature — or (b) gets blocked from doing anything until they pay. Free-tier autoenrolment is the foundation that makes every other story safe to ship.

**Independent Test**: Register a new vendor, confirm a `vendor_subscriptions` row pointing at the Free plan exists with `status = active`, attempt to create more services than the Free limit, and verify the (N+1)th creation is rejected with a clear "upgrade required" error. No payment, billing, or upgrade flow needs to work for this story.

**Acceptance Scenarios**:
1. **Given** a brand-new vendor profile is approved, **When** the approval completes, **Then** the system creates an active Free-tier subscription with no expiry and no payment record.
2. **Given** a vendor on the Free tier already at their service-creation limit, **When** the vendor tries to publish another service, **Then** the action is blocked with a message indicating the limit and the next tier that would unblock them.
3. **Given** a vendor on the Free tier, **When** an admin views the vendor profile, **Then** the current tier, plan limits, and effective-since date are visible.

---

### User Story 2 — A vendor upgrades to a paid tier, pays, and immediately gains expanded capabilities (Priority: P1)

A vendor self-serves an upgrade from their dashboard: picks a plan (Silver / Gold / Premium), reviews the price (monthly or yearly), confirms, pays via the existing payment gateway, and is moved to the new tier on successful capture. From that moment, the new tier's limits and commission rate apply to all subsequent actions; existing services and bookings are not retroactively affected.

**Why this priority**: This is the core revenue path. P1 because without it the feature does not generate revenue.

**Independent Test**: Seed a Free-tier vendor, call the subscribe endpoint with a chosen plan and idempotency key, simulate a successful gateway capture webhook, and verify (a) the active subscription has switched to the chosen plan, (b) an invoice is recorded, (c) the previous Free subscription is closed with the correct end-date, (d) gated capabilities now reflect the new tier.

**Acceptance Scenarios**:
1. **Given** an authenticated vendor on the Free tier, **When** they request the list of available plans, **Then** they see all four tiers with localised name, description, price, billing cycle, and a per-feature comparison.
2. **Given** a vendor selecting a paid plan with a valid idempotency key, **When** they confirm subscription, **Then** an invoice is created in `pending` state, a payment attempt is initiated, and the response returns a checkout reference.
3. **Given** a successful payment capture event for a subscription invoice, **When** the system processes it, **Then** the matching `vendor_subscription` row activates, the invoice moves to `paid`, an audit row is written, and the previous (lower) subscription is marked `superseded` with `ended_at = now`.
4. **Given** the same idempotency key replayed within 24 hours, **When** the subscribe endpoint is called again, **Then** the original result is returned and no duplicate invoice or payment is created.

---

### User Story 3 — Subscription renews automatically at end of cycle, with a grace period and graceful expiration (Priority: P1)

When a paid subscription nears its `current_period_end`, the system attempts auto-renewal using the gateway's saved recurring/payment token. If the renewal succeeds, the period rolls forward seamlessly. If it fails, the subscription enters a configurable grace period during which the vendor keeps their current capabilities but is warned. After grace expires, the subscription transitions to `expired`, the vendor falls back to the Free tier, and any services exceeding the Free-tier active-services cap are auto-paused (oldest excess first) rather than deleted.

**Why this priority**: P1 because monetisation only works if the subscription cycle is reliable, and downgrading must not destroy vendor data.

**Independent Test**: Seed a paid subscription whose `current_period_end` is in the past, run the renewal job, force the gateway to fail, advance time past the grace window, run the expiration job, and verify the vendor's tier dropped to Free, the correct excess services are paused (not deleted), and an audit trail and customer-facing notification were emitted.

**Acceptance Scenarios**:
1. **Given** an active paid subscription with a saved recurring token, **When** the renewal job runs at `current_period_end`, **Then** a renewal payment is attempted and on success a new invoice is created and the period is extended by one cycle.
2. **Given** a renewal payment fails, **When** the failure is recorded, **Then** the subscription enters `past_due` for the configured grace window, the vendor is notified, but capabilities are unchanged.
3. **Given** a `past_due` subscription whose grace window has elapsed, **When** the expiration job runs, **Then** the subscription becomes `expired`, the vendor is auto-attached to a fresh Free-tier subscription, services beyond the Free cap are auto-paused, and the lifecycle audit table records every step.
4. **Given** a vendor cancels their paid subscription before period end, **When** the cancel request is processed, **Then** the subscription is flagged `cancel_at_period_end = true`, no refund is issued for the current cycle, and the downgrade to Free occurs cleanly at `current_period_end`.

---

### User Story 4 — Commission rate respects the vendor's subscription tier (Priority: P2)

The Settlement module already resolves commission via four-level fallback (`category × type → category × NULL → NULL × type → NULL × NULL`). This story adds a fifth, lowest-priority lookup keyed on `subscription_plan_id` so paid tiers can carry a discounted commission rate. The match precedence remains "most specific wins": existing category/type rules continue to override tier-based rates unless the tier rule is intentionally configured for a specific category/type combination.

**Why this priority**: P2 because revenue is captured the moment Story 2 ships; tier-based commission discounts are a secondary lever the platform can switch on later without changing the user-facing flow.

**Independent Test**: Seed two commission rules — a global default and a Premium-tier discount. Run `CalculateCommissionAction` for a booking item belonging to a Premium-tier vendor, then for the same item with a Free-tier vendor, and verify each picks the correct rate. The snapshot stored on `booking_items.commission_bps` must reflect the rate at booking time, not at settlement time.

**Acceptance Scenarios**:
1. **Given** a Premium-tier discount rule and no category/type overrides, **When** a Premium vendor's booking item is priced, **Then** the discounted rate applies and is snapshotted onto the booking item.
2. **Given** a category/type-specific commission rule exists alongside a tier rule, **When** both could match, **Then** the category/type rule wins (per existing locked precedence).
3. **Given** a vendor whose tier changes mid-cycle, **When** a new booking is created after the change, **Then** the new booking item receives the new tier's rate; previously snapshotted bookings are untouched.

---

### User Story 5 — Featured placement and other gated capabilities respect the active tier (Priority: P2)

Discovery's "featured" placements (and any other capability that's gated by tier — Excel import access, max active services, max gallery images per service, etc.) consult `SubscriptionPolicy` before allowing the action. Free vendors see no "feature this service" affordance at all; paid tiers see it but each plan has a cap on simultaneously featured services.

**Why this priority**: P2 because it directly drives upgrade pressure but is not strictly required for the first paid customer to convert.

**Independent Test**: Seed three vendors at Free, Silver, and Gold tiers with the same number of services. Attempt to feature a service from each. Confirm the Free attempt is rejected, the Silver and Gold attempts succeed up to their respective caps, and the Discovery query that returns featured services correctly excludes any vendor whose subscription has lapsed.

**Acceptance Scenarios**:
1. **Given** a Free-tier vendor, **When** they attempt to feature a service, **Then** the action is denied and the response indicates which tier unlocks featuring.
2. **Given** a Silver-tier vendor at their featured-services cap, **When** they attempt to feature one more, **Then** the action is denied with a "limit reached" message naming the cap.
3. **Given** a Gold-tier vendor whose subscription expires, **When** the next Discovery query runs, **Then** their previously featured services are no longer surfaced as featured (but the underlying service rows remain published / paused per Story 3).

---

### User Story 6 — Admin can override a vendor's tier with a full audit trail (Priority: P3)

A platform admin can manually override a vendor's subscription tier (e.g., comp a Premium plan, run a beta program, resolve a billing dispute) with a required reason and an optional expiry date. The override is recorded in the lifecycle audit table with the actor, before/after states, and reason. While an override is active it behaves like a normal subscription for all gates and commission lookups but does not auto-renew or generate invoices.

**Why this priority**: P3 because it is an operational lever rather than a customer-facing feature; the rest of the system can ship without it for the first launch.

**Independent Test**: As an admin, override a Free vendor to Premium with reason "Beta partner" and expiry of 30 days. Confirm the override is reflected in the policy gates, that the audit row contains actor + before/after + reason, that no invoice or payment is created, and that after expiry the vendor falls back to Free without billing churn.

**Acceptance Scenarios**:
1. **Given** an admin with the appropriate permission, **When** they apply an override on a vendor profile, **Then** the override is created, all gates immediately reflect the new tier, and an audit entry is written.
2. **Given** an active admin override layered on top of a paid subscription, **When** the underlying subscription's renewal date arrives, **Then** the underlying subscription bills and renews normally; the override remains the effective tier for gating and commission until it expires.
3. **Given** an override with a future expiry, **When** the expiry passes, **Then** the override ends automatically, the vendor returns to whatever tier they had immediately before the override, and the lifecycle audit captures the transition.

---

### Edge Cases

- **Vendor in the middle of an upgrade payment, then cancels** → invoice stays `pending` until the gateway resolves; no tier change happens until capture; if capture later succeeds anyway, the upgrade is honoured because the request was idempotent and confirmed.
- **Two simultaneous subscribe calls with the same idempotency key** → only one invoice/payment exists; both calls return the same response.
- **Subscription expires while a vendor still has active bookings** → bookings are unaffected; only future actions are gated. Commission already snapshotted on `booking_items` is not retroactively recomputed.
- **Plan price changes after a vendor subscribed** → existing subscriptions continue at the price snapshotted on their current invoice; the new price applies on next renewal.
- **Vendor exceeds new tier limits because they downgraded voluntarily** → same auto-pause logic as Story 3 applies (oldest excess services paused, never deleted).
- **Tier-based commission rule conflicts with a category × type rule** → the more specific rule (category × type) wins; tier is the lowest-precedence lookup.
- **Admin override applied to a vendor with an active paid subscription** → the paid subscription continues to renew/bill independently; the override is a separate layer that wins for gating until it expires (assumption to confirm in clarification).
- **Free tier already exists but seeder is re-run** → seeder must be idempotent on `(plan_code)`.
- **Renewal job runs while gateway webhook for the same period is also in flight** → idempotency key on the renewal payment plus uniqueness of `(subscription_id, current_period_end)` prevent double charging.
- **Vendor with a pending subscription tries to cancel** → cancel marks the pending subscription as cancelled; if a payment lands afterwards, it's auto-refunded per the existing refund flow (Phase 1.4).

---

## Requirements *(mandatory)*

### Functional Requirements

**Plans & feature catalog**
- **FR-001**: System MUST seed exactly four built-in plan codes — `free`, `silver`, `gold`, `premium` — each with localised (EN+AR) name and description, monthly and yearly price (in minor units + currency), and a `is_default` flag set on `free`.
- **FR-002**: System MUST express each plan's capability as one or more rows in a `plan_features` catalog (feature key + value), so adding a new gate later does not require a schema change.
- **FR-003**: System MUST expose to vendors a localised list of all plans (including the Free tier) with their feature comparison.

**Subscription lifecycle**
- **FR-004**: System MUST create an active Free-tier `vendor_subscriptions` row automatically when a vendor profile is approved, and MUST NOT allow a vendor to be without an active subscription at any point.
- **FR-005**: System MUST allow a vendor to subscribe to a paid plan via a single endpoint that accepts a plan code, billing cycle (monthly/yearly), and an `Idempotency-Key` header; the endpoint MUST persist an idempotency record for 24 hours.
- **FR-006**: System MUST initiate a payment via the existing payment gateway abstraction (no new gateway), saving the recurring/payment token for auto-renewal where the gateway supports it. The renewal mode (unattended saved-token charge vs. vendor-initiated invoice-and-notify fallback) MUST be selectable at runtime via a `subscriptions.recurring_tokens_enabled` entry in `feature_flags`, defaulted to match the current Paymob adapter capability.
- **FR-007**: System MUST activate the new subscription only after the matching invoice is captured (`paid`), and MUST close the previous subscription with `ended_at` and reason `superseded`.
- **FR-008**: System MUST auto-renew an active paid subscription at `current_period_end`. When `subscriptions.recurring_tokens_enabled` is true, the renewal job MUST attempt an unattended payment with the saved token; when false, the job MUST instead create a `pending` renewal invoice and notify the vendor to pay it before the grace window expires.
- **FR-009**: System MUST treat renewal failure as `past_due`, keep capabilities for a configurable grace window, and emit a notification to the vendor.
- **FR-010**: System MUST transition `past_due` subscriptions to `expired` once the grace window passes, attach a fresh Free-tier subscription, and pause services exceeding the Free cap (oldest first), without deleting them.
- **FR-011**: System MUST allow a vendor to cancel an active paid subscription; cancellation MUST be effective at `current_period_end` (no mid-cycle refund) unless an admin issues a refund through existing payments tooling.
- **FR-012**: System MUST allow upgrading and downgrading between paid tiers; on upgrade, prorated billing is **out of scope** — the new cycle starts fresh and the old subscription ends. On downgrade, the change applies at `current_period_end`.

**Gating & policy**
- **FR-013**: Catalog's create/publish service flow MUST consult a `SubscriptionPolicy::canCreateService` gate before persisting the new service; failure MUST return a structured error naming the limit and the unblocking tier.
- **FR-014**: Catalog's Excel import flow MUST consult `SubscriptionPolicy::canImportExcel` and refuse the import for plans without that feature.
- **FR-015**: Discovery's "feature this service" action MUST consult `SubscriptionPolicy::canFeature`; the gate MUST also enforce a per-plan cap on simultaneously featured services per vendor.
- **FR-016**: All other tier-gated capabilities (max active services, max gallery images per service, vendor profile cover image, custom branding fields, analytics access) MUST be implemented as named gates against the same `SubscriptionPolicy` rather than scattered conditionals across modules.
- **FR-017**: A `FeatureResolver` MUST cache plan/feature lookups per request (and across requests with explicit invalidation on plan or subscription changes) so policy checks do not generate N+1 database load.

**Commission integration**
- **FR-018**: Settlement's commission rate resolver MUST add a fifth-level fallback keyed on `subscription_plan_id`, with priority strictly lower than the existing four levels.
- **FR-019**: When a booking item is created, the commission rate snapshotted onto `booking_items.commission_bps` MUST reflect the vendor's tier at that moment; later tier changes MUST NOT mutate that snapshot.

**Admin tools**
- **FR-020**: An admin endpoint MUST allow overriding a vendor's tier with a required reason and optional expiry; overrides MUST be recorded in the subscription lifecycle audit with actor, before/after states, and reason.
- **FR-021**: An admin list/detail UI MUST show all vendor subscriptions filterable by plan, status, and renewal date.

**Audit & data discipline**
- **FR-022**: System MUST record every lifecycle event (create, activate, renew, fail, past_due, expire, cancel, override applied, override ended, plan change) in an append-only `subscription_audit` table with timestamp, actor, before/after JSON, and reason.
- **FR-023**: `subscription_invoices`, `subscription_payments`, and `subscription_audit` MUST be append-only (no soft delete, no UPDATE except status fields where domain-correct). `subscription_plans`, `plan_features`, and `vendor_subscriptions` MAY be updated through Filament; their changes MUST emit audit rows.
- **FR-024**: All translatable plan content MUST be stored as JSON columns and MUST be present in both English and Arabic before a plan can be marked `published`.

**Domain events**
- **FR-025**: System MUST emit `SubscriptionActivated`, `SubscriptionRenewed`, `SubscriptionPastDue`, `SubscriptionExpired`, `SubscriptionCancelled`, and `SubscriptionTierChanged` domain events after the relevant DB transaction commits.
- **FR-026**: A listener for `OnPaymentCaptured` MUST detect subscription invoices (by invoice type) and trigger activation/renewal of the matching subscription.
- **FR-027**: A listener for `SubscriptionExpired` MUST trigger the auto-pause of excess services; pause MUST be reversible via the standard service publish/unpublish flow once the vendor upgrades again.
- **FR-028**: A listener for `VendorRegistered` MUST create the Free-tier subscription.

**API & docs**
- **FR-029**: API endpoints MUST conform to the standard `ApiResponse` envelope, include Scribe-compatible `@bodyParam` and `@response` PHPDoc with EN+AR examples, and be added to `.specify/memory/api-registry.md` and the Bruno/Postman collection in `docs/api/collections/`.
- **FR-030**: Subscription-mutating endpoints MUST require an `Idempotency-Key` header and MUST be 401/403-correct (vendor endpoints reject anonymous and other vendors; admin endpoints require the matching Shield permission).

### Key Entities

- **SubscriptionPlan** — One of the four sellable tiers. Holds plan code, translatable name/description, price (monthly + yearly minor units), billing cycle options, default flag, and a published flag.
- **PlanFeature** — A named capability/limit attached to a plan (e.g., `max_active_services = 5`, `can_feature = true`, `featured_cap = 3`, `can_import_excel = false`, `commission_discount_bps = 0`). Values are typed (int / bool / string).
- **VendorSubscription** — A vendor's link to a plan over a specific period. Tracks status (`active | past_due | cancelled | expired | superseded`), `current_period_start/end`, `cancel_at_period_end`, recurring token reference (opaque), `is_admin_override`, override expiry, and the `ended_at` reason.
- **SubscriptionInvoice** — A billing line tied to a subscription period. Status (`pending | paid | failed | refunded`), amount snapshot, currency, due date, paid date.
- **SubscriptionPayment** — Payment attempt(s) against an invoice, including gateway reference, status, and failure reason. Append-only.
- **SubscriptionAudit** — Append-only ledger of every lifecycle event with actor, event type, before/after snapshots, and reason.
- **CommissionRate (extended)** — Existing commission rate table gains an optional `subscription_plan_id` FK, used as the lowest-priority match level.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of newly approved vendors have an active Free-tier subscription within 5 seconds of approval; zero vendors in any environment are observed without an active subscription row.
- **SC-002**: A vendor can complete an upgrade (browse plans → pay → see new tier active) in under 3 minutes on a typical mobile connection.
- **SC-003**: 99% of auto-renewal attempts complete (success or terminal failure) within 24 hours of `current_period_end`; failed renewals enter the grace window without manual intervention.
- **SC-004**: 0 duplicate invoices or duplicate payment attempts created when the subscribe endpoint is replayed with the same idempotency key within 24 hours, verified by integration test.
- **SC-005**: Tier-gated actions (create service, feature service, Excel import) deny over-limit requests within 200 ms p95 and never silently allow them; verified by end-to-end tests at every tier boundary.
- **SC-006**: Commission calculations at every tier produce the expected rate per a fixture matrix covering all four tiers × three product types × two category override scenarios, with zero discrepancies.
- **SC-007**: After an enforced expiry, services exceeding the Free cap are paused (not deleted) and remain restorable on upgrade; verified by automated end-to-end test.
- **SC-008**: Every lifecycle event leaves exactly one `subscription_audit` row with non-empty `before`, `after`, and `reason`; verified by audit-completeness test.
- **SC-009**: Admin override changes take effect on the next gated action without requiring the vendor to log out or refresh, with the override recorded in audit.
- **SC-010**: All vendor and admin endpoints return EN and AR responses correctly per the `Accept-Language` header in 100% of test cases.

---

## Assumptions

- **Phase placement**: User has explicitly approved this as a Phase 1.7 extension despite the original PRD §5.2 / §11 listing it as Phase 2; if not, the spec needs a scope review before planning.
- **Currency**: Initial launch prices all four plans in EGP only. Multi-currency for subscriptions is out of scope; the `currency` column is present but constrained to `EGP` at app level for now.
- **Billing cycles**: Monthly and yearly only. Quarterly, lifetime, and custom cycles are out of scope.
- **Proration**: Mid-cycle proration on upgrades is out of scope. Upgrades end the current subscription on capture and start a new full cycle.
- **Refunds**: No automated refunds on cancel or downgrade. Existing Payments refund tooling (Phase 1.4) is the manual escape hatch.
- **Recurring tokens**: The existing `PaymentGateway` interface is reused; no new payment provider is added. Both renewal modes (unattended saved-token charge and vendor-initiated invoice-and-notify) are implemented and selected at runtime via the `subscriptions.recurring_tokens_enabled` feature flag, defaulted to match the adapter's current capability. The flag flips on the moment Paymob token support is verified, with no code change required.
- **Auto-pause policy**: When a vendor falls back to Free, services above the Free cap are paused oldest-first (by `created_at asc`). The vendor chooses which to re-publish on upgrade.
- **Admin override vs. paid subscription**: Override is a separate layer on top of the underlying subscription. The underlying subscription continues to bill and auto-renew normally. The override wins for all gating and commission lookups while active; on expiry the vendor reverts to whatever the underlying state is at that moment (paid or Free). Modelling implication: `vendor_subscriptions` carries an `is_admin_override` flag and the resolver picks the active override row in preference to a concurrent non-override row.
- **Notification channels**: Lifecycle notifications are delivered via the existing Communication module's templates and channels (email + in-app + push per existing prefs); no new channel is introduced.
- **Discovery "featured" mechanic**: A boolean `is_featured` flag (or equivalent existing feature) on a service plus a per-vendor cap is sufficient for Phase 1.7. A weighted ranking algorithm or paid promotion auction is out of scope.
- **Tax / VAT**: Subscription invoices reuse whatever tax-readiness foundation the Settlement module already exposes; full tax invoicing is Phase 2.
- **Soft delete**: `subscription_plans` may be soft-deleted (admins can retire a plan without breaking historical references). All other subscription tables follow the append-only / no-soft-delete rules in CLAUDE.md §15.
- **`subscription_payments` vs existing `payments` table**: A separate append-only `subscription_payments` table is introduced rather than reusing the booking-oriented `payments` table, to keep booking and subscription accounting cleanly separable. Cross-linking is via gateway reference.

---

## Open Questions Deferred to Planning

The three highest-impact ambiguities were resolved in the 2026-05-03 clarification session above. The following remain open and are deferred to `/speckit.plan` (default values to be proposed in `research.md`):

1. **Grace-period length** on failed renewals — proposed default: 7 days, configurable via `app_settings`.
2. **Concrete numeric limits per tier** — `max_active_services`, `max_gallery_images`, `featured_cap`, and `commission_discount_bps` for each of Free / Silver / Gold / Premium. To be specified in `data-model.md` with a default fixture.
