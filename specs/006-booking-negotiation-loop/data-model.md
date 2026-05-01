# Data Model: Booking Negotiation Loop

**Date**: 2026-04-30

---

## Existing Tables (unchanged, reference only)

These tables were created in Phase 3.1 and are used by this phase without modification:

| Table | Relevant Role in Negotiation |
|---|---|
| `bookings` | Aggregate root; `lifecycle_status` drives the negotiation FSM |
| `booking_vendors` | Per-vendor sub-aggregate; `sub_status` tracks vendor response |
| `booking_items` | Line items; modified in-place when customer accepts vendor changes |
| `booking_state_transitions` | Append-only audit trail; new rows appended on every negotiation event |
| `booking_snapshots` | Append-only versioned read model; new rows on every negotiation event |
| `booking_locks` | Pessimistic locking; used during submit and modification |
| `idempotency_keys` | 24h TTL idempotency store; used for submit and confirm-modification |

---

## New Migrations Required

### Migration 1: `booking_modifications`

```php
Schema::create('booking_modifications', function (Blueprint $table): void {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
    $table->bigIncrements('id');
    $table->char('public_id', 26)->unique();
    $table->foreignId('booking_vendor_id')->constrained('booking_vendors')->restrictOnDelete();
    $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
    $table->enum('proposal_kind', ['add_item','remove_item','change_quantity','change_price','change_slot','add_surcharge','add_note']);
    $table->enum('status', ['pending','customer_accepted','customer_rejected','withdrawn','expired'])->default('pending');
    $table->timestamp('customer_decision_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->json('vendor_explanation')->nullable();   // translatable
    $table->json('diff_snapshot');                    // {before:{…}, after:{…}}
    $table->timestamps();

    $table->index(['booking_vendor_id', 'status']);
    $table->index(['status', 'expires_at']);          // expiry job
});
```

### Migration 2: `booking_modification_items`

```php
Schema::create('booking_modification_items', function (Blueprint $table): void {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';
    $table->bigIncrements('id');
    $table->foreignId('booking_modification_id')->constrained('booking_modifications')->cascadeOnDelete();
    $table->foreignId('target_booking_item_id')->nullable()->constrained('booking_items')->nullOnDelete();
    $table->enum('change_kind', ['add','remove','update']);
    $table->json('payload');                          // proposed new values
    $table->timestamp('created_at')->useCurrent();    // append-only: no updated_at

    $table->index('booking_modification_id');
    $table->index('target_booking_item_id');
});
```

---

## New Eloquent Models

### `BookingModification`

```
app/Modules/Booking/Domain/Models/BookingModification.php
```

**Key @property annotations:**
- `public_id: string`
- `booking_vendor_id: int`
- `proposed_by: int`
- `proposal_kind: ModificationProposalKind`
- `status: ModificationStatus`
- `customer_decision_at: Carbon|null`
- `expires_at: Carbon|null`
- `vendor_explanation: array<string,string>|null` (translatable JSON)
- `diff_snapshot: array<string,mixed>` (JSON)

**Relationships:** `bookingVendor()` BelongsTo, `items()` HasMany BookingModificationItem, `proposedByUser()` BelongsTo User

**Casts:** `proposal_kind` → `ModificationProposalKind`, `status` → `ModificationStatus`, `vendor_explanation` → `array`, `diff_snapshot` → `array`, `customer_decision_at` → `datetime`, `expires_at` → `datetime`

---

### `BookingModificationItem`

```
app/Modules/Booking/Domain/Models/BookingModificationItem.php
```

**Key @property annotations:**
- `booking_modification_id: int`
- `target_booking_item_id: int|null`
- `change_kind: ModificationChangeKind`
- `payload: array<string,mixed>`

**Relationships:** `modification()` BelongsTo BookingModification, `targetItem()` BelongsTo BookingItem

**Casts:** `change_kind` → `ModificationChangeKind`, `payload` → `array`

**Note**: Append-only — no `updated_at`, no soft deletes.

---

## New Enums

### `ModificationStatus`

```php
enum ModificationStatus: string {
    case Pending = 'pending';
    case CustomerAccepted = 'customer_accepted';
    case CustomerRejected = 'customer_rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
```

### `ModificationProposalKind`

```php
enum ModificationProposalKind: string {
    case AddItem = 'add_item';
    case RemoveItem = 'remove_item';
    case ChangeQuantity = 'change_quantity';
    case ChangePrice = 'change_price';
    case ChangeSlot = 'change_slot';
    case AddSurcharge = 'add_surcharge';
    case AddNote = 'add_note';
}
```

### `ModificationChangeKind`

```php
enum ModificationChangeKind: string {
    case Add = 'add';
    case Remove = 'remove';
    case Update = 'update';
}
```

---

## State Machine

### Booking `lifecycle_status` FSM

```
draft
  ──submit──▶ submitted ──[immediate]──▶ vendor_review
                                              │
                             ┌────────────────┴────────────────────────┐
                             │ (all vendors pending)                   │
                      vendor modifies                                  │
                             │                                         │
                             ▼                                         │
                     customer_review                           all vendors accept
                             │                                         │
                    customer accepts/rejects                           │
                             │                                         │
                             └──────▶ vendor_review ◀─────────────────┘
                                           │
                                    all rejected
                                           │
                                        cancelled          confirmed
```

### `booking_vendor.sub_status` FSM

```
pending ──accept──▶ accepted
pending ──modify──▶ modified ──customer rejects──▶ pending
pending ──reject──▶ rejected
```

---

## BookingModification Constraint

Only ONE `booking_modifications` row per `booking_vendor` may have `status = pending` at a time. Enforced at the application layer (checked at the top of `VendorModifyBookingAction.execute()`): if a pending modification exists, abort with 409.

---

## New Domain Events

| Event | Fired By | Payload |
|---|---|---|
| `BookingSubmittedToVendor` | SubmitBookingAction (once per booking_vendor) | `$bookingVendor, $booking` |
| `VendorAccepted` | VendorAcceptBookingAction | `$bookingVendor` |
| `VendorModificationProposed` | VendorModifyBookingAction | `$modification` |
| `VendorRejected` | VendorRejectBookingAction | `$bookingVendor` |
| `CustomerModificationDecided` | CustomerConfirmModifiedBookingAction | `$modification, $decision` |
| `BookingConfirmed` | VendorAcceptBookingAction (when all accepted) | `$booking` |
| `BookingCancelled` | VendorRejectBookingAction (when all rejected) | `$booking` |

---

## New Listeners

| Listener | Listens To | Action |
|---|---|---|
| `WriteNegotiationSnapshotListener` | All 7 events above | Appends a new `booking_snapshots` row |
| `ConfirmInventoryReservationsListener` | `BookingConfirmed` | DB::table → `held` → `confirmed` for Rental items |
| `ReleaseInventoryOnCancellationListener` | `BookingCancelled` | DB::table → `held` → `released` for Rental items |
