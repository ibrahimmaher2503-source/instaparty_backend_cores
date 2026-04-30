# ADR-0004 — Catalog Module

- **Status:** Accepted
- **Date:** 2026-04-29
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-2-catalog
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §7.5 (Vendor Service Management), FR-19, FR-20, FR-21, FR-22
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §3 (Three Product Types)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §5 (Catalog, 13 tables)
  - [`docs/specs/03_Three_Product_Types.md`](../specs/03_Three_Product_Types.md) — module is type-aware

---

## 1. السياق / Context

**AR:** InstaParty بتسمح للـ vendors ببيع وتأجير وتسليم خدمات أحداث من 3 أنواع مختلفة (rental, sale, digital). كل نوع له منطق مختلف في التسعير والتوافر والـ fulfillment. الـ Catalog module بيوفر لـ vendor ينشئ خدماته، وللـ admin يراجعها وينشرها، وللـ booking engine يقرأ منها البيانات عند إنشاء الحجز.

**EN:** InstaParty vendors sell, rent, and deliver party services in 3 distinct product types (rental, sale, digital), each with different pricing, availability, and fulfillment logic. The Catalog module lets vendors create services, admins review and publish them, and the booking engine read service data at booking time. It is the central domain object that almost every other module references.

**Phase:** 2.0–2.4  
**Built in:** Weeks 2–3, Days 8–17

---

## 2. المسؤوليات / Responsibilities

### الـ module ده مسؤول عن / This module owns:

- Service creation, update, and lifecycle management (draft → pending_review → published → archived)
- Per-product-type service detail data (`service_rental_details`, `service_sale_details`, `service_digital_details`)
- Category taxonomy (2-level hierarchy: group → subcategory)
- Occasion management and occasion↔category pivot
- Per-(category × type) dynamic field schemas (`category_field_schemas`)
- Service themes (many-to-many via `service_themes_pivot`)
- Service media: gallery images via spatie/laravel-medialibrary (`gallery` collection)
- Inventory reservation system (`service_inventory_reservations`) — overselling guard, cart hold + payment hold
- Availability blocks and excluded dates
- Pricing tiers (base + tiered pricing)
- Excel bulk import of services (Phase 2.4 / 6.1)
- Meilisearch index management via Laravel Scout (Phase 3.0)

### الـ module ده مش مسؤول عن / This module does NOT own:

- Vendor identity / approval → Identity module
- Booking creation and state transitions → Booking module
- Payments and refund logic → Payments module
- Review aggregation (rating_avg updates) → Reviews module publishes events, Catalog listens
- Geography (cities lookup for vendor coverage) → Geography module via Contract
- Wishlists and search logs → Discovery module

---

## 3. الجداول المملوكة / Tables Owned

