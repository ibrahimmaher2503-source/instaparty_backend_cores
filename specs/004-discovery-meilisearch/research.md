# Research: Discovery — Meilisearch Search & Wishlists

**Date**: 2026-04-30
**Status**: Complete — no NEEDS CLARIFICATION markers remain

---

## R1 — Meilisearch Bilingual Indexing Strategy

**Decision**: Flatten JSON translatable fields in `toSearchableArray()`. Arabic and English are stored in separate top-level index fields: `name_en`, `name_ar`, `short_description_en`, `short_description_ar`.

**Rationale**: Meilisearch tokenizes all text in `searchableAttributes`. Flattening allows locale-specific searchable attribute ranking without custom analyzers. Meilisearch's built-in Unicode tokenizer handles Arabic script natively (right-to-left, word boundaries).

**`toSearchableArray()` shape** (added to `Service` model in Catalog module):
```php
public function toSearchableArray(): array
{
    return [
        'id'                       => $this->id,
        'public_id'                => $this->public_id,
        'name_en'                  => $this->getTranslation('name', 'en'),
        'name_ar'                  => $this->getTranslation('name', 'ar'),
        'short_description_en'     => $this->getTranslation('short_description', 'en'),
        'short_description_ar'     => $this->getTranslation('short_description', 'ar'),
        'product_type'             => $this->product_type->value,
        'category_id'              => $this->category_id,
        'vendor_id'                => $this->vendor_profile_id,
        'occasion_ids'             => $this->occasions->pluck('id')->toArray(),
        'price_minor'              => $this->base_price_minor,
        'currency'                 => $this->base_price_currency,
        'status'                   => $this->status->value,
        'is_active'                => $this->status->value === 'published',
        'rating_avg'               => (float) $this->rating_avg,
        'vendor_rating'            => optional($this->vendorProfile)->rating_avg ?? 0.0,
        'requires_electricity'     => optional($this->rentalDetail)->requires_electricity,
        'requires_outdoor_space'   => optional($this->rentalDetail)->requires_outdoor_space,
        'is_perishable'            => optional($this->saleDetail)->is_perishable,
        'allows_customization'     => optional($this->saleDetail)->allows_customization,
        'delivery_method'          => optional($this->digitalDetail)->delivery_method?->value,
        'has_expiry'               => optional($this->digitalDetail)->has_expiry,
    ];
}
```

**Eager loading required**: `Service` model's `shouldBeSearchable()` returns `$this->status->value === 'published'`. Scout queues the `toSearchableArray()` job only for published services.

**Alternatives considered**: Meilisearch multilingual tokenizer with `languageDetection` — available in Meilisearch v1.6+ cloud but not self-hosted without a plugin. Rejected in favour of explicit per-locale fields (simpler, self-hosted compatible).

---

## R2 — Meilisearch Index Settings Registration

**Decision**: Index settings registered in `DiscoveryServiceProvider::boot()` via a one-time idempotent call to the Meilisearch HTTP client. Settings are version-controlled (not schema migrations).

**Settings to register**:
```php
$client = app(\MeiliSearch\Client::class);
$index  = $client->index('services');

$index->updateSearchableAttributes([
    'name_ar', 'name_en',
    'short_description_ar', 'short_description_en',
]);

$index->updateFilterableAttributes([
    'product_type', 'category_id', 'occasion_ids',
    'vendor_id', 'is_active', 'price_minor', 'currency',
    'requires_electricity', 'requires_outdoor_space',
    'is_perishable', 'allows_customization',
    'delivery_method', 'has_expiry',
]);

$index->updateSortableAttributes([
    'price_minor', 'vendor_rating', 'rating_avg',
]);
```

**When to call**: Wrapped in a `try/catch`; silently skipped if Meilisearch is unavailable (important for CI environments without Meilisearch). Can be re-run idempotently.

**Alternatives considered**: Artisan command for index settings — acceptable but `ServiceProvider::boot()` ensures settings are always in sync on deploy.

---

## R3 — Zero-Downtime Re-index via Index Swap

**Decision**: Use Meilisearch's `swapIndexes` API. Create a temporary index `services_temp`, import all published services into it, then atomically swap with the live `services` index.

**Filament Action implementation** (`ReindexServicesAction`):
```php
public function execute(): void
{
    // 1. Configure the temp index with same settings
    $client->createIndex('services_temp', ['primaryKey' => 'id']);
    // copy settings ...

    // 2. Import all published services into temp
    Service::published()->searchable();  // Scout queue dispatches to services_temp if configured

    // 3. Swap (atomic)
    $client->swapIndexes([['services', 'services_temp']]);

    // 4. Drop temp (now contains old data)
    $client->deleteIndex('services_temp');
}
```

**Phase 3.0 simplification**: For Phase 3.0, using `php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"` directly (which truncates and re-imports, causing brief gap) is acceptable. Full index-swap pattern documented for Phase 6.0 hardening.

**Alternatives considered**: Artisan command only (no Filament) — rejected (admin needs UI trigger without SSH access).

---

## R4 — Wishlist Lazy Creation

