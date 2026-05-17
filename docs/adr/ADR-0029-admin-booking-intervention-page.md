# ADR-0029 — Admin Booking Intervention Page

- **Status:** Accepted
- **Date:** 2026-05-16
- **Decision-makers:** Ibrahim
- **Tags:** booking, admin, filament, intervention, phase-6.5
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md)
  - [`docs/adr/0003-identity-module.md`](0003-identity-module.md)
  - [`docs/adr/ADR-0028-financial-ledger-hardening.md`](ADR-0028-financial-ledger-hardening.md)
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §8 (Admin Journey)
  - [`docs/specs/08_Admin_Journey.md`](../specs/08_Admin_Journey.md)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §5 (Booking tables)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) Phase 6.5
  - [`specs/029-admin-booking-intervention/research.md`](../../specs/029-admin-booking-intervention/research.md)

---

## 1. السياق / Context

**EN:** When a booking enters a "troubled" state — vendor late to respond, all vendors rejected, customer review stalled, or any vendor timed out — the current admin toolset offers only Force-Cancel. Admins have no lightweight way to nudge vendors, formally record a vendor timeout, suggest candidate replacements to the customer, or add a triage note without closing the booking. This gap produces escalation via WhatsApp and manual DB edits, which are unaudited. Phase 6.5 introduces a dedicated `AdminBookingInterventionResource` Filament page that surfaces all troubled bookings and gates six surgical intervention actions behind granular Spatie Shield permissions. A hard boundary enforces that the admin may *suggest* alternative vendors but may **never** assign one — the customer remains the final selector.

**AR:** عندما تدخل الحجوزات في حالة "إشكالية" — تأخر استجابة المورد، رفض جميع الموردين، توقف مراجعة العميل — لا يمتلك المشرف سوى خيار إلغاء الحجز قسراً. هذه الفجوة تنتج تصعيداً عبر واتساب وتعديلات يدوية غير مدققة في قاعدة البيانات. Phase 6.5 يقدم صفحة Filament مخصصة تعرض جميع الحجوزات الإشكالية وتوفر ست إجراءات جراحية خلف صلاحيات Spatie Shield تفصيلية.

**Phase:** 6.5 (Admin Booking Override — extended)
**Built in:** Week 7 (post Phase 6 Communication baseline)

---

## 2. Core Decisions

### D-1: Six new Application Actions — no AssignReplacementVendor surface

The six Actions added:

| Action | Result |
|---|---|
| `SendVendorReminderAction` | Queues `booking.vendor.reminder` dispatch; throttled 5 min via `idempotency_keys` |
| `EscalateLateVendorResponseAction` | Flips `BookingVendor.sub_status → timed_out`; pessimistic row lock; writes `state_transitions` |
| `SuggestAlternativeVendorsAction` | Records a `vendor_proposal` intervention with `proposed_vendor_id = NULL`; no `booking_vendors` row created |
| `FreezeBookingChatAction` | Sets `chat_threads.frozen_at`; pushes Firestore mirror (gated on `chat_threads` table) |
| `ResumeBookingReviewAction` | Queues `booking.customer_review.reminder`; throttled 4 h |
| `CreateAdminInterventionNoteAction` | Persists free-text note; no dispatch; no state change |

**Forbidden surface**: No class, method, Filament action, or Spatie permission named `AssignReplacementVendor` (or substring variant) is ever registered. Enforced by architecture test `tests/Architecture/AdminCannotAssignReplacementVendorTest.php`.

### D-2: Throttle via `idempotency_keys` table (not Redis cache)

Rationale: `idempotency_keys` is already locked-in by Tech Decisions §12 for payment-mutating endpoints. Using the same mechanism for reminder throttling is consistent, survives Redis restarts, and avoids introducing a second throttle pattern. See `research.md` §R-2.

- Vendor reminder: scope `admin.intervention.vendor_reminder`, key = `booking_vendor_id`, TTL = 5 min
- Customer review reminder: scope `admin.intervention.customer_review_reminder`, key = `booking_id`, TTL = 4 h

### D-3: `BookingVendor` transitions recorded in polymorphic `state_transitions` table

`EscalateLateVendorResponseAction` writes a `state_transitions` row with `transitionable_type = App\Modules\Booking\Domain\Models\BookingVendor`, `trigger_kind = 'admin'`. This reuses the constitution's append-only transition log rather than adding a new audit column. See `research.md` §R-3.

### D-4: Cross-module contracts, not direct model imports

Two new contracts introduced:

- `AdminInboxWriter` in `Communication\Domain\Contracts` — binding in `CommunicationServiceProvider`
- `AlternativeVendorFinder` in `Discovery\Domain\Contracts` — binding in `DiscoveryServiceProvider`

No Booking Action imports a Communication or Discovery Eloquent Model directly. Enforced by `tests/Architecture/NoCrossModuleModelImportsTest.php`. See `research.md` §R-6.

### D-5: Granular Shield permissions (7 distinct abilities)

One permission per action, plus a master-gate `booking.intervene.access`. This allows future role splits (e.g., a "Level 1 Support" role that may send reminders but not escalate). A single omnibus `booking.intervene.*` was considered and rejected — too coarse for multi-tier admin roles. See `research.md` §R-9.

### D-6: `FreezeBookingChatAction` gated on `chat_threads` migration

The `chat_threads` base table does not yet exist at the time this ADR is accepted. US6 (freeze/resume chat) is placed on the cut-list (priority P2) and will ship in a follow-up PR once the Communication module creates the base table and adds `frozen_at` / `frozen_by` columns. All other six actions ship independently.

---

## 3. Alternatives Considered

| Option | Rejected because |
|---|---|
| Single omnibus `booking.intervene.*` permission | Too coarse — future role tiers need granular splits |
| Redis cache lock for throttle | Not durable across Redis restarts; inconsistent with existing `idempotency_keys` pattern |
| Inline vendor candidate query inside Filament action | Cannot be unit-tested in isolation; violates contract boundary between Discovery and Booking |
| `AssignReplacementVendor` action with customer confirmation UI | PRD §6.2 FR-EXT-010: admin is not a marketplace actor; customer agency must be preserved |

---

## 4. Thresholds (Internal Decisions)

| Threshold | Value | Config key |
|---|---|---|
| Stalled booking detection | 48 h with no vendor action | `booking.intervention.stalled_threshold_hours` |
| Vendor reminder cooldown | 5 min | `booking.intervention.vendor_reminder_cooldown_minutes` |
| Customer review reminder cooldown | 4 h | `booking.intervention.customer_review_reminder_cooldown_hours` |
| Escalation grace period | 0 min (admin overrides immediately) | `booking.intervention.deadline_grace_period_minutes` |
| Max candidate suggestions | 5 | `booking.intervention.suggest_max_candidates` |

All configurable via `config/booking.php` — no hardcoded magic numbers in Action classes.

---

## 5. Consequences

**Positive:**
- Admins have a single triage tool that replaces WhatsApp-based escalation.
- Every admin action is audited in `audit_logs` and `booking_admin_interventions`.
- Hard boundary is enforced at Policy + architecture-test level — cannot regress silently.
- All throttling is idempotent and durable.

**Negative / Trade-offs:**
- US6 (chat freeze) adds a blocking dependency on the `chat_threads` table — must track as a follow-up.
- Seven new Spatie permissions must be seeded before the admin role can use the page.
- `AlternativeVendorFinder` adds a Discovery → Booking dependency contract (bounded by interface, not model).
