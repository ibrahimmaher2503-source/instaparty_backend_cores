# Phase 1 Data Model: Vendor Onboarding Checklist Widget

**Feature**: `034-vendor-onboarding-checklist`
**Date**: 2026-05-16

**No schema changes.** This feature reads existing tables only. The "data model" delivered here is the in-memory DTO shape returned by `VendorOnboardingChecklistService::forVendor()`.

---

## Tables read (no writes)

| Source | Module | Columns read | Aggregate type |
|---|---|---|---|
| `vendor_profiles` | Identity | `id, business_name, business_type, commercial_register_no, tax_id, national_id, primary_governorate_id, primary_city_id, address_line, bank_name, bank_account_holder, bank_iban, approval_status, suspended_at, rejection_reason` | single row (authenticated vendor) |
| `vendor_documents` | Identity | `doc_type, status, expires_at` | `groupBy(doc_type) → latest per type`, scoped to vendor |
| `vendor_approved_product_types` | Identity | `product_type` | all rows for vendor |
| `vendor_business_hours` | Identity | `id` | `exists()` |
| `vendor_coverage_areas` | Identity | `id` | `exists()` |
| `services` (via `VendorServicePresenceQuery` contract) | Catalog | `id, status` | two `exists()` checks |

Total budget: **6 queries**, satisfying FR-EXT-017.

All FKs (`vendor_profile_id`) are already indexed per the locked schema (`docs/specs/11_DB_Schema.md`). `services.vendor_profile_id` + `services.status` composite index already exists for the catalog browse path; the same index serves the presence check.

---

## DTOs

### `App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO`

```php
final readonly class VendorOnboardingChecklistDTO
{
    public function __construct(
        public int $vendorId,
        public bool $isSuspended,
        public ?CarbonImmutable $suspendedAt,
        public ?string $suspensionReason,           // pre-translated
        public RejectionState $rejectionState,      // NONE | CHANGES_REQUESTED | REJECTED
        public ?string $rejectionReason,            // pre-translated
        /** @var array<int, VendorOnboardingChecklistItemDTO> exactly 10 items, fixed order */
        public array $items,
        public int $completedCount,
        public int $totalCount,                     // always 10
        public int $progressPercent,                // intdiv($completedCount * 100, $totalCount)
        public ?VendorOnboardingChecklistItemDTO $nextRecommendedAction,
        /** @var array<int, ProductType> */
        public array $approvedProductTypes,
    ) {}
}
```

### `App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO`

```php
final readonly class VendorOnboardingChecklistItemDTO
{
    public function __construct(
        public VendorOnboardingChecklistItemKey $key,    // enum, see below
        public ChecklistItemStatus $status,              // COMPLETE | PENDING | WARNING | DANGER | INFO
        public string $label,                            // pre-translated
        public ?string $subText,                         // pre-translated
        public ?string $url,                             // Filament URL to the vendor page; null when COMPLETE
    ) {}
}
```

### `App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey`

```php
enum VendorOnboardingChecklistItemKey: string
{
    case Profile           = 'profile';
    case Banking           = 'banking';
    case DocsUploaded      = 'docs_uploaded';
    case DocsApproved      = 'docs_approved';
    case Coverage          = 'coverage';
    case Hours             = 'hours';
    case ServiceDrafted    = 'service_drafted';
    case ServiceSubmitted  = 'service_submitted';
    case ApprovalStatus    = 'approval_status';
    case ApprovedTypes     = 'approved_types';
}
```

Fixed display order matches FR-EXT-002. The rejection banner is rendered separately and is not an enum case.

### `App\Modules\Identity\Domain\Enums\ChecklistItemStatus`

```php
enum ChecklistItemStatus: string
{
    case Complete = 'complete';   // green
    case Pending  = 'pending';    // grey/neutral
    case Warning  = 'warning';    // yellow (e.g., docs uploaded but expired)
    case Danger   = 'danger';     // red (e.g., docs rejected)
    case Info     = 'info';       // blue (e.g., awaiting admin review)
}
```