**Decision**: Each customer has exactly one default wishlist, created on first `AddToWishlistAction` call via `Wishlist::firstOrCreate(['user_id' => $userId], ['name' => 'Default'])`.

**Idempotency**: `wishlist_items` has UNIQUE constraint on `(wishlist_id, service_id)`. Duplicate add returns the existing item (no error). Use `insertOrIgnore()` or catch `UniqueConstraintViolationException`.

**Pattern in `AddToWishlistAction`**:
```php
return DB::transaction(function () use ($dto) {
    $wishlist = Wishlist::firstOrCreate(
        ['user_id' => $dto->userId],
        ['public_id' => Str::ulid(), 'name' => 'Default']
    );

    WishlistItem::firstOrCreate([
        'wishlist_id' => $wishlist->id,
        'service_id'  => $dto->serviceId,
    ]);

    DB::afterCommit(fn() => null);  // no domain event needed for wishlist add
    return $wishlist;
});
```

**Alternatives considered**: Named wishlists (multiple per customer) — deferred to Phase 1.5 per spec cut-list. No wishlist table (store as user preferences JSON) — rejected (spec defines discrete `wishlists` and `wishlist_items` tables).

---

## R5 — `SearchServicesAction` with Scout + Filters

**Decision**: Use `Service::search($query)` with chained `.where()` filters (Laravel Scout fluent API). For Meilisearch, this translates to filter syntax `product_type = rental AND occasion_ids IN [1, 2]`.

**Filter mapping** (from HTTP request params to Meilisearch filters):

| Query param | Meilisearch filter |
|---|---|
| `type=rental` | `product_type = rental` |
| `category=5` | `category_id = 5` |
| `occasion=birthday` | `occasion_ids IN [<id>]` (resolve slug → id first) |
| `vendor=abc` | `vendor_id = <id>` (resolve public_id → id) |
| `price_max=50000` | `price_minor <= 50000` |

**Locale resolution for search**: The API returns results in the customer's locale. `ServiceSearchResultResource` reads `$result['name_ar']` or `$result['name_en']` based on `app()->getLocale()`. The search query is submitted as-is (Meilisearch handles both scripts in one query against both `name_en` and `name_ar`).

**Pagination**: Uses Scout's `paginate($perPage, 'page', $page)` returning a `LengthAwarePaginator`.

**Alternatives considered**: Raw Meilisearch HTTP client — rejected (Scout abstraction already configured; Scout's fluent API is sufficient for Phase 3.0 filters). ElasticSearch — locked decision: Meilisearch only.

---

## R6 — `search_logs` Append-Only

**Decision**: `search_logs` migration uses `$table->timestamp('created_at')->useCurrent()` only (no `updated_at`). No soft deletes. Logs are written by `LogSearchQueryListener` after `ServiceSearchPerformed` event fires.

**What is logged**: `user_id` (nullable for anonymous), `query` string, `locale`, `filters` JSON, `results_count`. No PII beyond `user_id`.

**Phase 6.0**: Analytics queries on `search_logs` (top queries, zero-result searches). Not built in Phase 3.0.

---

## R7 — Scout Queue Configuration

**Decision**: `SCOUT_QUEUE=true` in `.env`. Index update jobs dispatch to the `default` queue and are processed by `php artisan queue:work`. This prevents search indexing from blocking HTTP responses.

**What triggers Scout indexing**:
- `Service::created()` / `Service::updated()` — Scout auto-dispatches via model observer when `shouldBeSearchable()` returns `true`
- `ServicePublished` domain event from Catalog — `DiscoveryServiceProvider` registers a listener that calls `$service->searchable()`
- `ServiceArchived` event — listener calls `$service->unsearchable()`

**Alternatives considered**: Synchronous indexing (`SCOUT_QUEUE=false`) — rejected (slow HTTP responses during batch updates; unsafe for import flows).

---

## R8 — Migration Dependency Order

Discovery tables depend on: `users` (Framework), `services` (Catalog).

Required order within Discovery module:

1. `wishlists` — FK to `users`
2. `wishlist_items` — FK to `wishlists`, `services`
3. `saved_searches` — FK to `users`
4. `search_logs` — FK to `users` (nullable), `services` (clicked_service_id, nullable)

No `public_id` on `saved_searches` or `search_logs` (internal-only tables with no external API surface in Phase 3.0 — consistent with schema spec).

`wishlists` and `wishlist_items` need `public_id` (exposed in API responses).

---

## R9 — Filament Re-index Action Location

**Decision**: `ReindexServicesAction` is a Filament table header action registered on an existing Resource (e.g., `RentalServiceResource`) — not a standalone page. This avoids creating a new Filament resource just for re-indexing.

**Implementation**: In `RentalServiceResource::table()`, add a `HeaderAction`:
```php
->headerActions([
    Action::make('reindex')
        ->label(__('discovery.reindex_services'))
        ->icon('heroicon-o-arrow-path')
        ->color('warning')
        ->requiresConfirmation()
        ->action(fn () => app(ReindexServicesAction::class)->execute())
        ->visible(fn () => auth()->user()->can('manage_search_index')),
])
```

The same action is registered on all three service resources. `shield:generate --all` creates the `manage_search_index` permission.
