# Quickstart: Settlement — Wallets, Commissions, Withdrawals

**Feature**: 008-settlement-wallets-commissions-withdrawals | **Phase**: Plan / Phase 1 | **Date**: 2026-05-03

This guide covers running migrations, seeding default commission rates, executing the test suite, and performing an end-to-end smoke test for the Settlement module.

---

## Prerequisites

- Phase 0.0–4.1 already shipped (foundation, identity, catalog, discovery, booking, payments)
- Docker Compose stack running: `docker compose up`
- MySQL/MariaDB reachable from `php artisan tinker`
- Redis reachable (queue worker)
- MinIO running with private bucket configured (`minio_private` disk for bank proof storage)
- `.env` has `QUEUE_CONNECTION=database` (or `redis`)
- Already on branch `008-settlement-wallets-commissions-withdrawals`

---

## Running Migrations

```bash
# Run only Settlement migrations
php artisan migrate --path=app/Modules/Settlement/Database/Migrations

# Or run everything
php artisan migrate
```

Verify the 6 new tables:

```bash
php artisan tinker
> Schema::hasTable('wallets');                    // true
> Schema::hasTable('wallet_ledger');              // true
> Schema::hasTable('commission_rates');           // true
> Schema::hasTable('commissions');                // true
> Schema::hasTable('withdrawals');                // true
> Schema::hasTable('settlement_runs');            // true (empty in Phase 4.2)
```

---

## Seeding Default Commission Rate

```bash
php artisan db:seed --class="App\\Modules\\Settlement\\Database\\Seeders\\DefaultCommissionRatesSeeder"
```

Verify:

```bash
php artisan tinker
> App\Modules\Settlement\Domain\Models\CommissionRate::all();
// → 1 row: (category_id=null, product_type=null, commission_bps=1500)
```

---

## Generating Filament Permissions

After Filament resources are added (`WithdrawalsQueueResource`, `WalletLedgerViewerResource`, `CommissionRulesResource`):

```bash
php artisan shield:generate --all
```

Verify the new permissions appear:

```bash
php artisan tinker
> Spatie\Permission\Models\Permission::where('name', 'like', 'settlement.%')->pluck('name');
// → settlement.view_wallet.own, settlement.view_withdrawals.own,
//   settlement.request_withdrawal.own, settlement.approve_withdrawal,
//   settlement.reject_withdrawal, settlement.manage_commission_rates,
//   ...
```

Assign to admin role:

```bash
> $admin = Spatie\Permission\Models\Role::findByName('admin');
> $admin->givePermissionTo([
    'settlement.approve_withdrawal',
    'settlement.reject_withdrawal',
    'settlement.manage_commission_rates',
  ]);
```

---

## Running Tests

```bash
# All Settlement tests
./vendor/bin/pest tests/Feature/Modules/Settlement tests/Unit/Modules/Settlement

# Just commission flow (parameterized for all 3 product types)
./vendor/bin/pest tests/Feature/Modules/Settlement/CommissionCalculationTest.php

# Just refund reversal
./vendor/bin/pest tests/Feature/Modules/Settlement/RefundReversalTest.php

# Architecture tests
./vendor/bin/pest tests/Architecture/SettlementModuleNoCrossImportTest.php

# Full suite
./vendor/bin/pest --bail
```

---

## Manual Smoke Test (vendor → admin → paid)

### 1. Trigger a `PaymentCaptured` event (simulating a successful Paymob webhook)

```bash
php artisan tinker
> $vendor = App\Modules\Identity\Domain\Models\VendorProfile::first();
> $bookingItem = App\Modules\Booking\Domain\Models\BookingItem::factory()
    ->for($vendor)
    ->withCommissionBps(1500)
    ->withTotal(100000) // 1000 EGP
    ->create();
> $payment = App\Modules\Payments\Domain\Models\Payment::factory()
    ->captured()
    ->for($bookingItem->booking)
    ->create();
> event(new App\Modules\Payments\Domain\Events\PaymentCaptured($payment->id));
```

Wait ~5s for queued listener:

```bash
php artisan queue:work --once
```

Verify:

