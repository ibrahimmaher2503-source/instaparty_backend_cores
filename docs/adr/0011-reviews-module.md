# ADR-0011 — Reviews Module

- **Status:** Accepted
- **Date:** 2026-05-03
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-5-reviews
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/adr/0004-catalog-module.md`](0004-catalog-module.md) — Catalog provides `services` (target of `service_reviews`)
  - [`docs/adr/0010-communication-module.md`](0010-communication-module.md) — Communication consumes `ReviewSubmitted` to notify admins
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §6.3 step 7 (admin moderates reviews), §7.6 (post-fulfillment review feedback loop is in scope)
  - [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) §Phase 5.1
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §10 Reviews Module (4 tables)
  - [`docs/specs/03_Three_Product_Types.md`](../specs/03_Three_Product_Types.md) — reviews carry product-type context via `booking_item.product_type`

---

## 1. السياق / Context

**AR:** المنصة محتاجة آلية لجمع تقييمات العملاء بعد إتمام الحجوزات: تقييم لكل خدمة (service review) وتقييم منفصل للمورد ككل (vendor review)، مع مراجعة إدارية قبل النشر علشان نمنع المحتوى المسيء أو المضلل. الـ ratings المعتمدة بتأثر على متوسط تقييم الخدمة والمورد اللي بيظهر في صفحات الاستكشاف وخلاصة الحجز.

**EN:** The platform needs a structured way to collect customer feedback after a booking is fulfilled: a per-service review (linked to a `booking_item`) and a separate per-vendor review (linked to a `booking_vendor`), each gated by admin moderation before publication. Approved ratings drive the average rating displayed on service detail pages, vendor profiles, and discovery results, closing the loop on the customer journey defined in `06_Customer_Journey.md`.

**Phase:** Phase 5.1 — Reviews
**Built in:** Week 6, Day 1

---

## 2. المسؤوليات / Responsibilities

### Owns:

- Customer-submitted **service reviews** (one per `booking_item`) with rating (1–5), free-text body, and detected/declared locale.
- Customer-submitted **vendor reviews** (one per `booking_vendor`) with the same shape.
- **Admin moderation lifecycle** (`pending → approved | rejected | hidden`) for both review types, with append-only audit trail in `review_moderation_log`.
- **Eligibility check** — only allow review submission when the underlying `booking_item` (or all items of a `booking_vendor`) is in `completed` status.
- **Uniqueness enforcement** — exactly one review per `booking_item_id` and one per `booking_vendor_id` (DB-level UNIQUE).
- **Rating aggregation triggers** — fires `ReviewApproved` event on moderation transition; downstream listeners recompute service/vendor average ratings.
- **Vendor responses to reviews** — schema is created (`review_responses`) but the response submission flow defers to Phase 6.0 (per cut-list).

### Does NOT own:

- `services.average_rating` / `vendor_profiles.average_rating` columns themselves — owned by Catalog and Identity respectively (this module fires events; they listen).
- `booking_item.item_status='completed'` — owned by Booking module (this module reads it via a `BookingItemReader` contract).
- Notification delivery to admins/customers/vendors when a review event fires — owned by Communication module (Phase 5.0).
- Search-index updates after rating changes — owned by Catalog/Discovery (listener on `ServiceRatingRecomputed`).
- Loyalty points awarded for leaving a review — Phase 5.2 / Phase 6.0 if scoped in.

> **القاعدة:** لو حسيت إنك بتضيف مسؤولية للـ module مش في القائمة فوق، اوقف. اكتب ADR منفصل أو وسع نطاق الـ module بـ ADR amendment.

---

## 3. الجداول المملوكة / Tables Owned

من [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §10:

| Table | Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `service_reviews` | One per `booking_item`; rating 1–5 + body + moderation status | Yes | No |
| `vendor_reviews` | One per `booking_vendor`; rating 1–5 + body + moderation status | Yes | No |
| `review_responses` | Vendor's reply to a service or vendor review (polymorphic by `review_type`) | No | No |
| `review_moderation_log` | Append-only audit of every moderation transition | No | **Yes** |

**Foreign key dependencies (jadāwil mawjuda fi modules tania):**

| FK | References | Module |
|---|---|---|
| `service_reviews.service_id` | `services.id` | Catalog |
| `service_reviews.booking_item_id` | `booking_items.id` | Booking |
| `service_reviews.user_id` | `users.id` | Identity |
| `service_reviews.moderated_by` | `users.id` | Identity |
| `vendor_reviews.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `vendor_reviews.booking_vendor_id` | `booking_vendors.id` | Booking |
| `vendor_reviews.user_id` | `users.id` | Identity |
| `review_responses.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `review_moderation_log.moderator_id` | `users.id` | Identity |

**Migration order:** Reviews migrations run AFTER Identity (users, vendor_profiles), Catalog (services), and Booking (booking_items, booking_vendors).

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

### ☑ Cross-type (مش type-aware)

The review **submission and moderation flow is identical across all three product types**. A 5-star rating + body for a digital invitation looks the same as for a rented bouncy castle. The product-type context exists at the `booking_item` FK (each `booking_item` carries its own `product_type` discriminator), but no per-type Form Request, Action, or Resource is needed.

**Where the product type DOES leak in (handled with `match($enum)` if ever needed):**

- Eligibility window (e.g., `digital` services may want a longer review window post-redemption vs. `rental` post-return) — currently a single 90-day window applies to all types; per-type tuning is a Phase 6.0 candidate.
- Aggregated rating display on per-type Filament/admin views — handled in the consuming module (Catalog), not here.

**Test groups:** `reviews` only (no `rental`/`sale`/`digital` per-type cases required for this phase). However, **eligibility tests must cover all three product types** because the underlying `booking_item.item_status='completed'` follows three different state machines.

> **هذا قرار مدروس.** راجع `docs/specs/03_Three_Product_Types.md` §15. تم تأكيده ضد قائمة "type-aware modules" في template ADR — Reviews كانت في القائمة بسبب الـ FK لـ booking_item، بس الـ behavior نفسه cross-type.

---

## 5. Layer Layout

```
app/Modules/Reviews/
├── Domain/
│   ├── Models/
│   │   ├── ServiceReview.php
│   │   ├── VendorReview.php
│   │   ├── ReviewResponse.php
│   │   └── ReviewModerationLog.php
│   ├── Enums/
│   │   ├── ModerationStatus.php           # pending, approved, rejected, hidden
│   │   ├── ReviewLocale.php               # ar, en, mixed
│   │   └── ReviewType.php                 # service, vendor (used by review_responses + log)
│   ├── Events/
│   │   ├── ReviewSubmitted.php
│   │   ├── ReviewApproved.php
│   │   ├── ReviewRejected.php
│   │   └── ReviewHidden.php
│   ├── States/
│   │   └── ReviewModerationState.php      # spatie/laravel-model-states
│   └── Contracts/
│       ├── ReviewRepository.php
│       └── BookingItemReviewabilityReader.php   # asks Booking "is this booking_item completed?"
├── Application/
│   ├── Actions/
│   │   ├── SubmitServiceReviewAction.php
│   │   ├── SubmitVendorReviewAction.php
│   │   ├── ModerateReviewAction.php
│   │   └── RespondToReviewAction.php      # Phase 6.0 — scaffold only in 5.1
│   ├── Services/
│   │   └── RatingAggregationService.php   # recompute service/vendor average on approval
│   ├── DTOs/
│   │   ├── SubmitReviewData.php
│   │   └── ModerateReviewData.php
│   └── Listeners/
│       └── RecomputeRatingOnApproval.php  # listens to ReviewApproved, recomputes & fires ServiceRatingRecomputed / VendorRatingRecomputed
├── Infrastructure/
│   └── Repositories/
│       ├── EloquentServiceReviewRepository.php
│       ├── EloquentVendorReviewRepository.php
│       └── EloquentBookingItemReviewabilityReader.php   # implements the Booking-side contract via a thin DB query
├── Http/
│   ├── Controllers/
│   │   ├── Customer/SubmitServiceReviewController.php
│   │   ├── Customer/SubmitVendorReviewController.php
│   │   ├── Customer/ListMyReviewsController.php
│   │   └── Public/ListServiceReviewsController.php
│   ├── Requests/
│   │   ├── SubmitServiceReviewRequest.php
│   │   └── SubmitVendorReviewRequest.php
│   └── Resources/
│       ├── ServiceReviewResource.php
│       └── VendorReviewResource.php
├── Filament/
│   ├── Resources/                         # (intentionally empty — moderation lives on a custom Page)
│   └── Pages/
│       └── ReviewModerationPage.php       # unified queue across service + vendor reviews
├── Routes/
│   ├── customer.php
│   └── public.php
├── Database/
│   ├── Migrations/
│   │   ├── NNNN_create_service_reviews_table.php
│   │   ├── NNNN_create_vendor_reviews_table.php
│   │   ├── NNNN_create_review_responses_table.php
│   │   └── NNNN_create_review_moderation_log_table.php
│   └── Factories/
├── Resources/
│   └── lang/
│       ├── en/reviews.php
│       └── ar/reviews.php
└── Providers/
    └── ReviewsServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 جدولين منفصلين بدل polymorphic single table / Two tables instead of one polymorphic table

