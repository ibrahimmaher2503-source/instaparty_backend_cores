# Contract: `VendorOnboardingChecklistService`

**Feature**: `034-vendor-onboarding-checklist`
**Date**: 2026-05-16

This is an **internal Identity-module contract**. It is not exposed to other modules. The widget is the sole consumer.

---

## Public API

```php
namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Domain\Models\VendorProfile;

final class VendorOnboardingChecklistService
{
    public function __construct(
        private readonly RequiredVendorDocumentTypesResolver $requiredDocs,
        private readonly \App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery $services,
    ) {}

    public function forVendor(VendorProfile $vendor): VendorOnboardingChecklistDTO;
}
```

## Behavioural contract

- **Read-only**: the method MUST NOT trigger any `INSERT`, `UPDATE`, or `DELETE`. Verified by a Pest assertion that wraps the call in `DB::connection()->enableQueryLog()` and asserts only SELECTs.
- **Query budget**: ≤ 6 queries per call. Verified by a Pest assertion on the query log.
- **Locale**: the returned DTO's `label`, `subText`, `suspensionReason`, and `rejectionReason` strings are pre-translated for the **currently-active locale** (`app()->getLocale()`). The caller does not need to call `__()` on the returned strings.
- **Suspended vendors**: when `$vendor->approval_status === 'suspended'`, the returned DTO has `isSuspended=true`, `items=[]`, `completedCount=0`, `progressPercent=0`, `nextRecommendedAction=null`, and the suspension banner fields populated. The widget MUST hide the checklist body when `isSuspended=true`.
- **Idempotency**: pure function of `(vendor, app()->getLocale(), database state)`. Two calls in the same request return equal DTOs.

## Invariants

- `count($dto->items) === 10` for any non-suspended vendor.
- `0 <= $dto->completedCount <= 10`.
- `$dto->totalCount === 10`.
- `$dto->progressPercent === intdiv($dto->completedCount * 100, $dto->totalCount)`.
- `$dto->nextRecommendedAction === null` IFF every item in the priority list (Profile → ServiceSubmitted) has `status === Complete`.
- Every item DTO's `url` is either `null` or a valid Filament URL string.

## Pest test surface (see `tests/Unit/Modules/Identity/Application/Services/VendorOnboardingChecklistServiceTest.php`)

- One `it(...)` per row asserting the per-row completion rule from `data-model.md`.
- One test for query-budget ≤ 6.
- One test for cross-vendor isolation (Vendor A's call MUST NOT count Vendor B's documents/services/etc.).
- One test for the suspended-state short-circuit.
- One test for translation: same call with `app()->setLocale('ar')` returns Arabic labels.
