# Research: Booking — Draft Creation & Item Management

**Date**: 2026-04-30
**Status**: Complete — no NEEDS CLARIFICATION markers remain

---

## R1 — Three State Machines on One `item_status` Column

**Decision**: Three separate abstract state hierarchies (`RentalItemStatus`, `SaleItemStatus`, `DigitalItemStatus`), each extending `spatie/laravel-model-states` `State` base. The `BookingItem` model stores `item_status` as `VARCHAR(40)` and resolves the correct state class at runtime via `match($this->product_type)`.

**Rationale**: `spatie/laravel-model-states` requires a declared `$casts` entry mapping a column to a state class. Three `product_type` values share one column, so a single base class won't work. The cleanest pattern: define a `resolveItemState(): State` method on `BookingItem` that uses `match()` to instantiate the right state. State transitions are performed via `$bookingItem->transitionTo($stateClass)` where `$stateClass` is resolved by the caller per type.

**State graphs:**

| Type | States (in order) |
|---|---|
| Rental | `pending_delivery` → `out_for_delivery` → `delivered` → `setup_complete` → `picked_up` |
| Sale | `pending` → `in_preparation` → `ready` → `delivered` |
| Digital | `pending` → `sent` → `redeemed` |

**Phase 3.1 scope**: Items are created in the initial state for their type. State transitions happen in Phase 3.2+ (vendor response / fulfillment). Phase 3.1 only needs the state class hierarchy declared, with all allowed transitions defined. No transitions are triggered by Phase 3.1 actions.

**Alternatives considered**: Three separate columns (`rental_item_status`, `sale_item_status`, `digital_item_status`) with two NULL — rejected (schema already locks `item_status` as `VARCHAR(40)`; nullable columns for non-applicable types violate the single-responsibility intent). Single mega-state class with all possible states — rejected (makes invalid transitions possible across types).

---

## R2 — Pessimistic Locking for Inventory Reservation

**Decision**: Two-level locking strategy inside `DB::transaction`:

1. **Service-level lock**: `Service::lockForUpdate()->find($serviceId)` — prevents concurrent row reads on the specific service during inventory check.
2. **Application-level lock record**: After inventory check passes, create a `BookingLock` record with `lock_purpose = 'payment'` (for items being added during booking submission). For Phase 3.1 cart operations, a lighter lock via the `service_inventory_reservations` table's `SELECT ... FOR UPDATE` on the count query is sufficient.

**Reservation insert pattern inside `AddItemToBookingAction`**:
```
DB::transaction(function () use ($dto) {
    // 1. Lock the service row
    $service = Service::lockForUpdate()->findOrFail($dto->serviceId);
    
    // 2. Check available inventory (for rental/sale; digital = unlimited)
    match ($dto->productType) {
        ProductType::Rental  => $this->checkRentalAvailability($service, $dto),
        ProductType::Sale    => $this->checkSaleAvailability($service, $dto),
        ProductType::Digital => null,  // no inventory check
    };
    
    // 3. Create reservation (TTL 15 min)
    $reservation = ServiceInventoryReservation::create([...]);
    
    // 4. Upsert booking_vendor
    // 5. Create booking_item
    // 6. Recalculate totals
    
    DB::afterCommit(fn() => event(new BookingItemAdded($bookingItem)));
});
```

**Alternatives considered**: Redis-based distributed lock — rejected (adds Redis dependency to what is a DB-level concern; DB transactions are simpler and the `SELECT ... FOR UPDATE` on MySQL is sufficient for Phase 1 scale).

---

## R3 — Reservation Expiry (15-Minute TTL)

**Decision**: `expires_at = now()->addMinutes(15)` set at reservation creation time. A Laravel scheduled command `ReleaseExpiredReservationsCommand` runs every minute via `$schedule->command('booking:release-expired-reservations')->everyMinute()`.

**Command logic**:
```php
ServiceInventoryReservation::where('status', 'held')
    ->where('expires_at', '<', now())
    ->chunkById(200, function ($reservations) {
        foreach ($reservations as $reservation) {
            DB::transaction(function () use ($reservation) {
                $reservation->update(['status' => 'expired', 'released_at' => now()]);
                // optionally: remove orphaned booking_item if booking is still draft
            });
        }
    });
```

**Rationale**: Scheduled command is the simplest reliable approach. Queue-based delayed jobs (via `dispatch(...)->delay(15 * 60)`) could race on failures; the command is idempotent and safe to run repeatedly.

**Alternatives considered**: MySQL events — rejected (not universally available, tied to DB engine). Queue delay job per reservation — rejected (harder to recover on worker restart; command-based sweeper is simpler).

---

## R4 — Booking Total Recalculation

**Decision**: Totals are computed in the database, not in PHP, to avoid stale reads in concurrent sessions.

**Pattern in `RecalculateBookingTotalsListener`** (triggered after `BookingItemAdded` and `BookingItemRemoved`):

```sql
-- Per booking_vendor:
UPDATE booking_vendors bv
SET subtotal_minor = (
    SELECT SUM(line_total_minor) FROM booking_items WHERE booking_vendor_id = bv.id
),
delivery_fee_minor = (
    SELECT delivery_fee_minor FROM vendor_coverage_areas
    WHERE vendor_profile_id = bv.vendor_profile_id AND city_id = :city_id
    LIMIT 1
)
WHERE bv.id = :booking_vendor_id;

-- Per booking:
UPDATE bookings b
SET subtotal_minor = (SELECT SUM(subtotal_minor) FROM booking_vendors WHERE booking_id = b.id),
    delivery_total_minor = (SELECT SUM(delivery_fee_minor) FROM booking_vendors WHERE booking_id = b.id),
    total_minor = subtotal_minor + delivery_total_minor - discount_total_minor
WHERE b.id = :booking_id;
```

