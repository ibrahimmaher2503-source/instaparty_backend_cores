# ADR-0005 — Payments Module

- **Status:** Accepted
- **Date:** 2026-05-02
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-4-payments
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/adr/0003-identity-module.md`](0003-identity-module.md) — owns `users`, source of `payments.user_id`, `refunds.initiated_by`, `idempotency_keys.user_id`
  - [`docs/adr/0004-catalog-module.md`](0004-catalog-module.md) — owns `service_digital_details.is_refundable_after_delivery` (read by `RefundPolicyService` for digital policy)
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) FR-30 (settlement / payment baseline)
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §11 (per-type refund policies, Paymob locked as Phase 1 gateway)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §6 (Payments — 5 tables)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) Phases 4.0 + 4.1
  - [`specs/007-payments-paymob-refunds/spec.md`](../../specs/007-payments-paymob-refunds/spec.md) — feature spec gating this ADR

---

## 1. السياق / Context

**AR:** المنصة محتاجة طريقة موثوقة عشان العميل يدفع تمن الحجز المؤكد، وعشان الـ admin يقدر يرد الفلوس بسياسات مختلفة لكل نوع منتج. Phase 1 معتمد على Paymob كـ gateway وحيد، لكن المعمارية لازم تستحمل إضافة Tabby/Tamara في Phase 2 من غير ما نلمس الـ Actions أو الـ Booking module. الـ idempotency middleware والـ HMAC webhook hardening لازم يكونوا foundation infrastructure من اليوم الأول، لأن أي bug فيهم يساوي double-charge أو missed payment.

**EN:** The platform needs a reliable way for a customer to pay for a confirmed booking and for admins to issue refunds under three different per-type policies. Phase 1 is locked to Paymob as the only gateway, but the architecture must absorb Tabby/Tamara in Phase 2 without touching Action classes or the Booking module. Idempotency middleware and HMAC webhook hardening must be foundational from day 1 because any bug in those layers translates to double-charges or silently missed payments. This module also publishes the events Settlement (Phase 4.2) needs in order to write `wallet_ledger` rows.

**Phase:** 4.0 (Paymob Gateway, 2 days) + 4.1 (Refunds per-type, 1 day)
**Built in:** Week 5, Days 22–24

---

## 2. المسؤوليات / Responsibilities

### الـ module ده مسؤول عن / This module owns:

- Payment intent creation against Paymob (`InitiatePaymentAction`) returning the hosted-checkout redirect URL
- Outbound gateway call logging in `payment_attempts` (append-only forensic record)
- Webhook receipt at `POST /api/v1/webhooks/paymob` with HMAC-SHA512 signature verification and append-only audit in `gateway_webhook_logs`
- Payment state machine transitions (`pending → captured | failed`, with downstream `captured → refunded | partially_refunded | voided`)
- The `PaymentGateway` public interface (`Domain/Contracts/PaymentGateway`) — the seam for swapping or adding gateways
- Refund initiation by admin (`InitiateRefundAction`), per-type policy enforcement via `RefundPolicyService::policyFor(ProductType)`, and gateway-side refund execution (`ProcessRefundAction`)
- Idempotency-key middleware (`Http/Middleware/IdempotencyKeyMiddleware`) backed by `idempotency_keys` (24h TTL); reused by Settlement (Phase 4.2) for withdrawals
- Sweep job that ages out abandoned `pending` payments (`failure_code = 'expired_payment_hold'`) once the booking's 24h payment hold expires
- Domain events broadcast to other modules: `PaymentInitiated`, `PaymentCaptured`, `PaymentFailed`, `RefundCompleted`, `RefundFailed`

### الـ module ده مش مسؤول عن / This module does NOT own:

- Booking lifecycle / `bookings.payment_status` mutations → owned by Booking module; updated via a queued listener on `PaymentCaptured`
- `wallet_ledger` entries, commission accrual, withdrawals → owned by Settlement (Phase 4.2), which subscribes to `PaymentCaptured` + `RefundCompleted`
- Customer-facing receipt / payment-confirmation notifications → owned by Communication (Phase 5.0)
- Per-type fulfillment state machines (rental `setup`, sale `in_preparation`, digital `delivered`) → owned by Booking; this module reads them through a contract to resolve refund eligibility
- Card-data acceptance / tokenization / PCI-scope escalation — explicitly forbidden (see §6.1)
- Customer-initiated cancel-and-refund flows → deferred to a later phase per spec clarification (admin-only refunds in Phase 1)

> **القاعدة:** لو حسيت إنك بتضيف مسؤولية للـ module مش في القائمة فوق، اوقف. اكتب ADR منفصل أو وسع نطاق الـ module بـ ADR amendment.

---

## 3. الجداول المملوكة / Tables Owned

من [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §6:

| Table | الغرض / Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `payments` | Single payment intent against a booking; state machine carries `pending → captured / failed → refunded / partially_refunded / voided` | No | Yes (status + `failure_message` updates only) |
| `payment_attempts` | Forensic record of every outbound gateway call (request, response, http_status, attempt_no) | No | Yes (fully immutable) |
| `refunds` | Reversal of a captured payment, per-type policy-gated, full-amount only in Phase 1 | No | No (status updates allowed) |
| `idempotency_keys` | Short-lived cache of `(key, user_id, request_hash) → response` with 24h TTL on payment-mutating endpoints | No | No (purged daily) |
| `gateway_webhook_logs` | Append-only audit of every webhook delivery (signed or not) for non-repudiation when Paymob disputes | No | Yes (fully immutable) |

**Foreign key dependencies (jadāwil mawjuda fi modules tania):**

| FK | المرجع / References | Module |
|---|---|---|
| `payments.booking_id` | `bookings.id` | Booking |
| `payments.user_id` | `users.id` | Identity |
| `refunds.payment_id` | `payments.id` | Payments (own) |
| `refunds.booking_id` | `bookings.id` | Booking |
| `refunds.initiated_by` | `users.id` | Identity |
| `payment_attempts.payment_id` | `payments.id` | Payments (own) |
| `idempotency_keys.user_id` | `users.id` | Identity |

**Read-only references resolved through Contracts (no FK, no model import):**

| Reference | Source Module | Contract used |
|---|---|---|
| `bookings.lifecycle_status`, `bookings.payment_status`, `bookings.total_minor`, `booking_items.product_type`, `booking_items.event_starts_at`, `booking_items.item_status` | Booking (implementer) | `Payments\Domain\Contracts\PaymentsBookingReader` (Payments owns; Booking implements) |
| `service_digital_details.is_refundable_after_delivery` | Catalog (implementer) | `Payments\Domain\Contracts\PaymentsCatalogReader` (Payments owns; Catalog implements) |

> **Migration order rule:** Identity (Phase 1.0) and Booking (Phase 3.1) must ship before Payments migrations run, because `payments.user_id` / `payments.booking_id` / `refunds.booking_id` reference their tables. Migration order: `payments` → `payment_attempts` → `refunds` → `idempotency_keys` → `gateway_webhook_logs` (idempotency_keys and gateway_webhook_logs are independent and could swap).

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

### ☑ Cross-type (مش type-aware)

Payments is cross-cutting infrastructure: one polymorphic payment record per booking regardless of the booking's product mix. The schema has zero per-type tables — `payments`, `refunds`, `payment_attempts`, `idempotency_keys`, `gateway_webhook_logs` are all single shape.

**However, refund eligibility IS per-type.** The module honours the rule via a single `RefundPolicyService::policyFor(ProductType)` method that uses `match($enum)` to dispatch to per-type policy logic — this is the canonical "cross-type code with per-type branching" pattern from `docs/specs/03_Three_Product_Types.md` §3. There are NO per-type Form Requests, Actions, or Filament Resources in this module — only `match` over the enum at the policy boundary.

The module's Pest suite uses three test groups (`->group('rental')`, `->group('sale')`, `->group('digital')`) for the refund policy tests, and a generic `->group('payments')` group for the Paymob-side flows.

**Type-aware module list (for reference, this module is NOT in it):** Catalog, Booking (booking_items), Imports, Reviews (per-item), Discovery (search facets).

---

## 5. Layer Layout

```
app/Modules/Payments/
├── Domain/
│   ├── Models/
│   │   ├── Payment.php                       # status, gateway, gateway_ref, money cast
│   │   ├── PaymentAttempt.php                # append-only, no updated_at
│   │   ├── Refund.php                        # status updates allowed
│   │   ├── IdempotencyKey.php                # short-lived cache row
│   │   └── GatewayWebhookLog.php             # append-only, no updated_at
│   ├── Enums/
│   │   ├── PaymentStatus.php                 # pending|authorized|captured|failed|refunded|partially_refunded|voided
│   │   ├── PaymentMethod.php                 # card|wallet|installment|cash_on_delivery|transfer
│   │   ├── RefundStatus.php                  # pending|processing|completed|failed
│   │   └── RefundReasonCode.php              # 5 fixed values (see §6.4)
│   ├── Events/
│   │   ├── PaymentInitiated.php
│   │   ├── PaymentCaptured.php
│   │   ├── PaymentFailed.php
│   │   ├── RefundCompleted.php
│   │   └── RefundFailed.php
│   ├── ValueObjects/
│   │   └── RefundPolicy.php                  # immutable (allowed: bool, reason_code, reason_message)
│   └── Contracts/
│       └── PaymentGateway.php                # public interface — Tabby/Tamara slot
├── Application/
│   ├── Actions/
│   │   ├── InitiatePaymentAction.php         # creates payments + payment_attempts row, calls gateway
│   │   ├── CapturePaymentAction.php          # invoked by webhook handler after signature verify
│   │   ├── ProcessPaymobWebhookAction.php    # signature verify + dispatch to capture/fail
│   │   ├── InitiateRefundAction.php          # admin entry point; runs RefundPolicyService
│   │   ├── ProcessRefundAction.php           # gateway call + state transitions
│   │   └── ExpirePendingPaymentsAction.php   # sweep job invoked by scheduler
│   ├── Services/
│   │   └── RefundPolicyService.php           # match(ProductType) → RefundPolicy VO
│   ├── DTOs/
│   │   ├── InitiatePaymentDto.php
│   │   ├── PaymobWebhookDto.php
│   │   └── InitiateRefundDto.php
│   └── Listeners/
│       └── HandleRefundCompletedListener.php # placeholder; Phase 4.2 will subscribe Settlement
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentPaymentRepository.php
│   │   ├── EloquentRefundRepository.php
│   │   └── EloquentIdempotencyKeyRepository.php
│   └── Gateways/
│       └── PaymobGateway.php                 # implements PaymentGateway; HMAC-SHA512 verify
├── Http/
│   ├── Controllers/
│   │   ├── Customer/InitiatePaymentController.php
│   │   ├── Customer/ShowPaymentController.php
│   │   ├── Webhook/PaymobWebhookController.php
│   │   ├── Admin/InitiateRefundController.php
│   │   └── Admin/ShowRefundController.php
│   ├── Requests/
│   │   ├── InitiatePaymentRequest.php
│   │   ├── PaymobWebhookRequest.php
│   │   └── InitiateRefundRequest.php
│   ├── Resources/
│   │   ├── PaymentResource.php               # locale conversion at this layer
│   │   └── RefundResource.php
│   └── Middleware/
│       └── IdempotencyKeyMiddleware.php      # reused by Settlement (Phase 4.2)
├── Filament/
│   └── Resources/
│       └── RefundResource.php                # admin read-only audit view
├── Routes/
│   ├── customer.php                           # POST /payments, GET /payments/{ulid}
│   ├── admin.php                              # POST /bookings/{ulid}/refunds, GET /refunds/{ulid}
│   └── webhook.php                            # POST /webhooks/paymob (no auth, HMAC)
├── Database/
│   ├── Migrations/
│   │   ├── {ts}_create_payments_table.php
│   │   ├── {ts}_create_payment_attempts_table.php
│   │   ├── {ts}_create_refunds_table.php
│   │   ├── {ts}_create_idempotency_keys_table.php
│   │   └── {ts}_create_gateway_webhook_logs_table.php
│   ├── Factories/
│   └── Seeders/
├── Resources/
│   └── lang/
│       ├── en/
│       │   ├── payments.php
│       │   └── refunds.php
│       └── ar/
│           ├── payments.php
│           └── refunds.php
└── Providers/
    └── PaymentsServiceProvider.php           # binds PaymentGateway → PaymobGateway, registers middleware, listeners, routes
