# Contract — VendorBookingDecisionPage

> Filament Page contract. This feature does **not** expose a public REST endpoint, so no OpenAPI/Bruno entry is required (per spec Assumptions §"No new REST endpoints").

---

## Route

| Aspect | Value |
|---|---|
| Panel | Vendor (`/vendor` — `VendorPanelProvider`) |
| URL pattern | `/vendor/booking-decisions/{bookingVendor}` |
| URL parameter | `bookingVendor` = `booking_vendors.public_id` (CHAR(26) ULID) |
| Auth | Vendor panel guard (Sanctum cookie session) |
| Method | GET (render) + Livewire POST (actions) |
| Navigation registered | NO (`$shouldRegisterNavigation = false`) |

---

## Class

```php
namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Actions\Action;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;

class VendorBookingDecisionPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-decision';

    public string $bookingVendor; // route param (public_id)

    private ?BookingVendor $resolvedBookingVendor = null;
    private bool $isAddressInCoverage = false;
    /** @var array<int,string> service names with active inventory conflicts */
    private array $inventoryConflicts = [];
    private bool $isLocked = false;
    private bool $isReadOnly = false;
    private string $readOnlyReason = '';

    public function mount(string $bookingVendor): void; // resolves + authorises + computes flags

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable;

    public function infolist(Infolist $infolist): Infolist;

    protected function getHeaderActions(): array; // [accept, modify, reject, back]

    // helpers
    private function getRecord(): BookingVendor;
    private function getVendorProfile(): VendorProfile;
    private function resolveCoverage(): void;
    private function resolveInventory(): void;
    private function resolveLocks(): void;
    private function resolveReadOnlyState(): void;
}
```

---

## Behavior contract

### `mount(string $bookingVendor): void`

**Preconditions**: caller authenticated via Vendor panel; URL param matches `booking_vendors.public_id` regex (`^[0-9A-HJKMNP-TV-Z]{26}$`).

**Steps**:
1. Eager-load `BookingVendor` by `public_id` with: `booking.customer`, `booking.address`, `booking.modifications` (latest first), `booking.locks` (active only), `items.service`. **404** if not found.
2. **403** if `$record->vendor_profile_id !== $this->getVendorProfile()->id`.
3. **403** if `auth()->user()->vendorProfile === null`.
4. Compute coverage flag, inventory conflicts, lock flag.
5. Resolve read-only mode (5 conditions per `research.md` R-005).
6. Store all derived state on instance properties.

**Postconditions**: page is ready to render its infolist + header actions.

### `infolist(Infolist $infolist): Infolist`

Returns a Filament Infolist composed of these sections (top to bottom):

1. **Header banner** (conditional): one of `read-only`, `deadline-expired`, `locked`, `cancelled-by-customer`, `no-items`. Hidden when actions are live.
2. **Booking info** — `reference_no`, `event_starts_at`, customer name, sub_status badge, address (locale-resolved).
3. **Coverage** — coverage badge (green/amber/grey) with explanatory text.
4. **Deadline countdown** — live countdown (Alpine.js timer or `wire:poll` every 30s). Color: amber > 2h, red < 2h, danger strike-through expired.
5. **Items** — `RepeatableEntry` over `items` filtered to this vendor. Per-item: type badge, name (locale), quantity, unit price, line total, type-specific snippet.
6. **Customer notes** — locale-resolved JSON; "—" if both blank.
7. **Payment status** — badge from `bookings.payment_status`.
8. **Inventory warning** (conditional) — list of conflicting item names.
9. **Previous modifications** (conditional) — `RepeatableEntry` over `booking.modifications` newest first.

### `getHeaderActions(): array`

Returns an array of Filament `Action` instances:

| Action | Visibility | Behavior on submit |
|---|---|---|
| `accept` | `! $this->isReadOnly` | confirmation modal → `VendorAcceptBookingAction::execute(VendorAcceptDTO)` → success Notification → redirect to `VendorIncomingBookingsPage` |
| `modify` | `! $this->isReadOnly` | if `class_exists(VendorBookingModificationBuilder::class)` → redirect; else → info Notification |
| `reject` | `! $this->isReadOnly` | modal with `Textarea` `reason_en`, `reason_ar` → `VendorRejectBookingAction::execute(VendorRejectDTO)` → success Notification → redirect |
| `back` | always | URL to `VendorIncomingBookingsPage` |

---

## Application Action contract changes (additive)

For each of:
- `App\Modules\Booking\Application\Actions\VendorAcceptBookingAction`
- `App\Modules\Booking\Application\Actions\VendorRejectBookingAction`
- `App\Modules\Booking\Application\Actions\VendorModifyBookingAction`

Add this guard **after** the existing `abort_if(... vendor_profile_id mismatch ...)` and **after** the existing `abort_if(... sub_status !== Pending ...)`:

```php
if ($bookingVendor->response_deadline !== null
    && $bookingVendor->response_deadline->isPast()) {
    throw new ResponseDeadlineExpiredException($bookingVendor->id);
}
```

The throw happens **inside** the `DB::transaction(...)` callback (after the `lockForUpdate()`) so the row state is consistent at the moment of the check.

---

## Page → Action mapping

| Page action | DTO | Action |
|---|---|---|
| `accept` | `new VendorAcceptDTO(bookingVendorId, vendorProfileId, proposedByUserId, idempotencyKey: null)` | `VendorAcceptBookingAction` |
| `reject` | `new VendorRejectDTO(bookingVendorId, vendorProfileId, proposedByUserId, rejectionReason, idempotencyKey: null)` | `VendorRejectBookingAction` |
| `modify` | n/a (handoff only) | `VendorModifyBookingAction` (invoked by future builder) |

---

## Error surface

| Trigger | HTTP / UX | User-facing message (translated) |
|---|---|---|
| Unauthenticated | redirect to vendor login | (Filament default) |
| Vendor without `VendorProfile` | 403 | "Access denied" |
| Wrong vendor's `booking_vendor` | 403 | "Access denied" |
| Already-decided submission (race) | 409 → Filament `danger` notification | `vendor-portal.decision.errors.no_longer_pending` |
| Deadline expired submission | 409 → Filament `danger` notification | `vendor-portal.decision.errors.deadline_expired` |
| Active `booking_locks` row | actions hidden + banner | `vendor-portal.decision.readonly.locked` |
| Parent cancelled/completed | actions hidden + banner | `vendor-portal.decision.readonly.{cancelled,completed}` |

---

## No HTTP API contract

This page is a Filament/Livewire surface, not a REST endpoint. The Constitution's API documentation constraint (Scribe `@bodyParam`, `@response`, `api-registry.md` entry, Bruno collection) does **not** apply here. Should a future Phase ship a public `POST /api/v1/vendor/bookings/{id}/{accept|reject|modify}` endpoint that reuses the same Application Actions, the API documentation constraint will apply at that point and must be addressed in that feature's plan — not here.