**Phase 3.1 simplification**: `line_total_minor = unit_price_minor × quantity`. Pricing tiers deferred.

**Alternatives considered**: Event sourcing (compute from replay) — rejected (over-engineering for Phase 1). Eager computation in Action — rejected (listener decouples the concern and allows future event consumers to trigger the same recalculation).

---

## R5 — Cross-Module Service Data Access

**Decision**: The Booking module must read service data (price, name snapshot, product_type, vendor_profile_id) from the Catalog module. Per module rules, direct model import is forbidden.

**Solution**: Define `CatalogServiceReader` contract in `Booking/Domain/Contracts/CatalogServiceReader.php`. The Catalog module's `ServiceProvider` binds its own repository implementation to this interface. `AddItemToBookingAction` injects `CatalogServiceReader`.

**Interface**:
```php
interface CatalogServiceReader
{
    public function findPublishedById(int $id): ?ServiceReadDTO;
}
```

`ServiceReadDTO` is a plain DTO in `Booking/Application/DTOs/` carrying only the fields needed for booking: `id`, `publicId`, `vendorProfileId`, `productType`, `nameEn`, `nameAr`, `basePriceMinor`, `basePriceCurrency`, `stockQuantity`.

**Alternatives considered**: Direct `Catalog\Domain\Models\Service` import — rejected (explicit module boundary violation per `modules.md`). Firing a domain event and awaiting a response — rejected (too complex for a synchronous read).

---

## R6 — Booking Snapshot at Draft Creation

**Decision**: Write one snapshot at draft creation only (Phase 3.1 scope). Full versioning (one per state change) is Phase 3.2.

**Pattern**: `WriteInitialBookingSnapshotListener` listens to `BookingDraftCreated` event (fired via `DB::afterCommit`). Snapshot `version = 1`, `trigger_kind = 'booking_created'`.

**Snapshot JSON shape**:
```json
{
  "booking": { "public_id": "...", "lifecycle_status": "draft", "event_starts_at": "...", "total_minor": 0 },
  "vendors": [],
  "items": [],
  "address": { "city_id": ..., "address_line": "..." }
}
```

**Alternatives considered**: Synchronous snapshot inside `CreateBookingDraftAction` transaction — rejected (snapshot is a read-model concern, not a write concern; listener separation keeps the Action lean).

---

## R7 — `AddItemToBookingAction` Per-Type Reservation Logic

**Decision**: Single Action handles all three types using `match($dto->productType)`:

```php
match ($dto->productType) {
    ProductType::Rental  => $this->reserveRentalInventory($service, $dto, $booking),
    ProductType::Sale    => $this->reserveSaleInventory($service, $dto, $booking),
    ProductType::Digital => null,  // digital: unlimited, no reservation
};
```

`reserveRentalInventory`: checks overlap with existing `held`/`confirmed` reservations for the same service on the same date range. Uses `reserved_starts_at` / `reserved_ends_at`.

`reserveSaleInventory`: checks `stock_quantity - held_count > 0` using a `COUNT` query on `service_inventory_reservations` with status `in ('held', 'confirmed')`.

**Initial `item_status`**:

```php
$initialStatus = match ($dto->productType) {
    ProductType::Rental  => 'pending_delivery',
    ProductType::Sale    => 'pending',
    ProductType::Digital => 'pending',
};
```

---

## R8 — Migration Dependency Order

Booking tables depend on: `users`, `vendor_profiles` (Identity), `cities` (Geography), `services` (Catalog), `occasions` (Catalog).

Required order within Booking module migrations (timestamp prefix enforces order):

1. `bookings` — FK to `users`, `occasions`
2. `booking_addresses` — FK to `bookings`, `cities`
3. `booking_snapshots` — FK to `bookings`, `users` (triggered_by)
4. `booking_locks` — FK to `users` (locked_by_user_id, nullable)
5. `booking_vendors` — FK to `bookings`, `vendor_profiles`
6. `booking_items` — FK to `booking_vendors`, `services`
7. `booking_state_transitions` — polymorphic (no FK declared on polymorphic columns)
8. `booking_customer_notes` — FK to `bookings`, `users`

**Note**: `booking_modifications` and `booking_modification_items` are NOT created in Phase 3.1 (they are Phase 3.2+ negotiation tables).

---

## R9 — Customer Ownership Check

**Decision**: Every Action verifies that the booking `customer_id = auth()->id()` before mutating. Return 403 if mismatch.

**Pattern**: `BookingRepository::findDraftForCustomer(int $bookingId, int $customerId): ?Booking` — returns null if not found or if `lifecycle_status != 'draft'`. Action throws `BookingNotFoundException` (→ 404) or `BookingNotDraftException` (→ 409).

---

## R10 — Booking Reference Number

**Decision**: `reference_no` format: `IP-{YYYY}-{NNNNNN}` (zero-padded sequential per year). Generated in `CreateBookingDraftAction` using `DB::selectOne('SELECT MAX(id) FROM bookings WHERE ...')` to extract the next suffix.

**Simpler alternative chosen**: Use `'IP-' . date('Y') . '-' . str_pad($booking->id, 6, '0', STR_PAD_LEFT)`. Set in the model's `creating` event (or inside the Action after insert).

**Alternatives considered**: UUID-based reference — rejected (not human-readable for support). Separate sequence table — rejected (YAGNI for Phase 1 volume).