```

> The custom Filament Action that puts a "Refund" button on the Booking row lives in **Booking/Filament/Resources/BookingResource.php** (which is in the Booking module), and its closure dispatches `InitiateRefundAction` from this module via the service container. Payments' own Filament resource (`RefundResource`) is read-only audit only.

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 PCI scope — zero card data on InstaParty servers (PCI-DSS SAQ-A)

**القرار:** الـ module ده **ما بيقبلش، ما بينقلش، ما بيخزنش** أي بيانات كروت (PAN, CVV, expiry) في أي وقت. الدخول الوحيد للعميل هو Paymob hosted iframe / redirect. الـ webhook payload و الـ `payment_attempts.request_payload` بيمروا على redaction allowlist قبل ما يتخزنوا.

**البديل:** قبول raw card data في API بتاعنا و forwarding لـ Paymob server-to-server (PCI-DSS SAQ-D scope).

**ليه؟:** SAQ-A هو أخف tier compliance — مفيش PCI penetration test سنوي، مفيش quarterly ASV scan، مفيش 12-control framework نطبقه. SAQ-D لو دخلناه هيضيف 6-12 شهر work على Phase 1. والعميل مش بيحس بفرق — الـ iframe redirect تجربة سلسة. Architecture test (`tests/Architecture/PaymentLoggingRedactionTest.php`) بيمنع أي merge بيدخل field غير مسموح في الـ logging allowlist.

**Allowlist of safe-to-log fields:** `order_id`, `amount_cents`, `currency`, `merchant_order_id`, `integration_id`, `payment_key_token`, `transaction_id`, `success`, `error_occured`, plus Paymob's documented non-sensitive fields. Anything else is hard-filtered before insert.

### 6.2 HMAC-SHA512 only for webhook auth — no IP allowlist

**القرار:** الـ Paymob webhook بيتأمن عن طريق HMAC-SHA512 signature بس، مع `hash_equals` constant-time comparison. مفيش IP allowlist.

**البديل:** HMAC + IP allowlist driven by `config/paymob.php` (`webhook_allowed_cidrs`).

**ليه؟:** Paymob ممكن يبدل IPs بدون إشعار → IP allowlist هيبوظ webhook delivery silently في production. الـ HMAC شغل properly verified بيوفر authentication و integrity في نفس الوقت — ما فيش هجوم realistic بيتجنب HMAC مع shared secret سليم. أي يجي tradeoff: ops risk (silent breakage) > marginal security gain. القرار موثق هنا عشان security review trail يبقى نظيف.

### 6.3 New `payments` row per initiate call — no row reuse

**القرار:** كل initiate-payment call (مش retry على نفس الـ idempotency-key) بيخلق `payments` row جديد بـ `gateway_ref` فريد. الـ `pending` rows اللي العميل سابها بتتسحب بعد expiry بـ sweep job (`failure_code = 'expired_payment_hold'`).

**البديل:** إعادة استخدام نفس الـ `payments` row — UPDATE الـ `gateway_ref` و reset لـ `pending`.

**ليه؟:** الـ `(gateway, gateway_ref)` UNIQUE في schema — لو كل retry بيعدل نفس الـ row، الـ history بتاع المحاولات الفاشلة بيضيع. مع approach الجديد، كل محاولة مسجلة بـ row خاص بها للـ forensics، والـ idempotency middleware بيمنع double-charge على نفس الـ key داخل الـ 24h window. السحب بيتم عن طريق `ExpirePendingPaymentsAction` كل 15 دقيقة — مفيش rows ضايعة.

### 6.4 Refund `reason_code` is a fixed enum, not free-form

**القرار:** `RefundReasonCode` enum بـ 5 قيم بس: `customer_request`, `vendor_cancellation`, `service_unavailable`, `duplicate_charge`, `admin_discretion`. الـ Filament admin action بيستخدم `Select` بـ القيم دي. الـ `InitiateRefundRequest` بيـ validate عن طريق `Rule::in([...])`. الـ free-text context بيتحط في `reason_notes` (translatable JSON, EN+AR).

**البديل:** `VARCHAR(80)` free-form — admin بيكتب أي حاجة.

**ليه؟:** Reporting Phase 6.1 محتاج يجمع refunds by category (e.g., "كم refund بسبب vendor cancellation الشهر ده؟"). Free-form reason_code بيخلي ده مستحيل من غير NLP cleanup. الـ enum 5-value بيغطي 99% من الـ scenarios عملياً (شكل Stripe + Paymob standard taxonomy)، والـ `admin_discretion` بيستوعب الباقي + الـ `reason_notes` بيلتقط التفاصيل.

### 6.5 Per-type refund policy via `match($enum)` value object — single Service, single dispatch

**القرار:** `RefundPolicyService::policyFor(ProductType): RefundPolicy` هو الـ entry point الوحيد. الـ method بيستخدم `match($type)` و بيرجع `RefundPolicy` value object (`allowed`, `reason_code`, `reason_message`). أي حد عاوز refund logic بيستدعي الـ service ده — مفيش inline checks في Actions أو Resources.

**البديل:** ثلاث Action classes منفصلة (`InitiateRentalRefundAction`, `InitiateSaleRefundAction`, `InitiateDigitalRefundAction`) per the `{Verb}{Type}{Noun}Action` pattern.

**ليه؟:** Refund policy logic per-type لكن مفيش data per-type في الـ `refunds` table — كله shared. Three Action classes هيضيف triplication بدون شيء يميز كل واحد عن التاني (نفس inputs, نفس outputs, نفس state machine). الـ `match` pattern مع value object بيخلي الـ policy resolution single-source-of-truth و testable in isolation. Per-type Pest groups (`->group('rental')` etc.) بيتأكدوا إن كل branch من الـ match مغطى. هذا استثناء واضح من قاعدة `{Verb}{Type}{Noun}Action` لأن الـ data shape موحدة.

### 6.6 Custom Paymob adapter — no SDK

**القرار:** `PaymobGateway` adapter مكتوب من الصفر بـ `Http::client()` Laravel + `hash_hmac('sha512', ...)`. مفيش library خارجي.

**البديل:** library تالت طرف (community-maintained Paymob SDK).

**ليه؟:** Paymob مفيش له official PHP SDK. الـ community SDKs قليلة الـ maintainers و version-locked على Laravel 9/10. كتابة adapter بسيط (~150 سطر) بيحت deps و بيخلينا في تحكم كامل في الـ HTTP retry logic و الـ logging redaction. الـ `PaymentGateway` interface بيخلي swap لـ Tabby/Tamara في Phase 2 شغل local.

### 6.7 Idempotency middleware lives in Payments module but is reused

**القرار:** `IdempotencyKeyMiddleware` و `idempotency_keys` table بيتولدوا هنا، لكن middleware بيتسجل globally في `bootstrap/app.php` و بيتطبق على Settlement endpoints (Phase 4.2 — withdrawals) و Booking submit (Phase 3.2) كمان عن طريق attribute group.

**البديل:** نقل الـ middleware و الـ table لـ Shared module.

**ليه؟:** Idempotency بدأت كـ payment-specific need. Shared module مفروض يحتوي على cross-cutting infrastructure ما له بيت طبيعي — لكن الـ idempotency_keys schema و الـ middleware semantics مرتبطين بـ money flows أكتر من حاجة تانية. لو احتجناها في 3+ modules تانية في Phase 2، نعمل ADR للنقل وقتها. Premature generalization = pain.

---

## 7. Inter-Module Communication

### Events بنطلقها / Events we publish:

| Event | When | Payload |
|---|---|---|
| `Payments\Domain\Events\PaymentInitiated` | After `payments` row created and gateway redirect URL obtained | `payment_id`, `booking_id`, `user_id`, `amount_minor`, `amount_currency` |
| `Payments\Domain\Events\PaymentCaptured` | After webhook with valid HMAC and `success=true`, after commit | `payment_id`, `booking_id`, `amount_minor`, `amount_currency`, `captured_at` |
| `Payments\Domain\Events\PaymentFailed` | After webhook with valid HMAC and `success=false`, OR sweep job marks expired | `payment_id`, `booking_id`, `failure_code`, `failure_message` (translatable) |
| `Payments\Domain\Events\RefundCompleted` | After gateway confirms refund, after commit | `refund_id`, `payment_id`, `booking_id`, `amount_minor`, `amount_currency`, `reason_code` |
| `Payments\Domain\Events\RefundFailed` | After gateway rejects refund | `refund_id`, `payment_id`, `failure_message` (translatable) |

### Events بنستهلكها / Events we consume:

| Event | Source Module | Action |
|---|---|---|
| _None in Phase 4.0 / 4.1_ | — | Phase 4.2+ may add `BookingCancelled` consumption to auto-trigger refunds; out of scope here |

### Public Contracts (interfaces بنوفرها):

| Contract | الغرض / Purpose | Implementation |
|---|---|---|
| `Payments\Domain\Contracts\PaymentGateway` | Multi-gateway seam — `initiate(InitiatePaymentDto): PaymentIntentDto`, `verifyWebhookSignature(payload, signature): bool`, `parseWebhook(payload): PaymobWebhookDto`, `refund(payment, amount): RefundResultDto` | `Infrastructure\Gateways\PaymobGateway` (Phase 1); Tabby / Tamara adapters (Phase 2) |

### Public Contracts (interfaces بنستخدمها من غيرنا):

| Contract | From Module | Why we need it |
|---|---|---|
| `Payments\Domain\Contracts\PaymentsBookingReader` | Booking (implementer) | Read booking + booking_items as DTOs (`PaymentBookingReadDto`, `PaymentBookingItemReadDto`) for ownership / lifecycle / refund-policy resolution — NEVER importing Booking models. Same consumer-owned-contract pattern as Booking's `CatalogServiceReader`. |
| `Payments\Domain\Contracts\PaymentsCatalogReader` | Catalog (implementer) | Read `service_digital_details.is_refundable_after_delivery` flag for digital refund policy resolution |

> **القاعدة:** لو احتجت تقرأ Eloquent model من module تاني → ده signal تطلب Contract منه، مش `use App\Modules\OtherModule\Domain\Models\X`. Architecture test in §9 enforces.

---

## 8. Filament Footprint

| Resource | الغرض / Purpose | Translatable? |
|---|---|---|
| `Payments/Filament/Resources/RefundResource.php` | Read-only admin audit view of all refunds (filterable by status, reason_code, vendor) | No (`reason_notes` displayed in active locale via accessor) |

The "Refund" button on a Booking row is a **custom Filament Action** that lives in `Booking/Filament/Resources/BookingResource.php` (Booking module), not here. Its closure dispatches `InitiateRefundAction::execute()` from this module. Payments owns no edit/create Filament UI for `payments`, `payment_attempts`, `idempotency_keys`, or `gateway_webhook_logs` — those are all admin read-only via `RefundResource`'s sibling read-only pages or, for now, direct DB access for ops.

**Navigation group:** `Payments`

**Permissions generated by Shield:**
- `view_any_refund`, `view_refund` (read-only — no create/update/delete on refunds via Filament; refunds are created only through the Booking action)
- Custom: `payment.refund` (gates the refund button on `BookingResource` — admins must be granted this explicitly)

> **Reminder:** بعد إنشاء Resources الجديدة، شغل `php artisan shield:generate --all`.

---

## 9. Testing Strategy

**Pest groups:** `payments` (gateway / webhook / idempotency flows) + `rental`, `sale`, `digital` (per-type refund policy tests).

**Test files location:**
```
tests/
├── Feature/Modules/Payments/
│   ├── InitiatePaymentTest.php
│   ├── PaymobWebhookTest.php                 # signature valid + invalid + replay
│   ├── IdempotencyMiddlewareTest.php         # concurrent same-key requests
│   ├── ExpirePendingPaymentsJobTest.php
│   ├── InitiateRefundRentalTest.php          # ->group('rental') — >24h, =24h, <24h, setup
│   ├── InitiateRefundSaleTest.php            # ->group('sale') — pre/post in_preparation
│   └── InitiateRefundDigitalTest.php         # ->group('digital') — flag true/false × delivered
├── Unit/Modules/Payments/
│   ├── RefundPolicyServiceTest.php           # all 3 type branches
│   ├── PaymobGatewaySignatureTest.php        # HMAC-SHA512 verification
│   └── PaymentLoggingRedactionTest.php       # PCI allowlist enforcement
└── Architecture/
    ├── PaymentsModuleNoCrossImportTest.php   # no Booking/Catalog model imports
    └── PaymentLoggingRedactionTest.php       # arch test from §6.1