**القرار:** نخزن `service_reviews` و `vendor_reviews` في جدولين منفصلين بنفس الشكل تقريباً.

**البديل:** جدول واحد `reviews` مع `reviewable_type` + `reviewable_id` polymorphic.

**ليه؟:** الـ schema المقفول في `11_DB_Schema.md` بيفصلهم. الـ FK لـ `booking_item_id` (للخدمة) و `booking_vendor_id` (للمورد) مش polymorphic — كلاهما FK محدد بـ UNIQUE constraint مختلف. الـ uniqueness على DB level أبسط مع جدولين منفصلين، والـ admin queries بتفلتر دايماً بـ نوع التقييم. الـ `review_responses` و `review_moderation_log` هما اللي polymorphic عشان توفر الـ duplication في جداول الـ moderation/replies.

### 6.2 Soft delete على service_reviews + vendor_reviews

**القرار:** الجدولين فيهم `deleted_at`.

**البديل:** append-only، مفيش حذف.

**ليه؟:** المستخدم له الحق في حذف تقييمه (GDPR-style "right to be forgotten" + لو غير رأيه). الحذف soft delete علشان الـ audit trail يفضل سليم — لو admin محتاج يراجع لاحقاً. الـ `review_moderation_log` فاضل append-only للـ audit الكامل بغض النظر عن حالة الـ review نفسه.

