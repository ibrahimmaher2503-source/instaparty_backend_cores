# Quickstart: Admin Booking View

## Preconditions

- Existing Booking module migrations and seed/factory support are available.
- Admin user can authenticate into `/admin`.
- Representative booking data exists with customer, occasion, vendors, items, address, payment, snapshot, and state transition records.

## Manual Verification

1. Open `/admin/bookings`.
2. Confirm the booking list shows customer name with phone context, not raw customer ID.
3. Confirm the occasion column shows a localized occasion name, not raw occasion ID.
4. Open a booking row.
5. Confirm the booking detail page opens at `/admin/bookings/{record}`.
6. Confirm these six relation sections are visible:
   - Booking Vendors
   - Booking Items
   - Booking Addresses
   - Payments
   - Booking Snapshots
   - Booking State Transitions
7. Confirm Booking Vendors displays vendor business names.
8. Confirm Booking Items includes rental, sale, and digital rows when seeded.
9. Confirm Payments, Snapshots, and State Transitions are read-only.
10. Switch admin locale to Arabic and confirm translatable labels/names remain readable.

## Automated Verification

Run focused tests:

```bash
./vendor/bin/pest tests/Feature/Modules/Booking/AdminBookingViewTest.php
```

Run required quality gates before commit:

```bash
php artisan pint
./vendor/bin/phpstan analyse
./vendor/bin/pest --bail
```

## Expected Outcome

- Admin can drill into any booking from `/admin/bookings`.
- All six relation managers show booking-scoped data or normal empty states.
- No raw customer, occasion, or vendor IDs remain in primary list/detail labels when related records exist.
- Query growth guard passes for representative booking data.