### `App\Modules\Identity\Domain\Enums\RejectionState`

```php
enum RejectionState: string
{
    case None              = 'none';
    case ChangesRequested  = 'changes_requested';
    case Rejected          = 'rejected';
}
```

---

## Per-row completion rules

| Key | Source(s) | Complete when |
|---|---|---|
| `Profile` | `vendor_profiles` | All of: `business_name` (en+ar populated), `business_type`, `primary_governorate_id`, `primary_city_id`, `address_line` (en+ar populated), AND the per-`business_type` gov-ID columns (see Decision 0.4 in research.md). |
| `Banking` | `vendor_profiles` | `bank_name`, `bank_account_holder`, `bank_iban` all non-empty (Decision 0.3). |
| `DocsUploaded` | `vendor_documents` + `RequiredVendorDocumentTypesResolver` | For each required `doc_type` per `business_type`, at least one `vendor_documents` row exists (any status). |
| `DocsApproved` | `vendor_documents` | For each required type, the latest row has `status='approved'` AND (`expires_at IS NULL` OR `expires_at > now()`). Any required type with status `rejected` → `Danger`. Any required type with status `pending` → `Info`. Any approved type with `expires_at <= now()` → `Warning`. |
| `Coverage` | `vendor_coverage_areas` | At least one row exists for the vendor. |
| `Hours` | `vendor_business_hours` | At least one row exists for the vendor (any weekday). |
| `ServiceDrafted` | `services` (via contract) | `hasAnyService(vendor_profile_id) === true`. |
| `ServiceSubmitted` | `services` (via contract) | `hasServiceInReviewOrPublished(vendor_profile_id) === true`. |
| `ApprovalStatus` | `vendor_profiles.approval_status` | Status is `Complete` when `approval_status='approved'`. Status is `Info` when `pending`. Status is `Warning` when `changes_requested`. Status is `Danger` when `rejected`. (Suspended → DTO returns the suspended variant; this row is hidden.) |
| `ApprovedTypes` | `vendor_approved_product_types` | At least one row exists. SubText lists the approved types (rental / sale / digital) in the active locale. |

The rejection banner (above the checklist) appears when `approval_status IN ('rejected', 'changes_requested')`, sourced from `vendor_profiles.rejection_reason` JSON in `app()->getLocale()`.

---

## URL resolution (for `VendorOnboardingChecklistItemDTO::$url`)

When an item is not complete, the DTO carries the destination URL for its "Go to step" link. The URLs are resolved via Filament's URL helpers against the existing vendor pages:

| Item key | Destination |
|---|---|
| `Profile` | `VendorProfilePage::getUrl()` |
| `Banking` | `VendorAccountPage::getUrl()` (banking sub-form) |
| `DocsUploaded`, `DocsApproved` | `VendorDocumentsPage::getUrl()` |
| `Coverage` | `VendorCoverageAreasPage::getUrl()` |
| `Hours` | `VendorBusinessHoursPage::getUrl()` |
| `ServiceDrafted`, `ServiceSubmitted` | `VendorRentalServiceResource::getUrl('index')` (defaulted to rental as the most common Phase 1 type; alt: pick the first approved type if available) |
| `ApprovalStatus` | `null` (read-only) |
| `ApprovedTypes` | `null` (read-only) |

The rejection banner's CTA links to `VendorProfilePage::getUrl()`.

---

## Sort & priority order for "Next recommended action"

Per FR-EXT-005, the recommended action is the first **incomplete** item in this priority order (admin-decision rows never become the recommended action):

1. `Profile`
2. `DocsUploaded`
3. `Banking`
4. `Coverage`
5. `Hours`
6. `ServiceDrafted`
7. `ServiceSubmitted`

(Order rationale: profile first because every other gate depends on it. Documents before banking because documents drive admin approval, and admin approval gates "submitted for review" being meaningful. Coverage and hours next because they're cheap. Service drafting and submission last because they require all prior data to be useful.)

If every item in the priority list is complete, `nextRecommendedAction` is `null` and the widget shows the "Onboarding complete" chip (FR-EXT-016).