### 6.3 Rating aggregation عبر listener async، مش inline في ModerateReviewAction

**القرار:** `ModerateReviewAction` بترفع event `ReviewApproved` بعد commit، والـ listener `RecomputeRatingOnApproval` بيحسب المتوسط الجديد ويعمل update على `services.average_rating` / `vendor_profiles.average_rating` (في queue worker).

**البديل:** نحدّث المتوسط inline في نفس الـ transaction.

**ليه؟:** الـ aggregation ممكن يلمس قراءات لكل التقييمات المعتمدة لخدمة معينة، وده ممكن يبطّأ moderation API. عمل الـ recompute async بيعزل الـ admin UX عن الـ aggregation cost. Constitution Principle IX (events fire `DB::afterCommit`) بيحكم القاعدة دي. الـ `ServiceRatingRecomputed` event بيخطر Catalog لتحديث Meilisearch index في نفس الـ pipeline.

### 6.4 Eligibility check via Contract، مش direct model import

**القرار:** الـ Reviews module بيعرّف `Reviews\Domain\Contracts\BookingItemReviewabilityReader` ولا بيعمل `use App\Modules\Booking\Domain\Models\BookingItem`.

**البديل:** نقرا `BookingItem` model مباشرة في `SubmitServiceReviewAction`.

**ليه؟:** Cross-module model imports forbidden per Constitution Principle I. الـ contract بيوضح الـ public surface اللي Booking module بيقدمها للـ Reviews. لو بكره الـ "completed" eligibility logic اتغيرت (مثلاً، فيه windows لكل product_type)، التغيير في implementation واحد، مش في كل module بيستهلك booking state.

### 6.5 Moderation عبر Filament Page مخصصة، مش Resource عادي

**القرار:** نعمل `ReviewModerationPage` (Filament custom Page) بتعرض queue موحد لكل الـ service_reviews + vendor_reviews `pending`، مع فلاتر بالـ rating, vendor, locale, date.

**البديل:** Two separate Filament Resources (`ServiceReviewResource`, `VendorReviewResource`).

