# Contract: `VendorServicePresenceQuery`

**Feature**: `034-vendor-onboarding-checklist`
**Date**: 2026-05-16

This is a **public cross-module contract** owned by the **Catalog** module and consumed by the **Identity** module. It exists to preserve module boundaries (Constitution Principle I): Identity must not import `App\Modules\Catalog\Domain\Models\Service` directly.

---

## Interface

```php
namespace App\Modules\Catalog\Domain\Contracts;

interface VendorServicePresenceQuery
{
    /**
     * Returns true iff the vendor has at least one non-archived service in any status.
     * Used to satisfy the "Service drafted" checklist row.
     */
    public function hasAnyService(int $vendorProfileId): bool;

    /**
     * Returns true iff the vendor has at least one service with status pending_review OR published.
     * Used to satisfy the "Service submitted for review" checklist row.
     */
    public function hasServiceInReviewOrPublished(int $vendorProfileId): bool;
}
```

## Implementation

```php
namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Catalog\Domain\Models\Service;

final class EloquentVendorServicePresenceQuery implements VendorServicePresenceQuery
{
    public function hasAnyService(int $vendorProfileId): bool
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->exists();
    }

    public function hasServiceInReviewOrPublished(int $vendorProfileId): bool
    {
        return Service::query()
            ->where('vendor_profile_id', $vendorProfileId)
            ->whereIn('status', ['pending_review', 'published'])
            ->exists();
    }
}
```

Bind in `App\Modules\Catalog\Providers\CatalogServiceProvider::register()`:

```php
$this->app->bind(
    VendorServicePresenceQuery::class,
    EloquentVendorServicePresenceQuery::class,
);
```

## Behavioural contract

- **Read-only**: both methods only `SELECT`. No writes, no events fired.
- **Soft deletes**: the underlying `services` table is soft-deletable. Both methods exclude soft-deleted rows by default (Eloquent's `softDeletes()` trait handles this — confirm in implementation phase).
- **Performance**: each method must use `exists()` so MySQL stops at the first matching row. The `(vendor_profile_id, status)` index already exists per the locked schema (`schema-cheatsheet.md` — `services` has `(category_id, product_type, status)` and `(vendor_profile_id, slug)` UNIQUE — confirm `vendor_profile_id` is indexed first, otherwise add an index in implementation).

## Pest test surface

- Confirm `hasAnyService` returns `true` for a vendor with at least one service (any status), `false` otherwise.
- Confirm `hasServiceInReviewOrPublished` returns `true` only for `pending_review` / `published` statuses; `false` for `draft` and `archived`.
- Confirm cross-vendor isolation: Vendor A's services do not satisfy Vendor B's call.
- Confirm soft-deleted services are excluded.

## Architecture-test note

The existing `tests/Architecture/CrossModuleImportTest.php` (or `NoCrossModuleModelImportsTest.php` per the constitution) MUST continue to pass: Identity code references `VendorServicePresenceQuery` (a Catalog **contract**, not a model) — that is allowed.