```bash
> App\Modules\Settlement\Domain\Models\Commission::where('booking_item_id', $bookingItem->id)->first();
// → row with commission_bps=1500, commission_minor=15000, vendor_share_minor=85000
> App\Modules\Settlement\Domain\Models\Wallet::where('owner_id', $vendor->id)->first();
// → balance_minor=85000 (i.e., 850 EGP)
> App\Modules\Settlement\Domain\Models\WalletLedgerEntry::where('wallet_id', $wallet->id)->latest()->first();
// → entry_type=commission_credit, amount_minor=85000
```

### 2. Vendor requests withdrawal

```bash
curl -X POST http://localhost:8000/api/v1/vendor/withdrawals \
  -H "Authorization: Bearer ${VENDOR_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Accept-Language: en" \
  -d '{
    "amount_minor": 80000,
    "currency": "EGP",
    "bank_account": {
      "account_holder": "Ibrahim Maher",
      "iban": "EG800001000000000123456789012",
      "bank_name": "Banque Misr",
      "swift_bic": "BMISEGCXXXX"
    }
  }'
```

Expected: 201 with `data.public_id` and `data.status=pending`.

### 3. Admin approves withdrawal in Filament

1. Open `http://localhost:8000/admin`
2. Log in as admin
3. Navigate to **Settlement → Withdrawals Queue**
4. Click the pending row
5. Click "Approve & Mark Paid"
6. Upload a sample PDF as bank proof
7. Click **Submit**

Verify:

```bash
> $w = App\Modules\Settlement\Domain\Models\Withdrawal::latest()->first();
> $w->status->value;          // → "paid"
> $w->paid_at;                // → recent timestamp
> $w->bank_proof_media_id;    // → non-null
> $vendor->wallet->balance_minor;   // → 5000 (was 85000, now 850 - 800 = 50 EGP after withdrawal)
```

### 4. Trigger a refund (reversal flow)

```bash
> $refund = App\Modules\Payments\Domain\Models\Refund::factory()
    ->completed()
    ->for($payment)
    ->withAmount(50000) // 500 EGP refund (50% of original)
    ->create();
> event(new App\Modules\Payments\Domain\Events\RefundCompleted($refund->id));
```

Wait for queue:

```bash
php artisan queue:work --once
```

Verify:

```bash
> $commission = App\Modules\Settlement\Domain\Models\Commission::where('booking_item_id', $bookingItem->id)->first();
> $commission->status->value;          // → "partially_reversed"
> $commission->reversed_amount_minor;  // → 7500 (50% of 15000)
> $vendor->wallet->refresh()->balance_minor;
// → balance reduced by 50% of vendor_share_minor (= 5000 - 42500 = -37500 EGP, i.e., negative)
> // Audit log will have a warn entry flagging the negative balance
```

---

## Common Issues

### Migration fails: `Table 'media' doesn't exist`
- Spatie Media Library's media table must be migrated first (Phase 0.0).
- Run `php artisan migrate` from the root, not from a subset path.

### Listener doesn't fire
- Confirm `QUEUE_CONNECTION` in `.env` is `database` (or `redis`)
- Run `php artisan queue:work` in a separate terminal
- Check `failed_jobs` table for exceptions: `php artisan queue:failed`

### `commission_rates` resolution returns 0 bps
- Confirm the seeder ran: `php artisan db:seed --class=DefaultCommissionRatesSeeder`
- Or insert a rate manually: `CommissionRate::create(['commission_bps' => 1500])`

### Single-pending rule fires unexpectedly
- The DB-level UNIQUE PARTIAL index allows only one `pending` withdrawal per vendor
- Approve or reject the existing pending withdrawal before testing a new one

### IBAN validation fails
- IBAN format: country code (2 chars) + check digits (2 chars) + BBAN (variable)
- Egypt IBAN: `EG` + 2 digits + 25 alphanumeric (total 29 chars)
- Mod-97 checksum is enforced; use a real valid Egyptian IBAN for smoke tests

---

## Cleanup / Reset

```bash
# Roll back Settlement migrations
php artisan migrate:rollback --path=app/Modules/Settlement/Database/Migrations --step=6

# Or fresh start (destroys all data!)
php artisan migrate:fresh --seed
```