```

**التغطية المطلوبة / Required coverage:**

- [ ] Happy path (initiate → webhook → captured → booking.payment_status = paid)
- [ ] Auth (unauthenticated initiate → 401)
- [ ] Authorization (customer A initiating on customer B's booking → 403; admin without `payment.refund` → 403)
- [ ] Validation (`InitiateRefundRequest` rejects invalid `reason_code` → 422; rejects partial amount → 422)
- [ ] Idempotency (10 concurrent requests, same key → 1 payment row, 1 gateway call, 9 replays)
- [ ] Locale (response `error.message` returned in EN and AR for refund-policy violations)
- [ ] **All three product types** for refund policy — non-negotiable per Constitution VII
- [ ] Architecture test: no imports from Booking/Catalog/Identity Eloquent models
- [ ] Architecture test: PCI allowlist enforced on `payment_attempts` + `gateway_webhook_logs` writes
- [ ] Architecture test: no `if/elseif` on product type strings in `RefundPolicyService` (`match` only)

**Architecture test pattern:**

```php
test('does not import other module models')
    ->expect('App\Modules\Payments')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Identity\Domain\Models',
    ]);
```

**Coverage targets (per Constitution VII):**
- ≥ 80% on Action classes (`InitiatePaymentAction`, `CapturePaymentAction`, `ProcessPaymobWebhookAction`, `InitiateRefundAction`, `ProcessRefundAction`, `ExpirePendingPaymentsAction`)
- ≥ 80% on `RefundPolicyService` and `PaymobGateway`
- ≥ 80% on `IdempotencyKeyMiddleware`

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) Phases 4.0 + 4.1:

- [ ] **Split payments** (multiple `payments` rows per booking) → defer to Phase 2; enforce 1-payment invariant in `InitiatePaymentAction` for Phase 1
- [ ] **GCC adapters** (Tabby, Tamara, HyperPay) → Phase 2 — interface stays open, no work in Phase 1
- [ ] **Partial refunds** → Phase 1.5 — full refund only; reject partial amounts with 422
- [ ] **Automatic gateway-failure retry job** for failed refunds → Phase 1.5 — admin manual retry only in Phase 1
- [ ] **Filament `RefundResource` UI polish** (advanced filters, export) → Phase 1.5 — basic table + view page only in Phase 1
- [ ] **Customer self-serve refund / cancel-and-refund** → later phase per spec clarification — admin-only in Phase 1

If 3-day budget slips, defer in this order: (1) automatic retry, (2) `RefundResource` polish, (3) digital `is_refundable_after_delivery` precision (fall back to "all digital refunds blocked after delivery" until Phase 1.5).

---

## 11. Open Questions

> **لو في حاجة لسه ما اتقررتش، اكتبها هنا.** مفيش قرار يعتمد لو في open questions.

- [ ] **Observability targets** — what specific metrics + alerts ship in Phase 4.0? (e.g., `payments_initiated_total`, `payments_captured_total`, `webhook_signature_failures_total`, alert on >5 invalid signatures / 5 minutes). Deferred to plan-level decision in `/speckit.plan`.
- [ ] **Webhook QPS / payment throughput target** — no production volume baseline yet. Default queue worker concurrency (per Tech Decisions §1) is sufficient for Phase 1 sandbox + early production; revisit when first production payment volume metrics arrive.
- [ ] **Multi-currency activation** — schema is currency-aware (`amount_currency CHAR(3)`), but Phase 1 is EGP-only. The runtime rejects any non-EGP `amount_currency`. When multi-currency activates (Phase 2 per Constitution §"Phase 1 Forbidden Features"), we'll need an FX-rate snapshot column on `payments` and a separate ADR.

> **لما يتحلوا، حول كل واحد لـ "Internal Decision" في القسم 6 أو لـ ADR منفصل.**

---

## 12. Implementation Checklist

عند تنفيذ الـ module:

- [ ] Migrations في `Payments/Database/Migrations/` بالترتيب: `payments` → `payment_attempts` → `refunds` → `idempotency_keys` → `gateway_webhook_logs`
- [ ] Models تحت `Domain/Models/` (relationships, casts, scopes ONLY — مفيش business logic). `Payment.amount` cast via `MoneyCast`. `PaymentAttempt` and `GatewayWebhookLog` have no `updated_at`.
- [ ] `PaymentGateway` Contract في `Domain/Contracts/`, `PaymobGateway` implementation في `Infrastructure/Gateways/`. `PaymentsServiceProvider` binds the interface.
- [ ] `IdempotencyKeyMiddleware` registered globally + applied to `POST /api/v1/customer/bookings/{ulid}/payments`, `POST /api/v1/admin/bookings/{ulid}/refunds` via route `->middleware('idempotency')`.
- [ ] API Resources مع locale conversion (`PaymentResource`, `RefundResource`) — `failure_message`, `reason_notes` translated at Resource layer.
- [ ] `RefundResource` Filament Resource (read-only) + `payment.refund` permission seeded.
- [ ] `ExpirePendingPaymentsAction` registered in `app/Console/Kernel.php` schedule, every 15 minutes.
- [ ] Service Provider مسجل في `bootstrap/providers.php`.
- [ ] Pest tests كاملة per requirement فوق, including 3 per-type refund test files with explicit groups.
- [ ] `php artisan shield:generate --all` after `RefundResource` is added.
- [ ] Translations في `Resources/lang/en/payments.php`, `Resources/lang/en/refunds.php` and AR mirrors.
- [ ] Architecture tests passing: no cross-module model imports, PCI redaction allowlist enforced, no `if/elseif` on `product_type` strings in policy code.
- [ ] هذا الـ ADR محدّث في القائمة الرئيسية في [`docs/adr/README.md`](README.md) و الـ `Status` بتاعه `Accepted`.
- [ ] `.specify/memory/api-registry.md` updated with all 5 endpoints documented.
- [ ] `docs/api/collections/` Bruno/Postman collection includes payment + refund endpoints with sample request bodies.