**ليه؟:** الـ moderation workflow هو هو لجدولين — admin بيقرا الـ body ويوافق/يرفض. صفحة موحدة بتقلل context-switching للـ moderator. Resources منفصلة هتولّد double permissions (`view_any_service_review`, `view_any_vendor_review`) من Shield بدون قيمة مضافة. الـ approve/reject actions بتفوض لـ `ModerateReviewAction::execute(Review $review, ModerationDecision $decision)` اللي بيعمل `match` على نوع الـ review.

### 6.6 Locale field — assumption (cut-list)

**القرار:** نخزن `locale` على الـ review كـ ENUM(ar, en, mixed) بقيمة من `customer_profiles.preferred_locale` وقت الـ submission.

**البديل:** auto-detect من الـ body باستخدام language detection library.

**ليه؟:** auto-detection deferred في cut-list الـ phase. الـ `preferred_locale` على الـ customer profile موجود ومضمون (default `ar`). الـ `mixed` enum value فاضل متاح لو ضفنا detection لاحقاً.

---

## 7. Inter-Module Communication

### Events بنطلقها / Events we publish:

| Event | When | Payload |
|---|---|---|
| `Reviews\Domain\Events\ReviewSubmitted` | بعد ما customer submits review (status=pending) | `review_id`, `review_type`, `service_id` or `vendor_profile_id`, `rating`, `submitted_at` |
| `Reviews\Domain\Events\ReviewApproved` | بعد ما moderator approves | `review_id`, `review_type`, `service_id` or `vendor_profile_id`, `rating`, `approved_at`, `moderator_id` |
| `Reviews\Domain\Events\ReviewRejected` | بعد ما moderator rejects | `review_id`, `review_type`, `reason`, `moderator_id`, `rejected_at` |
| `Reviews\Domain\Events\ReviewHidden` | بعد ما admin يخفي review موافق عليه (e.g., post-publish takedown) | `review_id`, `review_type`, `reason`, `moderator_id` |
| `Reviews\Domain\Events\ServiceRatingRecomputed` | بعد كل تغير في average_rating لخدمة | `service_id`, `new_average`, `count` |
| `Reviews\Domain\Events\VendorRatingRecomputed` | بعد كل تغير في average_rating لمورد | `vendor_profile_id`, `new_average`, `count` |

### Events بنستهلكها / Events we consume:

| Event | Source Module | Action |
|---|---|---|
| (none in Phase 5.1) | — | الـ eligibility check لما العميل submits — pull-style عبر `BookingItemReviewabilityReader`، مش push عبر event |

> **ملاحظة:** ممكن نستهلك `Booking\Domain\Events\BookingItemCompleted` في Phase 6.0 لإرسال "review reminder" notification بعد X يوم، لكن مش جزء من Phase 5.1.

### Public Contracts (interfaces بنوفرها):

| Contract | Purpose | Implementation |
|---|---|---|
| `Reviews\Domain\Contracts\ReviewRepository` | تستخدم من Catalog/Identity للحصول على approved reviews count + average للخدمة أو المورد | `EloquentReviewRepository` |

### Public Contracts (interfaces بنستخدمها من غيرنا):

| Contract | From Module | Why we need it |
|---|---|---|
| `Booking\Domain\Contracts\BookingItemReader` (or similar) | Booking | تأكد إن الـ booking_item completed قبل ما نسمح بـ review |
| `Booking\Domain\Contracts\BookingVendorReader` | Booking | تأكد إن كل items الـ booking_vendor completed قبل ما نسمح بـ vendor review |

> **القاعدة:** لو احتجت تقرأ Eloquent model من module تاني → ده signal تطلب Contract منه، مش `use App\Modules\OtherModule\Domain\Models\X`.

---

## 8. Filament Footprint

| Page/Resource | Purpose | Translatable? |
|---|---|---|
| `Reviews/Filament/Pages/ReviewModerationPage.php` | صفحة موحدة للـ moderation: queue (pending), all (with filters), per-vendor view | Yes (review body shown in original locale; UI labels translated) |

**Navigation group:** `"Moderation"`

**Permissions generated by Shield:**
- `page_ReviewModerationPage`
- Custom (granted to `admin` role + `moderator` role): `moderate_service_review`, `moderate_vendor_review`, `hide_review`

> **Reminder:** بعد إنشاء الـ Page الجديدة، شغل `php artisan shield:generate --all`.

---

