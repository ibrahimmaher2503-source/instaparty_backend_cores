---
description: Rules for the three product types (rental/sale/digital) — central domain
globs:
  - "app/Modules/Catalog/**/*.php"
  - "app/Modules/Booking/**/*.php"
  - "app/Modules/Discovery/**/*.php"
  - "app/Modules/Reviews/**/*.php"
  - "app/Modules/Imports/**/*.php"
---

# Three Product Types — Inviolable Rules

> Full reference: `docs/specs/03_Three_Product_Types.md`

## The three types

| Type | Code | Examples |
|---|---|---|
| Rental | `rental` | inflatables, mascots |
| Sale | `sale` | cakes, gifts |
| Digital | `digital` | e-invitations, photo apps |

## Storage architecture

- Base: `services` (with `product_type` ENUM discriminator).
- Detail tables: `service_rental_details`, `service_sale_details`, `service_digital_details` (1:1 by `service_id`).
- ❌ Single Table Inheritance — forbidden.
- ❌ Three independent top-level tables — forbidden.

## Code architecture

- Canonical enum: `App\Modules\Catalog\Domain\Enums\ProductType` with cases `Rental`, `Sale`, `Digital`.
- Per-type classes for type-aware operations (Form Requests, Actions, Resources, Filament Resources, slot resolvers, price calculators, fulfillment state machines).
- Cross-type code uses `match($enum)`. **Never** `if/elseif` on type strings.

## Decision tree for new features

When adding a feature, ask: **"Is this type-aware?"**

**Type-aware** (different shape per type) → produce three variants:
- service creation, validation, fulfillment, pricing, refund, Excel import, Filament resource, slot resolver, notification template (lifecycle events).

**Cross-type** (same shape for all types) → one implementation, possibly with `match($enum)` for branching:
- booking creation, payment capture, vendor profile management, geography, audit logging, search index (with `product_type` as a facet).

## Tests

For every type-aware feature, the test suite must include cases for **all three types**:

```php
it('handles rental case', function () { /* ... */ })->group('rental');
it('handles sale case',   function () { /* ... */ })->group('sale');
it('handles digital case',function () { /* ... */ })->group('digital');
```

A test that covers only one type for a type-aware feature is incomplete.

## Anti-pattern triggers

If you see any of these, **fix before continuing**:

- A Service model method that returns different shapes per type without type-specific subclasses.
- A migration on `services` that adds rental-only or sale-only columns directly (those go in detail tables).
- A single Excel template with conditional columns based on a type field.
- A booking Action that handles only rental and `// TODO sale, digital`.
- A search index per type instead of one index with `product_type` as a facet.
- A Filament page with tabs labelled "Rental / Sale / Digital" trying to share one form schema.

## Refund policy

Type-specific, resolved by `RefundPolicyService`:

- **Rental:** refundable up to 24h before `event_starts_at` (configurable per service); blocked once item enters `setup`.
- **Sale:** refundable until item enters `in_preparation` state.
- **Digital:** refundable per `is_refundable_after_delivery` flag on the detail row.

Never inline this logic — always call `RefundPolicyService->policyFor($productType)` and apply the returned `RefundPolicy` value object.

## Vendor permissions

Per-type permissions exist:

- `service.create.rental.own`, `service.update.rental.own`, etc.
- (same for `sale` and `digital`)

A vendor can be approved for some types and not others (`vendor_approved_product_types`). Permission checks must respect this — `Gate::authorize('service.create.rental.own', $vendor)` for example.

## Commission rates

Stored in basis points in `commission_rates` with discriminators `(category_id, product_type)`. Most-specific match wins:

1. `(category × type)` — most specific
2. `(category × NULL)` — any type within this category
3. `(NULL × type)` — any category for this type
4. `(NULL × NULL)` — global default

Snapshot the resolved rate onto `booking_items.commission_bps` at booking time.
