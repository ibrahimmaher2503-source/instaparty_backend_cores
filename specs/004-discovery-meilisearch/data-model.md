# Data Model: Discovery — Meilisearch Search & Wishlists

**Date**: 2026-04-30
**Status**: Schema locked from `docs/specs/11_DB_Schema.md`.

---

## Entity Map

### Wishlist

**Table**: `wishlists`
**Traits**: `HasPublicId`, `HasFactory`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) UNIQUE | ULID |
| user_id | BIGINT FK→users | |
| name | VARCHAR(120) | Default `'Default'` |
| created_at, updated_at | TIMESTAMPS | |

**Relationships**: `belongsTo(User)`, `hasMany(WishlistItem)`

**Scopes**: none needed — one wishlist per user (lazy-created)

---

### WishlistItem

**Table**: `wishlist_items`
**Traits**: `HasFactory`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| wishlist_id | BIGINT FK→wishlists | |
| service_id | BIGINT FK→services | |
| created_at | TIMESTAMP | No `updated_at` |

**Unique constraint**: `(wishlist_id, service_id)` — prevents duplicate adds.

**Relationships**: `belongsTo(Wishlist)`, `belongsTo(Service)` (read-only; Service model is Catalog-owned)

**Note**: No `public_id` — wishlist items are identified by `(wishlist_id, service_id)` pair.

---

### SavedSearch

**Table**: `saved_searches`
**Traits**: `HasFactory`

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK→users | |
| label | VARCHAR(120) | User-defined name |
| filters | JSON | Saved filter snapshot |
| created_at, updated_at | TIMESTAMPS | |

**Phase 3.0**: Table migrated and model declared. No API endpoints. UI deferred to Phase 1.5 (cut-list).

---

### SearchLog (append-only)

**Table**: `search_logs`
**Traits**: none (append-only; no factory needed for log entries)

| Field | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT FK→users | NULL for anonymous |
| query | VARCHAR(255) | Search string |
| locale | ENUM('ar','en') | |
| filters | JSON | Applied filters snapshot |
| results_count | INT UNSIGNED | |
| clicked_service_id | BIGINT FK→services | NULL (click tracking deferred) |
| created_at | TIMESTAMP | Only `created_at`, no `updated_at` |

**Write pattern**: `SearchLog::create([...])` called by `LogSearchQueryListener`. Never updated or soft-deleted.

---

## Scout Index Document Shape

The Meilisearch `services` index primary key is `id` (internal BIGINT, not `public_id`). Documents are shaped by `Service::toSearchableArray()`.

| Index field | Source | Used for |
|---|---|---|
| `id` | `services.id` | Primary key |
| `public_id` | `services.public_id` | Returned in API for routing |
| `name_en` | `services.name->en` | Search (EN queries) |
| `name_ar` | `services.name->ar` | Search (AR queries) |
| `short_description_en` | `services.short_description->en` | Search |
| `short_description_ar` | `services.short_description->ar` | Search |
| `product_type` | `services.product_type` | Filter facet |
| `category_id` | `services.category_id` | Filter |
| `occasion_ids` | eager: `occasions` pivot | Filter (array) |
| `vendor_id` | `services.vendor_profile_id` | Filter |
| `price_minor` | `services.base_price_minor` | Filter (range) |
| `is_active` | `services.status == published` | Default filter |
| `rating_avg` | `services.rating_avg` | Sort |
| `vendor_rating` | `services.vendorProfile.rating_avg` | Sort |
| Type-specific facets | detail tables | Filter (per type) |

---

## Meilisearch Index Settings

```
Index name:          services
Primary key:         id
Searchable:          name_ar, name_en, short_description_ar, short_description_en
Filterable:          product_type, category_id, occasion_ids, vendor_id, is_active,
                     price_minor, currency, requires_electricity, requires_outdoor_space,
                     is_perishable, allows_customization, delivery_method, has_expiry
Sortable:            price_minor, vendor_rating, rating_avg
```

---

## Actions

### SearchServicesAction

**Input**: `SearchServicesDTO` (query, type, category_id, occasion_id, vendor_id, price_max, locale, page, per_page)

**Output**: `LengthAwarePaginator` of Meilisearch hit arrays

**Logic**:
1. Resolve `occasion_id` from slug if provided (DB lookup: `occasions.code = $slug`)
2. Build Scout query: `Service::search($dto->query)->where('is_active', true)->where('product_type', ...)`
3. Paginate: `->paginate($dto->perPage, 'page', $dto->page)`
4. Fire `ServiceSearchPerformed` event (for logging)

**Log event listener**: `LogSearchQueryListener` → creates `SearchLog` record asynchronously.

---

### AddToWishlistAction

**Input**: `userId`, `serviceId`

**Output**: `Wishlist` model

**Logic**:
1. Verify service exists and is published (read from DB)
2. `Wishlist::firstOrCreate(['user_id' => $userId], ['public_id' => Str::ulid(), 'name' => 'Default'])`
3. `WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'service_id' => $serviceId])`
4. Return wishlist

---

### RemoveFromWishlistAction

**Input**: `userId`, `serviceId`

**Output**: `void`

**Logic**:
1. Find wishlist for user (`Wishlist::where('user_id', $userId)->firstOrFail()`)
2. Delete `WishlistItem::where(['wishlist_id' => $wishlist->id, 'service_id' => $serviceId])->delete()`
3. (No domain event — wishlist operations are low-visibility)

---

## Test Plan Summary

### `SearchServicesTest`

| Case | Expected |
|---|---|
| Search returns published services | 200, array of results with `public_id`, `name` in locale |
| `type=rental` filter | Only rental services in results |
| `type=sale` filter | Only sale services in results |
| `type=digital` filter | Only digital services in results |
| Unpublished service does not appear | Zero results for that service's name |
| Paginated response | `meta.total`, `meta.current_page`, `meta.per_page` present |

### `SearchLocaleTest`

| Case | Expected |
|---|---|
| Arabic query `q=نطاطية`, `Accept-Language: ar` | Arabic-named services returned, `name` field in AR |
| English query, `Accept-Language: en` | English-named services returned, `name` field in EN |

### `WishlistTest`

| Case | Expected |
|---|---|
| Add service to wishlist (authenticated) | 201, service appears in wishlist |
| Add same service twice | 200 (idempotent), only one entry |
| Remove service from wishlist | 204, service no longer in wishlist |
| Add to wishlist (unauthenticated) | 401 |
| Remove from wishlist (unauthenticated) | 401 |