## 9. Testing Strategy

**Pest groups:** `reviews`

**Test files location:**
```
tests/
├── Feature/Modules/Reviews/
│   ├── SubmitServiceReviewTest.php
│   ├── SubmitVendorReviewTest.php
│   ├── ModerateReviewTest.php
│   ├── ReviewEligibilityTest.php
│   └── RatingAggregationListenerTest.php
└── Unit/Modules/Reviews/
    └── RatingAggregationServiceTest.php
```

**Required coverage:**

- [x] Happy path (submit → pending → approve → rating recomputed)
- [x] Auth (unauthenticated → 401)
- [x] Authorization (customer who didn't own the booking → 403; vendor → 403; only the customer of `booking_items.booking_id.user_id` can submit)
- [x] Validation (rating 1–5, body length, locale enum)
- [x] Idempotency on POST review (duplicate submission with same body returns the existing review, not 409 — to be confirmed in `/speckit.clarify`)
- [x] Locale (EN response, AR response — translatable error messages)
- [x] Eligibility per product type (rental completed → eligible; sale completed → eligible; digital completed → eligible; any non-completed → 422)
- [x] Uniqueness (one review per `booking_item_id`, one per `booking_vendor_id` — UNIQUE violation surfaces as 409)
- [x] Moderation transitions (pending→approved, pending→rejected, approved→hidden) logged in `review_moderation_log`
- [x] Architecture test: لا توجد imports من Booking/Catalog/Identity Eloquent models

**Architecture test pattern:**

```php
test('reviews module does not import other module models')
    ->expect('App\Modules\Reviews')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
        'App\Modules\Identity\Domain\Models',
        'App\Modules\Payments\Domain\Models',
    ]);
```

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) §Phase 5.1:

- [ ] Vendor responses to reviews (`RespondToReviewAction` + customer-facing endpoint) → defer to Phase 6.0
- [ ] Review locale auto-detection → defer to Phase 6.0; assume `customer_profiles.preferred_locale` for now
- [ ] Per-product-type review windows / eligibility tuning → defer to Phase 6.0
- [ ] "Review reminder" notification (X days post-completion) → defer to Phase 6.0
- [ ] Image attachments on reviews → out of Phase 1 entirely

---

## 11. Open Questions

- [ ] Should resubmission be allowed after a review is `rejected` by admin? (Default: **no** — UNIQUE on `booking_item_id` blocks it; user must contact support. To confirm in `/speckit.clarify`.)
- [ ] Does an `approved → hidden` transition revert the rating aggregation? (Default: **yes** — listener treats `hidden` as effectively excluded from the average.)
- [ ] Idempotency-Key on review submission — required or optional? (Default: optional but supported per Constitution VIII for `POST` mutations.)

> **لما يتحلوا، حول كل واحد لـ "Internal Decision" في القسم 6 أو لـ ADR منفصل.**

---

## 12. Implementation Checklist

عند تنفيذ الـ module:

- [ ] Migrations في `Reviews/Database/Migrations/` بالترتيب الصح (service_reviews → vendor_reviews → review_responses → review_moderation_log)
- [ ] Models تحت `Domain/Models/` (relationships, casts, scopes ONLY — مفيش business logic)
- [ ] Form Requests + Actions (cross-type — single set)
- [ ] API Resources مع locale conversion (review body returned in customer's `Accept-Language`; original `locale` field included)
- [ ] Filament `ReviewModerationPage` مع approve/reject/hide actions
- [ ] Service Provider مسجل في `bootstrap/providers.php`
- [ ] Listener `RecomputeRatingOnApproval` queued (ShouldQueue) و firing `ServiceRatingRecomputed`/`VendorRatingRecomputed`
- [ ] Pest tests كاملة per requirement فوق
- [ ] `php artisan shield:generate --all` (لـ ReviewModerationPage permissions)
- [ ] Translations في `Resources/lang/en/reviews.php` و `Resources/lang/ar/reviews.php`
- [ ] Architecture test passes (no cross-module model imports)
- [ ] هذا الـ ADR محدّث في القائمة الرئيسية في [`docs/adr/README.md`](README.md)
- [ ] `.specify/memory/project-index.md` محدث بـ ADR-0011 row
- [ ] `.specify/memory/api-registry.md` محدث بـ Reviews endpoints
