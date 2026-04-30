# Quickstart: Phase 3.0 — Discovery (Meilisearch)

## Prerequisites

- Phase 2.0 complete: Published services exist in DB with EN + AR names
- Docker services running: `docker compose up -d` (includes Meilisearch service)
- `.env` configured:

```dotenv
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=masterKey
SCOUT_QUEUE=true
```

## Running Migrations

Migrations live in `app/Modules/Discovery/Database/Migrations/` and are loaded via `DiscoveryServiceProvider`.

```bash
php artisan migrate
```

Expected new tables:
- `wishlists`, `wishlist_items`, `saved_searches`, `search_logs`

## Registering Meilisearch Index Settings

On first deploy (or after Meilisearch wipe), boot the service provider to register settings:

```bash
php artisan tinker
>>> app()->boot();  # triggers DiscoveryServiceProvider::boot() which registers index settings
```

Or trigger it via a dedicated Artisan command:

```bash
php artisan scout:sync-index-settings   # if this command is defined
# OR manually via tinker:
>>> app(\Meilisearch\Client::class)->index('services')->updateSearchableAttributes(['name_ar','name_en',...]);
```

## Importing the Initial Index

After running migrations and registering settings, import all published services:

```bash
php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"
```

This takes a few seconds for small datasets. The queue worker must be running for async jobs.

## Running Tests

```bash
# All discovery tests (requires Meilisearch running in Docker)
./vendor/bin/pest tests/Feature/Modules/Discovery/

# Unit tests only (no Meilisearch needed)
./vendor/bin/pest tests/Unit/Modules/Discovery/

# Specific groups
./vendor/bin/pest --group=discovery
./vendor/bin/pest --group=wishlist
./vendor/bin/pest --group=search
```

## Manual Smoke Test

```bash
# 1. Search in Arabic
curl -s "http://localhost/api/v1/customer/services?q=نطاطية&type=rental" \
  -H "Accept-Language: ar" | jq '.meta.total'

# 2. Search in English
curl -s "http://localhost/api/v1/customer/services?q=bouncy+castle&type=rental" \
  -H "Accept-Language: en" | jq '.meta.total'

# 3. Add to wishlist (requires customer token)
TOKEN=$(curl -s -X POST http://localhost/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"phone_e164":"+201000000002","password":"password123"}' | jq -r '.data.token')

curl -s -X POST http://localhost/api/v1/customer/wishlist/items \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"service_id": "<SERVICE_PUBLIC_ID>"}' | jq .

# 4. List wishlist
curl -s http://localhost/api/v1/customer/wishlist \
  -H "Authorization: Bearer $TOKEN" | jq '.meta.total'

# 5. Admin re-index (from Filament admin panel)
# Navigate to /admin → Services (Rental/Sale/Digital) → Re-index Services (header action)
```

## Flush and Re-import Index

```bash
php artisan scout:flush "App\Modules\Catalog\Domain\Models\Service"
php artisan scout:import "App\Modules\Catalog\Domain\Models\Service"
```

## Common Issues

| Issue | Fix |
|---|---|
| `MEILISEARCH_HOST connection refused` | Start Docker: `docker compose up -d meilisearch` |
| Arabic query returns no results | Check `name_ar` is in `searchableAttributes` — re-run settings registration |
| Services not appearing after publish | Check queue worker: `php artisan queue:work` (Scout queue must be processing) |
| Wishlist add returns 404 for service | Check service `status = published` in DB |
| `toSearchableArray()` returns null for occasions | Ensure service model eager-loads `occasions` relationship in `toSearchableArray()` |
