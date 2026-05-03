# Internal Module Contracts (Reviews ↔ Booking, Catalog, Identity)

These are PHP interfaces that the Reviews module **defines** in its `Domain/Contracts/` namespace and other modules **implement** in their `Infrastructure/Repositories/` directories. Reviews depends on the interfaces, never on the implementations.

---

## 1. `Reviews\Domain\Contracts\BookingItemReviewabilityReader`

**Implemented by:** `Booking\Infrastructure\Repositories\EloquentBookingItemReviewabilityReader`
**Bound in:** `BookingServiceProvider::register()`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface BookingItemReviewabilityReader
{
    /**
     * Returns true if the booking item is in 'completed' state, owned by the
     * given user (booking.user_id === userId), and not soft-deleted.
     *
     * @param  string  $bookingItemPublicId  CHAR(26) ULID
     * @param  int     $userId               authenticated user id
     */
    public function isReviewable(string $bookingItemPublicId, int $userId): bool;

    /**
     * Returns the internal id and the service_id for a reviewable booking item.
     * Returns null if the item is not reviewable (caller decides 422 vs 403).
     *
     * @return array{booking_item_id: int, service_id: int}|null
     */
    public function resolveReviewableContext(string $bookingItemPublicId, int $userId): ?array;
}
```

---

## 2. `Reviews\Domain\Contracts\BookingVendorReviewabilityReader`

**Implemented by:** `Booking\Infrastructure\Repositories\EloquentBookingVendorReviewabilityReader`
**Bound in:** `BookingServiceProvider::register()`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface BookingVendorReviewabilityReader
{
    /**
     * Returns true if EVERY booking_items row under this booking_vendor has
     * item_status='completed', the booking_vendor is owned by the user, and
     * neither the booking_vendor nor any underlying booking_item is soft-deleted.
     */
    public function isReviewable(string $bookingVendorPublicId, int $userId): bool;

    /**
     * Resolves to internal ids needed by the Action.
     *
     * @return array{booking_vendor_id: int, vendor_profile_id: int}|null
     */
    public function resolveReviewableContext(string $bookingVendorPublicId, int $userId): ?array;
}
```

---

## 3. `Reviews\Domain\Contracts\ServiceRatingWriter`

**Implemented by:** `Catalog\Infrastructure\Repositories\EloquentServiceRatingWriter`
**Bound in:** `CatalogServiceProvider::register()`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface ServiceRatingWriter
{
    /**
     * Atomically sets services.rating_avg and services.rating_count.
     * The implementation must round rating_avg to 2 decimals to fit DECIMAL(3,2).
     *
     * @param  int    $serviceId   internal id (NOT public_id)
     * @param  float  $newAverage  0.00..5.00
     * @param  int    $newCount    >= 0
     */
    public function update(int $serviceId, float $newAverage, int $newCount): void;
}
```

---

## 4. `Reviews\Domain\Contracts\VendorRatingWriter`

**Implemented by:** `Identity\Infrastructure\Repositories\EloquentVendorRatingWriter`
**Bound in:** `IdentityServiceProvider::register()`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface VendorRatingWriter
{
    public function update(int $vendorProfileId, float $newAverage, int $newCount): void;
}
```

---

## 5. `Reviews\Domain\Contracts\ServiceReviewRepository`

**Implemented by:** `Reviews\Infrastructure\Repositories\EloquentServiceReviewRepository`
**Bound in:** `ReviewsServiceProvider::register()`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

use App\Modules\Reviews\Application\DTOs\SubmitReviewData;
use App\Modules\Reviews\Domain\Models\ServiceReview;

interface ServiceReviewRepository
{
    public function create(SubmitReviewData $data, int $serviceId, int $bookingItemId): ServiceReview;

    public function findByPublicIdForUser(string $publicId, int $userId): ?ServiceReview;

    public function findByBookingItemId(int $bookingItemId): ?ServiceReview;

    /**
     * @param  array{cursor?: string|null, limit?: int}  $options
     * @return array{items: ServiceReview[], next_cursor: ?string, prev_cursor: ?string}
     */
    public function listApprovedForService(int $serviceId, array $options = []): array;

    public function aggregateApprovedForService(int $serviceId): array;
    // returns ['average' => float, 'count' => int]

    public function softDelete(ServiceReview $review): void;

    public function transition(ServiceReview $review, string $toStatus, int $moderatorId): void;
}
```

---

## 6. `Reviews\Domain\Contracts\VendorReviewRepository`

Symmetric to `ServiceReviewRepository`.

---

## 7. `Reviews\Domain\Contracts\ReviewModerationLogRepository`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface ReviewModerationLogRepository
{
    /**
     * Append a single immutable row. The implementation MUST refuse update/delete.
     *
     * @param  array{en: string, ar: string}|null  $reason
     */
    public function append(
        string $reviewType,        // 'service' | 'vendor' | 'response'
        int $reviewId,
        ?string $fromStatus,
        string $toStatus,
        int $moderatorId,
        ?array $reason = null,
    ): void;
}
```

---

## Binding summary (one ServiceProvider per implementing module)

| Implementation | Bound in | Lifetime |
|---|---|---|
| `EloquentBookingItemReviewabilityReader` | `BookingServiceProvider` | singleton |
| `EloquentBookingVendorReviewabilityReader` | `BookingServiceProvider` | singleton |
| `EloquentServiceRatingWriter` | `CatalogServiceProvider` | singleton |
| `EloquentVendorRatingWriter` | `IdentityServiceProvider` | singleton |
| `EloquentServiceReviewRepository` | `ReviewsServiceProvider` | singleton |
| `EloquentVendorReviewRepository` | `ReviewsServiceProvider` | singleton |
| `EloquentReviewModerationLogRepository` | `ReviewsServiceProvider` | singleton |

---

## Boundary rule reminder

The Reviews module's `Application\Actions\*` and `Application\Listeners\*` classes type-hint **only** these interfaces. They never type-hint a concrete `Booking\Domain\Models\BookingItem`, `Catalog\Domain\Models\Service`, or `Identity\Domain\Models\VendorProfile`. The `ReviewsModuleNoCrossImportTest` architecture test enforces this.
