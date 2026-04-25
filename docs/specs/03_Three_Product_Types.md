# InstaParty — Three Product Types (Central Domain Reference)

> **Source of truth hierarchy (per Tech Decisions):** PRD → CLAUDE.md → **this document** → Tech Decisions → Software Description → conversation.
>
> This doc exists because the three product types (rental / sale / digital) are not just data — they are first-class domain concepts that drive schema, validation, fulfillment, pricing, refunds, and reporting. **Every type-aware feature MUST cover all three types.**

---

## 1. The Three Types

| Type | Code | Examples (from Software Description) |
|---|---|---|
| **Rental** | `rental` | Inflatables, mascots, photo booths, decorations, costumes |
| **Sale** | `sale` | Cakes, food, snacks, gifts, party supplies |
| **Digital** | `digital` | E-invitations, photo apps, gift links, gift cards |

### What makes them structurally different

| Dimension | Rental | Sale | Digital |
|---|---|---|---|
| **Inventory** | Time-bound (date overlap check) | Quantity-bound (stock count) | Code/seat-bound or unlimited |
| **Slot** | Required event window | Delivery date | Instant or scheduled redemption |
| **Delivery** | Setup + teardown on-site | Physical handoff or shipping | Email / SMS / in-app / redirect URL |
| **Refund window** | Up to N hours before event start | State-driven (until preparation begins) | Per-flag (`is_refundable_after_delivery`) |
| **Lifecycle states** | Reserved → setup → active → teardown → returned | Ordered → preparing → ready → shipped → delivered | Pending → delivered → redeemed → expired |
| **Customization** | Theme, optional add-ons | Per-item customization (cake message, dietary) | Template fields, redemption parameters |

---

## 2. Storage Pattern (LOCKED)

```
┌─────────────────────────┐
│       services          │  ← polymorphic base
│  (id, public_id, type,  │     `product_type` is discriminator ENUM
│   vendor_id, category,  │
│   name, description,    │
│   base_price_minor, …)  │
└────────┬────────────────┘
         │ 1:1 by service_id
         │
    ┌────┼─────────────────────────┬─────────────────────────┐
    │    │                         │                         │
    ▼    ▼                         ▼                         ▼
service_rental_details   service_sale_details   service_digital_details
   (size_dimensions,       (is_perishable,         (delivery_method,
    requires_electricity,   is_made_to_order,       has_expiry,
    duration_hours,         lead_time_hours,        is_refundable_after_delivery,
    setup_time_minutes,     stock_quantity,         redemption_url_template,
    security_deposit, …)    customization_fields,   asset_path,
                            min/max_quantity, …)    code_pool_id, …)
```

### What's forbidden

| Anti-pattern | Why it's forbidden |
|---|---|
| Single Table Inheritance (one mega-table with nullable type-specific columns) | Schema becomes unreadable, indexing breaks down, validation logic explodes |
| Three completely independent top-level tables (no shared base) | No way to do cross-type queries (e.g., "all of vendor X's services"), no shared moderation lifecycle, search/booking polymorphism breaks |
| Type stored as string in business code (`if $type == 'rental'`) | Replace with `match($enum)` on `App\Modules\Catalog\Domain\Enums\ProductType` |

---

## 3. Code Architecture

### 3.1 Canonical Enum

```php
namespace App\Modules\Catalog\Domain\Enums;

enum ProductType: string
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Rental => __('catalog.types.rental'),
            self::Sale => __('catalog.types.sale'),
            self::Digital => __('catalog.types.digital'),
        };
    }

    public function detailsTable(): string
    {
        return match ($this) {
            self::Rental => 'service_rental_details',
            self::Sale => 'service_sale_details',
            self::Digital => 'service_digital_details',
        };
    }
}
```

### 3.2 Per-Type Classes

For every type-aware feature, create three parallel classes named with the type:

| Concern | Classes |
|---|---|
| Form Requests (vendor-facing) | `CreateRentalServiceRequest`, `CreateSaleServiceRequest`, `CreateDigitalServiceRequest` (and `Update…Request` variants) |
| Actions (writes) | `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction`, plus `Update…` and `Import…FromExcelAction` |
| API Resources (reads) | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (extending `ServiceResource` base) |
| Filament Resources | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` (grouped under "Services" navigation) |
| Slot resolvers | `RentalSlotResolver`, `SaleSlotResolver`, `DigitalSlotResolver` |
| Price calculators | `RentalPriceCalculator`, `SalePriceCalculator`, `DigitalPriceCalculator` |
| Fulfillment state machines | Three distinct state graphs via `spatie/laravel-model-states` |
| Excel templates | Three separate templates with paired `_en` / `_ar` columns |

### 3.3 Cross-Type Code

When code must handle all types, use `match($productType)`:

```php
// ✅ CORRECT
$refund = match ($booking->product_type) {
    ProductType::Rental => $this->rentalRefundPolicy->compute($booking),
    ProductType::Sale => $this->saleRefundPolicy->compute($booking),
    ProductType::Digital => $this->digitalRefundPolicy->compute($booking),
};

