# Data Model: Sale + Digital Excel Imports

**Phase**: 6.1 | **Date**: 2026-05-03

---

## No Schema Changes

`excel_imports` and `excel_import_errors` were created in Phase 2.4. The `product_type` ENUM already includes `'sale'` and `'digital'`.

---

## Entities in Scope

### `excel_imports` (Catalog / Imports module)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `public_id` | CHAR(26) ULID | External ID |
| `vendor_profile_id` | BIGINT FK→vendor_profiles | Set from admin form selection or auth vendor |
| `product_type` | ENUM('rental','sale','digital') | `'sale'` or `'digital'` for this feature |
| `status` | ENUM('pending','completed','failed') | Updated within outer transaction |
| `original_filename` | VARCHAR | Original file name |
| `stored_path` | VARCHAR | Temp local path |
| `total_rows` | INT | Set on completion/failure |
| `imported_rows` | INT | Count of rows successfully created |
| `error_rows` | INT | Count of rows that failed validation |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `excel_import_errors` (Catalog / Imports module)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `excel_import_id` | BIGINT FK | |
| `row_number` | INT | 1-indexed from Excel row (2 = first data row) |
| `field` | VARCHAR | Field name that failed |
| `message` | JSON | `{"en": "...", "ar": "..."}` |

### Cascade: `services` + detail tables

On successful sale import, each row creates:
- 1 row in `services` (`product_type='sale'`, `status='draft'`)
- 1 row in `service_sale_details` (FK `service_id`)

On successful digital import, each row creates:
- 1 row in `services` (`product_type='digital'`, `status='draft'`)
- 1 row in `service_digital_details` (FK `service_id`)

---

## Validation Rules Summary

### Sale rows

| Field | Rule |
|---|---|
| `name_en` | required, string, max:255 |
| `name_ar` | required, string, max:255 |
| `short_description_en` | required, string, max:1000 |
| `short_description_ar` | required, string, max:1000 |
| `base_price_minor` | required, integer, min:0 |
| `category_id` | required, integer, min:1 |
| `is_perishable` | required, boolean |
| `is_made_to_order` | required, boolean |
| `lead_time_hours` | required_if:is_made_to_order,1; integer, min:1 (manual post-pass) |
| `stock_quantity` | nullable, integer, min:0 |

### Digital rows

| Field | Rule |
|---|---|
| `name_en` | required, string, max:255 |
| `name_ar` | required, string, max:255 |
| `short_description_en` | required, string, max:1000 |
| `short_description_ar` | required, string, max:1000 |
| `base_price_minor` | required, integer, min:0 |
| `category_id` | required, integer, min:1 |
| `delivery_method` | required, string, in:download,email,api,redemption_code |
| `has_expiry` | required, boolean |
| `expiry_days_after_purchase` | required_if:has_expiry,1; integer, min:1 (manual post-pass) |
| `is_refundable_after_delivery` | required, boolean |
| `redemption_url_template` | nullable, string, max:500 |

---

## State: ExcelImport lifecycle

```
pending → completed   (all rows valid, services created in transaction)
pending → failed      (any row invalid, no services created, errors logged)
```

`completed` and `failed` are final states — no further transitions.