من [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §5:

| Table | الغرض / Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `occasions` | Occasion taxonomy (birthday, wedding…) | Yes | No |
| `categories` | 2-level category hierarchy with allowed product types | Yes | No |
| `occasion_category` | Pivot: occasions ↔ categories | No | No |
| `category_field_schemas` | Dynamic field definitions per (category × product_type) | No | No |
| `service_themes` | Reusable theme tags (princess, superhero…) | No | No |
| `services` | Polymorphic base: all 3 product types, `product_type` discriminator | Yes | No |
| `service_rental_details` | 1:1 rental-specific columns (electricity, dimensions, deposit) | No | No |
| `service_sale_details` | 1:1 sale-specific columns (perishable, made-to-order, stock) | No | No |
| `service_digital_details` | 1:1 digital-specific columns (delivery method, expiry, redemption) | No | No |
| `service_pricing_tiers` | Tiered pricing per service (quantity / duration) | No | No |
| `service_availability_blocks` | Explicit blocked slots per service | No | No |
| `service_excluded_dates` | Excluded dates per vendor / per service | No | No |
| `service_themes_pivot` | Many-to-many: services ↔ themes | No | No |
| `service_inventory_reservations` | Overselling guard: cart holds (15 min) + payment holds (24h) | No | No |

**Foreign key dependencies (في modules تانية):**

| FK | المرجع / References | Module |
|---|---|---|
| `services.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `service_inventory_reservations.holder_user_id` | `users.id` | Identity |
| `service_inventory_reservations.booking_item_id` | `booking_items.id` | Booking (Phase 3 — nullable until then) |

> **Migration order:** Geography → Identity → Catalog. `service_inventory_reservations.booking_item_id` is nullable (NULL while in `held` state) — no FK to `booking_items` is declared at migration time to avoid circular dependency. The constraint will be added in Phase 3.1.

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

### ☑ Type-aware (يلزم تغطية rental + sale + digital)

الـ Catalog module هو **أصل** الـ type-aware pattern في InstaParty.

- `services.product_type` ENUM — discriminator for all downstream logic
- 3 separate detail tables (1:1): `service_rental_details`, `service_sale_details`, `service_digital_details`
- Per-type Form Requests: `CreateRentalServiceRequest`, `CreateSaleServiceRequest`, `CreateDigitalServiceRequest`
- Per-type Actions: `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction`
- Per-type API Resources: `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource`
- Per-type Filament Resources: same three (under "Services" nav group)
- Cross-type code uses `match($productType)` on `App\Modules\Catalog\Domain\Enums\ProductType`
- Pest tests **must** cover all 3 product types for every type-aware feature

---

## 5. Layer Layout

```
app/Modules/Catalog/
├── Domain/
│   ├── Models/
│   │   ├── Service.php               # base, with product_type discriminator
│   │   ├── ServiceRentalDetail.php
│   │   ├── ServiceSaleDetail.php
│   │   ├── ServiceDigitalDetail.php
│   │   ├── Category.php
│   │   ├── Occasion.php
│   │   ├── ServiceTheme.php
│   │   ├── ServicePricingTier.php
│   │   ├── ServiceAvailabilityBlock.php
│   │   ├── ServiceExcludedDate.php
│   │   └── ServiceInventoryReservation.php
│   ├── Enums/
│   │   ├── ProductType.php            # Rental | Sale | Digital (canonical)
│   │   └── ServiceStatus.php          # Draft | PendingReview | Published | Rejected | Archived
│   ├── Events/
│   │   ├── ServiceCreated.php
│   │   ├── ServicePublished.php
│   │   ├── ServiceRejected.php
│   │   └── ServiceArchived.php
│   └── Contracts/
│       └── ServiceRepository.php      # read contract for Booking module
├── Application/
│   ├── Actions/
│   │   ├── CreateRentalServiceAction.php
│   │   ├── CreateSaleServiceAction.php
│   │   ├── CreateDigitalServiceAction.php
│   │   ├── UpdateRentalServiceAction.php
│   │   ├── UpdateSaleServiceAction.php
│   │   ├── UpdateDigitalServiceAction.php
│   │   ├── PublishServiceAction.php        # cross-type (match($enum) for notifications)
│   │   ├── ArchiveServiceAction.php        # cross-type
│   │   ├── HoldServiceInventoryAction.php  # match($enum): rental overlap, sale stock, digital free
│   │   ├── ReleaseServiceInventoryAction.php
│   │   └── ImportRentalServicesFromExcelAction.php
│   ├── DTOs/
│   │   ├── CreateRentalServiceDTO.php
│   │   ├── CreateSaleServiceDTO.php
│   │   └── CreateDigitalServiceDTO.php
│   └── Listeners/
│       ├── TakeVendorServicesOfflineOnTypeRevoked.php  # consumes Identity\VendorTypeRevoked
│       └── UpdateServiceRatingOnReviewPublished.php   # consumes Reviews\ReviewPublished
├── Infrastructure/
│   └── Repositories/
│       └── EloquentServiceRepository.php
├── Http/
│   ├── Controllers/
│   │   ├── RentalServiceController.php
│   │   ├── SaleServiceController.php
│   │   └── DigitalServiceController.php
│   ├── Requests/
│   │   ├── CreateRentalServiceRequest.php
│   │   ├── UpdateRentalServiceRequest.php
│   │   ├── CreateSaleServiceRequest.php
│   │   ├── UpdateSaleServiceRequest.php
│   │   ├── CreateDigitalServiceRequest.php
│   │   └── UpdateDigitalServiceRequest.php
│   └── Resources/
│       ├── RentalServiceResource.php
│       ├── SaleServiceResource.php
│       └── DigitalServiceResource.php
├── Filament/
│   └── Resources/
│       ├── RentalServiceResource.php     # navigationGroup: "Services"
│       ├── SaleServiceResource.php       # navigationGroup: "Services"
│       ├── DigitalServiceResource.php    # navigationGroup: "Services"
│       ├── CategoryResource.php          # navigationGroup: "Catalog"
│       └── OccasionResource.php          # navigationGroup: "Catalog"
├── Routes/
│   ├── customer.php    # GET /services (browse), GET /services/{id}
│   ├── vendor.php      # POST/PATCH /vendor/services/{type}, GET /vendor/services
│   └── admin.php       # (mostly Filament — manual moderation endpoints if needed)
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
│       └── CatalogSeeder.php
├── Resources/
│   └── lang/
│       ├── en/catalog.php
│       └── ar/catalog.php
└── Providers/
    └── CatalogServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 Polymorphic base + 3 detail tables — not Single Table Inheritance

**القرار:** `services` جدول base واحد بـ `product_type` discriminator. كل type ليه 1:1 detail table (`service_rental_details`, `service_sale_details`, `service_digital_details`). الـ PK في الـ detail table هو `service_id` نفسه (مفيش `id` auto-increment على الـ detail).

**البديل:** Single Table Inheritance (كل columns من الـ 3 types في `services` نفسها، مع NULL للـ columns اللي مش مستخدمة).

**ليه؟:**
- STI → `services` بتوصل 40+ عمود، معظمها NULL لكل record. FK integrity على detail-specific columns مستحيل.
- Three independent top-level tables → مفيش shared query (`SELECT * FROM services WHERE vendor_profile_id = ?` تشمل الثلاثة مرة واحدة). Booking engine محتاج يقرأ الـ base بدون ما يعرف النوع.
- الـ polymorphic base بيخلي `booking_items.service_id` تشاور على `services.id` بغض النظر عن النوع.
- مرجع: `docs/specs/03_Three_Product_Types.md` §2

### 6.2 ProductType enum canonical location: Catalog module (not Shared)

**القرار:** `App\Modules\Catalog\Domain\Enums\ProductType` هو الـ canonical enum. الـ `App\Modules\Shared\Domain\Enums\ProductType` اللي اتعمل في Phase 1 يتحول لـ re-export (أو يتحذف) لصالح الـ Catalog version.

**البديل:** إبقاء الـ enum في Shared module عشان Booking + Identity يقدروا يستخدموه بدون ما يستوردوا من Catalog.

**ليه؟:**
- `ProductType` هو domain concept أصيل لـ Catalog. Booking بيستخدمه لأنه بيحجز catalog items — مش لأنه يمتلك الـ concept.
- الـ Shared module هو للـ cross-cutting utilities (MoneyCast, HasPublicId, ApiResponse) — مش domain enums.
- Booking يستورد ProductType من Catalog عبر الـ `ServiceRepository` Contract — ده مش direct model import (التعامل بـ enum value مسموح).

### 6.3 ProductType enum moves from Shared to Catalog on Phase 2.0 Day 1

**القرار:** `App\Modules\Catalog\Domain\Enums\ProductType` هو الـ canonical location. الـ `App\Modules\Shared\Domain\Enums\ProductType` اللي اتعمل في Phase 1 يتحذف، وكل الـ imports في Phase 1 code تتحدث قبل ما نكتب أي Phase 2 code.

**البديل C (اللي رُفض):** إبقاء الـ enum في Shared لأن `product_type` بيظهر في جداول تانية (booking_items, commission_rates, vendor_approved_product_types).

**ليه؟:**
- `ProductType` هو domain concept تبعه الـ Catalog. الـ modules التانية بتستخدمه *لأنهم بيتعاملوا مع catalog items* — مش لأنهم يمتلكوا الـ concept.
- Booking بيقرأ الـ enum من Catalog عبر DTO أو value — ده مش cross-module model import.
- Shared Module هو للـ utilities (MoneyCast, HasPublicId, ApiResponse) — مش domain enums.
- **First task of Phase 2.0 Day 1:** move enum + update all `use App\Modules\Shared\Domain\Enums\ProductType` imports across the codebase.

### 6.4 Inventory reservation in Catalog (not Booking)

**القرار:** `service_inventory_reservations` في Catalog، و `HoldServiceInventoryAction` في Catalog Application layer.

**البديل:** وضع الـ reservation logic في Booking module لأن الـ reservation بتحصل عند إنشاء الحجز.

**ليه؟:**
- الـ reservation هي service-level concern (تتعلق بتوافر الخدمة ذاتها) مش booking-level concern.
- Cart holds بتحصل قبل ما الـ booking يتوجد (15-minute pre-booking hold). الـ Booking module مش موجود بعد لما الـ customer بيعمل cart hold.
- الـ Booking module هيستخدم `HoldServiceInventoryAction` via Catalog's public Contract — مش inverse.
- Cleanup job (`ReleaseExpiredReservations`) هو service concern — يشتغل مستقل عن الـ bookings.

---

## 7. Inter-Module Communication

### Events بنطلقها / Events we publish:

| Event | When | Payload |
|---|---|---|
| `Catalog\ServiceCreated` | بعد commit للـ service | `{service_id, product_type, vendor_profile_id}` |
| `Catalog\ServicePublished` | admin ينشر service | `{service_id, product_type}` |
| `Catalog\ServiceRejected` | admin يرفض service | `{service_id, product_type, reason}` |
| `Catalog\ServiceArchived` | service تتأرشف | `{service_id, product_type}` |

### Events بنستهلكها / Events we consume:

| Event | Source Module | Action |
|---|---|---|
| `Identity\VendorTypeRevoked` | Identity | Archive all vendor services of that product_type |
| `Identity\VendorSuspended` | Identity | Archive all vendor services (all types) |
| `Reviews\ReviewPublished` | Reviews | Update `services.rating_avg` + `rating_count` |

### Public Contracts (interfaces بنوفرها):

| Contract | الغرض / Purpose | Implementation |
|---|---|---|
| `Catalog\Domain\Contracts\ServiceRepository` | Booking needs to read service data (base_price, product_type, vendor) at booking time | `EloquentServiceRepository` |

### Public Contracts (interfaces بنستخدمها من غيرنا):

| Contract | From Module | Why we need it |
|---|---|---|
| `Identity\Domain\Contracts\VendorProfileRepository` | Check vendor approval status before allowing service creation | `EloquentVendorProfileRepository` |

---

## 8. Filament Footprint

| Resource | الغرض / Purpose | Translatable? |
|---|---|---|
| `Catalog/Filament/Resources/RentalServiceResource.php` | CRUD for rental services — admin moderation + publish | Yes (name, description) |
| `Catalog/Filament/Resources/SaleServiceResource.php` | CRUD for sale services | Yes |
| `Catalog/Filament/Resources/DigitalServiceResource.php` | CRUD for digital services | Yes |
| `Catalog/Filament/Resources/CategoryResource.php` | Manage category taxonomy + allowed_product_types | Yes (name, description) |
| `Catalog/Filament/Resources/OccasionResource.php` | Manage occasion tags + occasion↔category pivot | Yes (name, description) |

**Navigation groups:**
- `Services` → RentalServiceResource, SaleServiceResource, DigitalServiceResource
- `Catalog` → CategoryResource, OccasionResource

**Permissions generated by Shield:**
- Standard CRUD on each resource
- **Custom:** `publish_rental_service`, `publish_sale_service`, `publish_digital_service`, `reject_service`, `moderate_service`

> **Reminder:** بعد إنشاء Resources الجديدة، شغل `php artisan shield:generate --all`.

---

## 9. Testing Strategy

**Pest groups:** `catalog`, `rental`, `sale`, `digital`

**Test files location:**
```
tests/
├── Feature/Modules/Catalog/
│   ├── CreateRentalServiceTest.php
│   ├── CreateSaleServiceTest.php
│   ├── CreateDigitalServiceTest.php
│   ├── UpdateServiceTest.php
│   ├── PublishServiceTest.php
│   ├── InventoryReservationTest.php
│   ├── FilamentRentalServiceResourceTest.php
│   └── ExcelImportTest.php
└── Unit/Modules/Catalog/
    └── ArchTest.php
```

**التغطية المطلوبة / Required coverage:**

- [ ] Happy path (create + publish each type)
- [ ] Auth (unauthenticated → 401)
- [ ] Authorization (vendor without type approval can't create that type → 403)
- [ ] Validation (per-type required fields, conditional rules like `lead_time_hours` required when `is_made_to_order`)
- [ ] Locale (service name + description returned in correct locale)
- [ ] **All three product types — non-negotiable for every type-aware feature**
- [ ] Inventory: rental overlap detection, sale stock decrement, digital free-pass
- [ ] Inventory cleanup: expired holds released by artisan command
- [ ] Excel import: 100% valid → all created; 1 invalid row → 0 created, errors logged
- [ ] Architecture test: Catalog does not import Booking or Payments models

**Architecture test pattern:**

```php
test('Catalog does not import other module models')
    ->expect('App\Modules\Catalog')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Payments\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
    ]);
```

---

## 10. Cut-list (لو تأخرت)

من [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md):

- [ ] Pricing tiers UI (Filament) → single `base_price` only in Phase 2.x; tiered pricing deferred to Phase 6.x
- [ ] Availability blocks management (vendor UI) → assume "always available" in Phase 2; explicit blocks deferred to Phase 3+
- [ ] Digital code pools (`code_pool_id`) → deferred to Phase 1.5
- [ ] Sale + digital Excel imports → Phase 6.1 (only rental Excel in Phase 2.4)
- [ ] Image folder upload in Excel → filenames only in Phase 2.4; full upload in Phase 6.x
- [ ] `CategoryFieldSchemas` Filament UI → admin seeds directly in Phase 2; UI deferred to Phase 6.2
- [ ] Meilisearch indexing → Phase 3.0 (Scout import); service creation in Phase 2 does NOT push to search index yet

---

## 11. Open Questions

- [x] **ProductType enum migration:** Resolved — Option A. Move to `App\Modules\Catalog\Domain\Enums\ProductType` and update all Phase 1 imports on Phase 2.0 Day 1. See §6.3.
- [x] **`customer_addresses` table** ownership confirmed: Cross-cutting module (Schema §12). Catalog does not touch it.

---

## 12. Implementation Checklist

- [ ] Migrations dalam `Catalog/Database/Migrations/` bالترتيب الصح (occasions → categories → services → details → reservations)
- [ ] Models تحت `Domain/Models/` (relationships, casts, scopes ONLY)
- [ ] `ProductType` enum created and Shared version resolved
- [ ] Per-type Form Requests + Actions (Rental, Sale, Digital each)
- [ ] API Resources مع locale conversion + `@response` docblocks
- [ ] Filament Resources (RentalServiceResource, SaleServiceResource, DigitalServiceResource, CategoryResource, OccasionResource)
- [ ] `HoldServiceInventoryAction` with `match($productType)` — no if/elseif
- [ ] `ReleaseExpiredReservations` artisan command scheduled every minute
- [ ] `ImportRentalServicesFromExcelAction` with strict no-partial-commit
- [ ] Service Provider مسجل في `bootstrap/providers.php`
- [ ] Pest tests كاملة (all 3 types, happy + auth + authz + validation + locale + inventory)
- [ ] `php artisan shield:generate --all`
- [ ] Translations في `Resources/lang/en/catalog.php` و `Resources/lang/ar/catalog.php`
- [ ] Architecture test passes
- [ ] هذا الـ ADR محدّث في القائمة الرئيسية في [`docs/adr/README.md`](README.md)