// ❌ FORBIDDEN
if ($booking->product_type === 'rental') { ... }
elseif ($booking->product_type === 'sale') { ... }
else { ... }
```

Cross-type Actions that don't need per-type logic (publish, archive, moderate) use the base class only.

---

## 4. Per-Type Schemas (Detail Tables)

> Full column lists are in `docs/specs/02_Tech_Decisions.md` and the migrations themselves. This is a quick conceptual map.

### 4.1 `service_rental_details`

Time-and-space-bound inventory:

- Physical attributes: dimensions, weight, suggested age range
- Operational: `requires_electricity`, `requires_outdoor_space`, `min_space_required`
- Duration: `default_rental_duration_hours`, `min/max_rental_duration_hours`
- Logistics: `setup_time_minutes`, `teardown_time_minutes`
- Financials: `security_deposit_minor` + currency
- `theme_id` (optional FK to `service_themes`)
- `custom_attributes` (per-category schema values)

### 4.2 `service_sale_details`

Quantity-bound, possibly made-to-order:

- Physical attributes: dimensions, weight, suggested age range
- Stock control: `stock_quantity` (NULL = unlimited)
- Production: `is_perishable`, `is_made_to_order`, `lead_time_hours`
- Customization: `allows_customization`, `customization_fields` (e.g., cake message)
- Order constraints: `min_order_quantity`, `max_order_quantity`
- `theme_id` (optional)
- `custom_attributes`

### 4.3 `service_digital_details`

No physical inventory, distinct delivery mechanics:

- `delivery_method`: `email | sms | in_app | redirect_url`
- Lifecycle: `has_expiry`, `expiry_days_after_purchase`
- Refund: `is_refundable_after_delivery` (per-service flag)
- Distribution: `redemption_url_template`, `asset_path` (e.g., e-invitation template)
- Future: `code_pool_id` (Phase 1.5 — finite code pool table)
- Customization: `customization_fields`
- `custom_attributes`

---

## 5. Per-Type Lifecycles (Fulfillment State Machines)

Each type has its own state machine on `booking_items.item_status`. These are **distinct graphs**, not a shared one.

### 5.1 Rental Lifecycle

```
reserved → confirmed → scheduled → setup → active → teardown → returned → completed
              │            │          │       │         │          │
              └─→ cancelled (with optional refund per timing rule)
```

### 5.2 Sale Lifecycle

```
ordered → confirmed → in_preparation → ready → out_for_delivery → delivered → completed
            │              │
            └─→ cancelled (refund if before in_preparation; not after)
```

### 5.3 Digital Lifecycle

```
pending → delivered → redeemed → completed
   │          │
   │          └─→ expired (if has_expiry and expiry_days_after_purchase elapsed)
   └─→ failed (delivery failure, e.g., invalid email)
