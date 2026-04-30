# Quickstart: Phase 3.1 — Booking Draft & Items

## Prerequisites

- Phase 2.0 complete: Catalog services published in DB
- Phase 1.0 complete: Customers authenticated via Sanctum
- Docker services running: `docker compose up -d`
- `.env` configured (DB, Redis, Queue driver)

## Running Migrations

Migrations live in `app/Modules/Booking/Database/Migrations/` and are loaded via `BookingServiceProvider`.

```bash
php artisan migrate
```

Expected new tables:
- `bookings`, `booking_addresses`, `booking_snapshots`
- `booking_locks`, `booking_vendors`, `booking_items`
- `booking_state_transitions`, `booking_customer_notes`

## Registering the Scheduled Command

Add to `app/Console/Kernel.php` (or `routes/console.php` in Laravel 12):

```php
$schedule->command('booking:release-expired-reservations')->everyMinute();
```

Start the scheduler locally:

```bash
php artisan schedule:work
```

## Running Tests

```bash
# All booking tests
./vendor/bin/pest tests/Feature/Modules/Booking/
./vendor/bin/pest tests/Unit/Modules/Booking/

# Per-group
./vendor/bin/pest --group=booking
./vendor/bin/pest --group=rental
./vendor/bin/pest --group=sale
./vendor/bin/pest --group=digital
```

## Manual Smoke Test

```bash
# 1. Get a customer token
TOKEN=$(curl -s -X POST http://localhost/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"phone_e164":"+201000000002","password":"password123"}' \
  | jq -r '.data.token')

# 2. Create a draft booking
BOOKING=$(curl -s -X POST http://localhost/api/v1/customer/bookings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept-Language: ar" \
  -d '{
    "occasion_id": "<OCCASION_PUBLIC_ID>",
    "event_starts_at": "2026-07-15T18:00:00Z",
    "event_ends_at": "2026-07-15T23:00:00Z",
    "address": {
      "city_id": "<CITY_PUBLIC_ID>",
      "address_line": "12 شارع النيل",
      "recipient_name": "آية ماهر",
      "recipient_phone_e164": "+201012345678"
    }
  }')
echo $BOOKING | jq '.data.public_id'
BOOKING_ID=$(echo $BOOKING | jq -r '.data.public_id')

# 3. Add a rental item
curl -s -X POST http://localhost/api/v1/customer/bookings/$BOOKING_ID/items \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"service_id": "<SERVICE_PUBLIC_ID>", "quantity": 1}' | jq .

# 4. Verify reservation in DB
php artisan tinker
>>> \App\Modules\Catalog\Domain\Models\ServiceInventoryReservation::where('status', 'held')->latest()->first();

# 5. Remove the item
curl -s -X DELETE http://localhost/api/v1/customer/bookings/$BOOKING_ID/items/<ITEM_PUBLIC_ID> \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## Common Issues

| Issue | Fix |
|---|---|
| `booking_snapshots` row missing after draft create | Check queue worker is running: `php artisan queue:work` |
| Reservation not expiring | Check scheduler: `php artisan schedule:work` or manually run `php artisan booking:release-expired-reservations` |
| 409 on add item | Check service `status = published` and inventory is available |
| `BookingLock` UNIQUE constraint violation | Two requests raced for the same resource; retry logic needed at client or Action level |
| `CatalogServiceReader` not bound | Check `BookingServiceProvider::register()` binds the contract |
