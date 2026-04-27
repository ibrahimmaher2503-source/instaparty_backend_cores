# Quickstart: Phase 1 — Identity & Vendor Onboarding

## Prerequisites

- Phase 0 complete: Geography module seeded, Filament running at `/admin`
- Docker services running: `docker compose up -d`
- `.env` configured (DB, Redis, S3/MinIO)

## Running migrations

Migrations are already in `app/Modules/Identity/Database/Migrations/` and loaded via `IdentityServiceProvider`.

```bash
php artisan migrate
```

Expected tables after migrate:
- `vendor_profiles`, `vendor_documents`, `vendor_approved_product_types`
- `vendor_business_hours`, `customer_profiles`, `customer_addresses`
- `user_devices`, `two_factor_secrets`
- `vendor_coverage_areas` (from Geography module)

## Seeding roles & permissions

```bash
php artisan db:seed --class=IdentityRolesSeeder
```

Creates: `customer`, `vendor`, `admin` roles + all 12 per-type service permissions + admin approval permissions.

## Running tests

```bash
./vendor/bin/pest --group=identity
./vendor/bin/pest tests/Feature/Modules/Identity/
./vendor/bin/pest tests/Unit/Modules/Identity/
```

## Filament Shield (run after all resources created)

```bash
php artisan shield:generate --all
```

## Verifying the approval flow manually

```bash
# 1. Register a vendor
curl -X POST http://localhost/api/v1/register/vendor \
  -H "Content-Type: application/json" \
  -H "Accept-Language: ar" \
  -d '{"name":"Test Vendor","phone_e164":"+201000000001","password":"password123","password_confirmation":"password123","business_name":{"en":"Test Co","ar":"شركة تجريبية"},"business_type":"individual","primary_governorate_id":1,"primary_city_id":1}'

# 2. Log in as admin in Filament: http://localhost/admin
# 3. Navigate to Vendors → Approval Queue
# 4. Click "Approve for Rental" on the test vendor
# 5. Verify vendor_approved_product_types row created
# 6. Verify vendor has service.create.rental.own permission:
#    php artisan tinker
#    $user = \App\Modules\Identity\Domain\Models\User::where('phone_e164', '+201000000001')->first();
#    $user->hasPermissionTo('service.create.rental.own'); // true
```

## Common issues

| Issue | Fix |
|---|---|
| `vendor_coverage_areas` table not found | Run `php artisan migrate` — Geography module's migration 000005 creates it |
| Shield permissions not appearing | Run `php artisan shield:generate --all` and clear cache |
| OTP code not working in tests | Use `000000` in non-production — StubOtpGateway accepts any 6-digit code in tests |
| `Accept-Language` header ignored | Ensure `SetLocaleMiddleware` is registered in `IdentityServiceProvider` and applied to API routes |