```

---

## 6. Per-Type Pricing

`RentalPriceCalculator`, `SalePriceCalculator`, `DigitalPriceCalculator` resolve final price from:

| Type | Inputs |
|---|---|
| Rental | base_price + duration tier + setup fee + delivery fee + security deposit |
| Sale | base_price × quantity − volume discount tier + delivery fee + customization surcharge |
| Digital | base_price + redemption flags (e.g., bulk code pool surcharge) |

Tiers stored in `service_pricing_tiers` table with `tier_kind` discriminator (`quantity` / `duration_hours` / `duration_days`).

---

## 7. Per-Type Refund Policies

| Type | Policy |
|---|---|
| **Rental** | Refund up to **24h before event_start** (configurable per service). State-aware: blocked once item enters `setup` state. |
| **Sale** | Refund **only before** the item enters `in_preparation` state (state-driven, not time-driven). |
| **Digital** | Refund per `is_refundable_after_delivery` flag on the service detail. Default: not refundable after delivery. |

Resolved by `RefundPolicyService::policyFor(ProductType $type)` returning a `RefundPolicy` value object.

---

## 8. Per-Type Vendor Approval

A vendor is approved **per type independently** via `vendor_approved_product_types`. Permissions reflect this:

- `service.create.rental.own`
- `service.create.sale.own`
- `service.create.digital.own`

(plus `update`, `delete`, `publish` × per-type variants)

A vendor with only `rental` approval cannot create sale or digital services, even from Excel imports.

---

## 9. Per-Type Commissions

`commission_rates` table allows different rates per `(category × product_type)`. Most-specific match wins:

```
1. (category × type)        ← most specific
2. (category × NULL)        ← any type within this category
3. (NULL × type)            ← any category for this type
4. (NULL × NULL)            ← global default
```

Rates stored in **basis points** (1500 = 15%). Snapshot taken at booking time on `booking_items.commission_bps`.

---

## 10. Per-Type Excel Templates

**Three separate Excel templates** — never one template with conditional fields. All translatable fields have paired `_en` / `_ar` columns.

### 10.1 Rental Template Columns

```
code, category, name_en, name_ar,
short_description_en, short_description_ar,
long_description_en, long_description_ar,
theme_en, theme_ar,
base_price, currency,
length, width, height, weight_kg, suggested_age_min, suggested_age_max,
requires_electricity, requires_outdoor_space,
default_rental_duration_hours, min_rental_duration_hours, max_rental_duration_hours,
setup_time_minutes, teardown_time_minutes,
security_deposit, security_deposit_currency,
images_folder, video_url, external_url
```

### 10.2 Sale Template Columns

```
code, category, name_en, name_ar,
short_description_en, short_description_ar,
long_description_en, long_description_ar,
theme_en, theme_ar,
base_price, currency,
length, width, height, weight_kg, suggested_age_min, suggested_age_max,
is_perishable, is_made_to_order, lead_time_hours, stock_quantity,
allows_customization, customization_fields_json,
min_order_quantity, max_order_quantity,
images_folder, video_url, external_url
```

### 10.3 Digital Template Columns

```
code, category, name_en, name_ar,
short_description_en, short_description_ar,
long_description_en, long_description_ar,
base_price, currency,
delivery_method, has_expiry, expiry_days_after_purchase,
is_refundable_after_delivery, redemption_url_template,
asset_path, customization_fields_json,
images_folder, external_url
```

### Validation Rules (All Three)

- Both `_en` and `_ar` variants must be present and non-empty for required fields
- **No partial commits** — all rows valid or none committed (Tech Decisions §12)
- Conditional rules: e.g., `lead_time_hours` required when `is_made_to_order = true` (sale)
- Per-row error feedback in the user's locale

---

## 11. Per-Type Notifications

Templates are scoped to `(event_key × channel × audience × locale)`. Type-specific events include:

| Event Key | Type |
|---|---|
| `rental.delivery_scheduled` | Rental |
| `rental.setup_started` | Rental |
| `rental.teardown_completed` | Rental |
| `sale.preparation_started` | Sale |
| `sale.out_for_delivery` | Sale |
| `sale.delivered` | Sale |
| `digital.delivered` | Digital |
| `digital.redeemed` | Digital |
| `digital.expiring_soon` | Digital |

Cross-type events (booking-level) use type-agnostic templates: `booking.submitted`, `booking.modified`, `booking.confirmed`, etc.

---

## 12. Per-Type Reporting

All admin reports must support per-type breakdowns:

- Bookings by status × type
- Revenue by type × period
- Top vendors by type
- Inventory utilization (rental only — date overlap)
- Stock-out frequency (sale only)
- Redemption rate (digital only)

---

## 13. Testing Requirements

> **From Tech Decisions §2.4: "Every type-aware feature MUST cover all three types in tests."**

For every feature that's type-aware (any feature that uses `match($productType)` or has per-type classes), Pest tests must include:

```php
test('creates a rental service', function () { ... });
test('creates a sale service', function () { ... });
test('creates a digital service', function () { ... });
```

Plus the standard set:
- Happy path
- Auth (no auth → 401)
- Authorization (wrong role / wrong vendor → 403)
- Validation (per-type required fields)
- Idempotency (where applicable)
- Locale (EN response vs AR response)
- All three product types (mandatory for type-aware features)

---

## 14. Anti-Patterns Checklist

When reviewing or generating code, watch for:

| Anti-pattern | Fix |
|---|---|
| Generic "service" code that ignores type | Add per-type variant or `match($enum)` |
| Type as raw string (`'rental'`) outside DB layer | Use `ProductType` enum |
| Single Filament resource trying to handle all types | Split into 3 resources under "Services" group |
| One Excel template with conditional columns | Three separate templates |
| Shared state machine for all types | Three distinct state machines |
| `if/elseif` chain on `$type` string | `match($enum)` |
| Test that covers only rental | Add sale + digital test cases |
| Refund logic shared with timing only | Use `RefundPolicyService` per-type |
| Vendor permissions ungated by type | Use `service.{action}.{type}.own` permissions |

---

## 15. Quick Reference for Code Generation

When asked to add a feature, ask: **"Is this type-aware?"**

If yes → produce three variants. If no → produce one cross-type implementation.

| Type-aware? | Examples |
|---|---|
| **Yes** | Service creation, validation, fulfillment, pricing, refund, Excel import, Filament resource, slot resolver, notification template (lifecycle events) |
| **No** | Booking creation, payment capture, vendor profile management, geography, audit logging, search index (with `product_type` as a facet, not separate indexes) |
