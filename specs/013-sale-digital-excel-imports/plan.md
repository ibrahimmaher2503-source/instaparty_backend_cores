---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Implementation Plan: Sale + Digital Excel Imports

**Branch**: `008-settlement-wallets-commissions-withdrawals` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**Phase**: Phase 6.1 — Sale + Digital Excel Imports (1 day, Week 7)
**PRD**: FR-22 | **Tables**: `excel_imports`, `excel_import_errors` (no schema changes)

---

## Summary

Replicate the Phase 2.4 rental Excel import pattern for the `sale` and `digital` product types. Extends the existing pattern in two directions:

1. **Admin Filament import actions** — header `Action` on `SaleServiceResource` and `DigitalServiceResource` with explicit vendor selection (admin cannot infer vendor from auth).
2. **Vendor API endpoints** — `POST /api/v1/vendor/services/sale/import` and `/digital/import` that infer vendor_profile_id from the authenticated user and reject mismatched `store_id` values.

All import logic uses the identical four-phase pattern from `ImportRentalServicesFromExcelAction`: store file → create pending `ExcelImport` record → validate ALL rows → atomic commit or full rollback with bilingual per-row errors in `excel_import_errors`.

---

## Technical Context

| Field | Value |
|---|---|
| **Language/Version** | PHP 8.3+, Laravel 12 |
| **Primary Dependencies** | `maatwebsite/excel ^3.1` (already installed), Filament v3, Laravel Sanctum |
| **Storage** | MySQL 8 — `excel_imports`, `excel_import_errors`, `services`, `service_sale_details`, `service_digital_details` |
| **Testing** | Pest 3.5 + pest-plugin-laravel |
| **Target Platform** | Laravel API + Filament admin |
| **Performance Goals** | ≤10s for files up to 200 rows |
| **Constraints** | No new migrations; locked 60-table schema (`11_DB_Schema.md`); no new packages |
| **Scale/Scope** | Max ~500 rows per import per `10_Package_List.md §7` rationale |

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Pre-Design | Post-Design | Notes |
|---|---|---|---|---|
| I | Modular Monolith — no cross-module model imports | ✅ PASS | ✅ PASS | Import actions stay in `Catalog`; admin Filament page accesses `VendorProfile` via the same precedent set in Phase 2.4 `ImportRentalServicesPage` (see cross-module note below) |
| II | Three Product Types — `match($enum)`, never if/elseif | ✅ PASS | ✅ PASS | Two separate per-type actions (`ImportSaleServicesFromExcelAction`, `ImportDigitalServicesFromExcelAction`); no shared conditional branching |
| III | Money Discipline — integer minor units | ✅ PASS | ✅ PASS | `base_price_minor` validated as `integer`, passed to `CreateSaleServiceDTO`/`CreateDigitalServiceDTO` which already enforce `int $basePriceMinor` |
| IV | Bilingual EN+AR | ✅ PASS | ✅ PASS | Template columns `name_en`/`name_ar`, `short_description_en`/`short_description_ar` required; `excel_import_errors.message` JSON `{en,ar}` |
| V | Append-Only Tables | ✅ PASS | ✅ PASS | `excel_imports`/`excel_import_errors` are NOT append-only tables; no append-only tables touched |
| VI | Spec-Driven ADR | ✅ N/A | ✅ N/A | No new module; Catalog ADR-0005 already accepted |
| VII | Test-First for Critical Paths | ✅ PASS | ✅ PASS | Happy path + rollback Pest tests for both types; admin + vendor flows covered |
| VIII | Idempotency Keys | ✅ N/A | ✅ N/A | Import is not a payment-mutating endpoint |
| IX | Domain Events DB::afterCommit | ✅ PASS | ✅ PASS | `CreateSaleServiceAction`/`CreateDigitalServiceAction` already use `DB::afterCommit()` — inherited automatically by import actions |
| X | Vendor Approval Two-Step Gate | ✅ PASS | ✅ PASS | Import actions verify vendor approved for `ProductType::Sale` / `ProductType::Digital` before processing rows |
| XI | Document Storage — S3 typing | ✅ N/A | ✅ N/A | Excel upload is a transient processing file; stored locally then discarded |

> **Cross-module note (Principle I):** `ImportRentalServicesPage` (Phase 2.4) already accesses the Identity module's `VendorProfile` model from within the Catalog Filament page via `auth()->user()->vendorProfile`. This pattern is consistent across all module Filament pages and is an accepted pragmatic boundary for the Filament admin layer — the UI layer is not subject to the same strict cross-module isolation as Action/Service/Repository layers. The admin import header action follows the same precedent.

---

## Project Structure

### Documentation (this feature)

```text
specs/013-sale-digital-excel-imports/
├── spec.md
├── plan.md          ← this file
├── research.md
├── data-model.md
├── checklists/
│   └── requirements.md
└── tasks.md         ← generated by /speckit.tasks
```

### Source Code (Catalog module additions)

```text
app/Modules/Catalog/
├── Application/
│   └── Actions/
│       ├── ImportSaleServicesFromExcelAction.php    ← NEW
│       └── ImportDigitalServicesFromExcelAction.php ← NEW
├── Infrastructure/
│   └── Importers/
│       ├── SaleServicesImport.php                  ← NEW
│       └── DigitalServicesImport.php               ← NEW
├── Http/
│   ├── Controllers/
│   │   └── Vendor/
│   │       ├── ImportSaleServicesController.php    ← NEW
│   │       └── ImportDigitalServicesController.php ← NEW
│   └── Requests/
│       ├── ImportSaleServicesRequest.php           ← NEW
│       └── ImportDigitalServicesRequest.php        ← NEW
├── Filament/
│   └── Resources/
│       ├── SaleServiceResource.php                 ← MODIFIED (add header action)
│       └── DigitalServiceResource.php              ← MODIFIED (add header action)
└── Routes/
    └── vendor.php                                  ← MODIFIED (add import endpoints)

tests/Feature/Modules/Catalog/
├── SaleExcelImportTest.php                         ← NEW (action-level tests)
├── DigitalExcelImportTest.php                      ← NEW (action-level tests)
├── SaleImportApiTest.php                           ← NEW (vendor API tests)
└── DigitalImportApiTest.php                        ← NEW (vendor API tests)
```

---

## Phase 0: Research

No external unknowns. All patterns are established in Phase 2.4. Research findings below confirm the approach.

### Research Findings

**Decision 1 — Reuse `RentalServicesImport` shape verbatim**
- Both `SaleServicesImport` and `DigitalServicesImport` implement `ToCollection + WithHeadingRow` — identical structure to `RentalServicesImport`. No configuration needed.
- Alternative (using `ToModel`) rejected: ToModel runs row-by-row and can't enforce no-partial-commit without custom savepoint logic.

**Decision 2 — Admin import as header `Action` (not a separate Filament `Page`)**
- `SaleServiceResource` and `DigitalServiceResource` already exist. Adding a `->headerActions([...])` entry on the list table is the idiomatic Filament v3 way — avoids an extra navigation item and keeps the import contextually within the resource.
- A separate `Page` (like `ImportRentalServicesPage`) was the Phase 2.4 approach (vendor-facing). For admin, a modal `Action` is better UX because it keeps vendor context in one step.

**Decision 3 — Vendor approval check placement**
- Check happens at the start of `ImportSaleServicesFromExcelAction::execute()` / `ImportDigitalServicesFromExcelAction::execute()`, before any file processing. Same gate as `service.create.sale.own` / `service.create.digital.own`.
- Rationale: fail fast before wasting time parsing large files.

**Decision 4 — `store_id` validation in vendor API**
- `store_id` accepts the `vendor_profile.public_id` (CHAR(26) ULID). The controller resolves the authenticated vendor's `vendor_profile` and compares its `public_id` with the submitted `store_id`. Mismatch → 403.
- This keeps controllers thin (≤3 lines of real work) — resolution logic in `ImportSaleServicesRequest::vendorProfile()`.

**Decision 5 — Admin vendor selector: direct model access in Filament action**
- Per the cross-module precedent established in Phase 2.4 (`ImportRentalServicesPage` accesses `VendorProfile`), the admin header action form uses `VendorProfile::query()->approved()->get()` for the vendor selector.
- The `business_name` JSON column is accessed as `business_name->en` via MySQL JSON path for the display label.
- This is confined to the Filament admin layer; no Action classes access cross-module models.

---

## Phase 1: Data Model

### No schema changes

`excel_imports` and `excel_import_errors` were created in Phase 2.4. The `product_type` column on `excel_imports` is already ENUM('rental','sale','digital') — `sale` and `digital` values are supported by the existing schema. No migrations are needed.

Verify: `excel_imports.product_type` ENUM includes 'sale' and 'digital'.

### Existing models in play

| Model | Table | Module | Role |
|---|---|---|---|
| `ExcelImport` | `excel_imports` | Catalog (Imports) | Tracks import attempt; updated to completed/failed |
| `ExcelImportError` | `excel_import_errors` | Catalog (Imports) | Per-row validation errors with bilingual message |
| `Service` | `services` | Catalog | Created on successful import |
| `ServiceSaleDetail` | `service_sale_details` | Catalog | Created on successful sale import |
| `ServiceDigitalDetail` | `service_digital_details` | Catalog | Created on successful digital import |
| `VendorProfile` | `vendor_profiles` | Identity | Source of `vendor_profile_id`; accessed in admin action (Filament layer only) |

### Import DTO column mappings

**sale Excel template columns → `CreateSaleServiceDTO`**

| Column | Type | Required | Maps to |
|---|---|---|---|
| `name_en` | string | YES | `name['en']` |
| `name_ar` | string | YES | `name['ar']` |
| `short_description_en` | string | YES | `shortDescription['en']` |
| `short_description_ar` | string | YES | `shortDescription['ar']` |
| `base_price_minor` | integer ≥0 | YES | `basePriceMinor` |
| `category_id` | integer ≥1 | YES | `categoryId` |
| `is_perishable` | boolean (0/1) | YES | `isPerishable` |
| `is_made_to_order` | boolean (0/1) | YES | `isMadeToOrder` |
| `lead_time_hours` | integer ≥1 | IF `is_made_to_order=true` | `leadTimeHours` |
| `stock_quantity` | integer ≥0 | NO | `stockQuantity` |

**digital Excel template columns → `CreateDigitalServiceDTO`**

| Column | Type | Required | Maps to |
|---|---|---|---|
| `name_en` | string | YES | `name['en']` |
| `name_ar` | string | YES | `name['ar']` |
| `short_description_en` | string | YES | `shortDescription['en']` |
| `short_description_ar` | string | YES | `shortDescription['ar']` |
| `base_price_minor` | integer ≥0 | YES | `basePriceMinor` |
| `category_id` | integer ≥1 | YES | `categoryId` |
| `delivery_method` | enum string | YES | `deliveryMethod` |
| `has_expiry` | boolean (0/1) | YES | `hasExpiry` |
| `expiry_days_after_purchase` | integer ≥1 | IF `has_expiry=true` | `expiryDaysAfterPurchase` |
| `is_refundable_after_delivery` | boolean (0/1) | YES | `isRefundableAfterDelivery` |
| `redemption_url_template` | string | NO | `redemptionUrlTemplate` |

---

## Phase 2: API Contracts

### New Vendor API Endpoints

#### POST `/api/v1/vendor/services/sale/import`

```
Auth: Sanctum (vendor)
Request: multipart/form-data
```

**Form fields (Scribe @bodyParam):**

| Field | Type | Required | Description |
|---|---|---|---|
| `store_id` | string (ULID) | YES | Public ID of the vendor's own vendor_profile |
| `file` | file (.xlsx/.xls) | YES | Excel file with sale services template |

**Responses:**

| Code | Scenario | Body |
|---|---|---|
| 200 | All rows valid, import completed | `{"data":{"status":"completed","imported_rows":5,"total_rows":5},"meta":{},"errors":[]}` |
| 422 | Validation failure (field missing, wrong MIME) | `{"data":null,"meta":{},"errors":[{"code":"import_failed","status":"failed","total_rows":5,"imported_rows":0,"errors":[{"row":3,"field":"base_price_minor","message":{"en":"...","ar":"..."}}]}]}` |
| 422 | Invalid file type | `{"data":null,"meta":{},"errors":[{"code":"invalid_file_type","message":{"en":"Only .xlsx and .xls files are accepted.","ar":"يُقبل فقط ملفات .xlsx و .xls."}}]}` |
| 403 | `store_id` not owned by authenticated vendor | `{"data":null,"meta":{},"errors":[{"code":"store_not_owned","message":{"en":"This store does not belong to you.","ar":"هذا المتجر لا ينتمي إليك."}}]}` |
| 403 | Vendor not approved for `sale` type | `{"data":null,"meta":{},"errors":[{"code":"vendor_type_not_approved","message":{"en":"You are not approved to sell sale-type services.","ar":"لم تتم الموافقة عليك لبيع خدمات من نوع البيع."}}]}` |
| 401 | Unauthenticated | Standard 401 |

#### POST `/api/v1/vendor/services/digital/import`

Identical contract as above, substituting `sale` → `digital` in all descriptions, error messages, and type checks.

---

## Implementation Sequence

Follow this layer order per constitution §spec-kit-tasks:

### Step 1 — Importers (Infrastructure layer)

**Files:**
- `app/Modules/Catalog/Infrastructure/Importers/SaleServicesImport.php`
- `app/Modules/Catalog/Infrastructure/Importers/DigitalServicesImport.php`

**Pattern:** identical to `RentalServicesImport` — `ToCollection + WithHeadingRow`. No custom logic in the importer class; all validation happens in the Action.

```php
// SaleServicesImport.php — verbatim copy of RentalServicesImport with namespace rename
class SaleServicesImport implements ToCollection, WithHeadingRow { ... }

// DigitalServicesImport.php — same shape
class DigitalServicesImport implements ToCollection, WithHeadingRow { ... }
```

---

### Step 2 — Import Actions (Application layer)

**Files:**
- `app/Modules/Catalog/Application/Actions/ImportSaleServicesFromExcelAction.php`
- `app/Modules/Catalog/Application/Actions/ImportDigitalServicesFromExcelAction.php`

**Constructor injection:**
```php
// ImportSaleServicesFromExcelAction
public function __construct(
    private readonly CreateSaleServiceAction $createSaleServiceAction,
) {}

// ImportDigitalServicesFromExcelAction
public function __construct(
    private readonly CreateDigitalServiceAction $createDigitalServiceAction,
) {}
```

**Execute signature (same as rental):**
```php
public function execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport
```

**Validation rules for sale:**
```php
private array $rules = [
    'name_en'                  => ['required', 'string', 'max:255'],
    'name_ar'                  => ['required', 'string', 'max:255'],
    'short_description_en'     => ['required', 'string', 'max:1000'],
    'short_description_ar'     => ['required', 'string', 'max:1000'],
    'base_price_minor'         => ['required', 'integer', 'min:0'],
    'category_id'              => ['required', 'integer', 'min:1'],
    'is_perishable'            => ['required', 'boolean'],
    'is_made_to_order'         => ['required', 'boolean'],
    'lead_time_hours'          => ['nullable', 'integer', 'min:1'],
    'stock_quantity'           => ['nullable', 'integer', 'min:0'],
];
// Post-validation: if is_made_to_order=true and lead_time_hours missing → additional error per row
```

> **Note on `lead_time_hours` conditional**: Because `Validator::make()` can't read sibling field values in simple `required_if` rules across the row-by-row loop, apply a second pass after the baseline validator: if `(bool)$row['is_made_to_order']` is true and `$row['lead_time_hours']` is empty → push a bilingual error for that row. This is the same technique used for `is_made_to_order` in `CreateSaleServiceRequest`.

**Validation rules for digital:**
```php
private array $rules = [
    'name_en'                        => ['required', 'string', 'max:255'],
    'name_ar'                        => ['required', 'string', 'max:255'],
    'short_description_en'           => ['required', 'string', 'max:1000'],
    'short_description_ar'           => ['required', 'string', 'max:1000'],
    'base_price_minor'               => ['required', 'integer', 'min:0'],
    'category_id'                    => ['required', 'integer', 'min:1'],
    'delivery_method'                => ['required', 'string', 'in:download,email,api,redemption_code'],
    'has_expiry'                     => ['required', 'boolean'],
    'expiry_days_after_purchase'     => ['nullable', 'integer', 'min:1'],
    'is_refundable_after_delivery'   => ['required', 'boolean'],
    'redemption_url_template'        => ['nullable', 'string', 'max:500'],
];
// Post-validation: if has_expiry=true and expiry_days_after_purchase missing → bilingual error
```

**DTO construction inside Action (sale):**
```php
new CreateSaleServiceDTO(
    vendorProfileId: $vendorProfileId,
    categoryId: (int) $row['category_id'],
    name: ['en' => (string) $row['name_en'], 'ar' => (string) $row['name_ar']],
    shortDescription: ['en' => (string) $row['short_description_en'], 'ar' => (string) $row['short_description_ar']],
    basePriceMinor: (int) $row['base_price_minor'],
    isPerishable: (bool) $row['is_perishable'],
    isMadeToOrder: (bool) $row['is_made_to_order'],
    leadTimeHours: isset($row['lead_time_hours']) && $row['lead_time_hours'] !== '' ? (int) $row['lead_time_hours'] : null,
    stockQuantity: isset($row['stock_quantity']) && $row['stock_quantity'] !== '' ? (int) $row['stock_quantity'] : null,
    customizationFields: null, // not included in Excel template
);
```

---

### Step 3 — Form Requests (HTTP layer)

**Files:**
- `app/Modules/Catalog/Http/Requests/ImportSaleServicesRequest.php`
- `app/Modules/Catalog/Http/Requests/ImportDigitalServicesRequest.php`

**Both requests share the same field structure:**

```php
public function rules(): array
{
    return [
        'store_id' => ['required', 'string', 'size:26'],
        'file'     => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
    ];
}

/**
 * Resolves and returns the authenticated vendor's VendorProfile.
 * Throws 403 if store_id does not match.
 */
public function vendorProfile(): VendorProfile
{
    $vendor = $this->user()->vendorProfile()->firstOrFail();
    if ($vendor->public_id !== $this->input('store_id')) {
        abort(403, json_encode([
            'code'    => 'store_not_owned',
            'message' => ['en' => 'This store does not belong to you.', 'ar' => 'هذا المتجر لا ينتمي إليك.'],
        ]));
    }
    return $vendor;
}
```

---

### Step 4 — Controllers (HTTP layer)

**Files:**
- `app/Modules/Catalog/Http/Controllers/Vendor/ImportSaleServicesController.php`
- `app/Modules/Catalog/Http/Controllers/Vendor/ImportDigitalServicesController.php`

**Controller body ≤3 lines per convention:**

```php
// ImportSaleServicesController
public function store(ImportSaleServicesRequest $request, ImportSaleServicesFromExcelAction $action): JsonResponse
{
    $vendor  = $request->vendorProfile();
    $import  = $action->execute($request->file('file'), $vendor->id, app()->getLocale());

    return $import->status === 'completed'
        ? ApiResponse::success(['status' => 'completed', 'imported_rows' => $import->imported_rows, 'total_rows' => $import->total_rows])
        : ApiResponse::error('Import failed', 422, $import->errors()->get()->toArray());
}
```

---

### Step 5 — Routes update

**File:** `app/Modules/Catalog/Routes/vendor.php`

Add below the existing service routes:
```php
// Excel imports
Route::post('services/sale/import',    [ImportSaleServicesController::class, 'store'])->name('vendor.services.sale.import');
Route::post('services/digital/import', [ImportDigitalServicesController::class, 'store'])->name('vendor.services.digital.import');
```

---

### Step 6 — Admin Filament header actions

**Files modified:** `SaleServiceResource.php`, `DigitalServiceResource.php`

Add to the `table()` method's `->headerActions([...])`:

```php
use App\Modules\Catalog\Application\Actions\ImportSaleServicesFromExcelAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

Action::make('importSaleServices')
    ->label(__('catalog.import_sale_services'))
    ->icon('heroicon-o-arrow-up-tray')
    ->form([
        Select::make('vendor_profile_id')
            ->label(__('catalog.vendor'))
            ->options(
                fn () => VendorProfile::query()
                    ->where('approval_status', 'approved')
                    ->get()
                    ->mapWithKeys(fn ($vp) => [$vp->id => data_get($vp->business_name, 'en')])
            )
            ->searchable()
            ->required(),
        FileUpload::make('file')
            ->label(__('catalog.import_file_label'))
            ->acceptedFileTypes([
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ])
            ->disk('local')
            ->directory('excel-imports-temp')
            ->required(),
    ])
    ->action(function (array $data, ImportSaleServicesFromExcelAction $action): void {
        $absolutePath = Storage::disk('local')->path($data['file']);
        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

        $import = $action->execute($file, (int) $data['vendor_profile_id'], app()->getLocale());

        if ($import->status === 'completed') {
            Notification::make()
                ->title(__('catalog.import_success', ['count' => $import->imported_rows]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('catalog.import_failed'))
                ->danger()
                ->send();
        }
    })
    ->modalHeading(__('catalog.import_sale_services'))
    ->modalSubmitActionLabel(__('catalog.import'))
    ->requiresConfirmation(false),
```

Identical action added to `DigitalServiceResource` with `importDigitalServices` name and `ImportDigitalServicesFromExcelAction`.

---

### Step 7 — Pest tests

#### `tests/Feature/Modules/Catalog/SaleExcelImportTest.php`

Test groups: `catalog`, `import`, `sale`

| Test | Asserts |
|---|---|
| All valid sale rows → `completed`, services + saleDetails in DB | happy path |
| One invalid row → `failed`, zero services, `excel_import_errors` created | rollback |
| `is_made_to_order=true` + missing `lead_time_hours` → error | conditional validation |
| Error messages have bilingual shape `{en, ar}` | FR-IV |

#### `tests/Feature/Modules/Catalog/DigitalExcelImportTest.php`

Test groups: `catalog`, `import`, `digital`

| Test | Asserts |
|---|---|
| All valid digital rows → `completed`, services + digitalDetails in DB | happy path |
| One invalid row → `failed`, zero services | rollback |
| `has_expiry=true` + missing `expiry_days_after_purchase` → error | conditional validation |
| Invalid `delivery_method` value → error | enum validation |
| Error messages have bilingual shape | FR-IV |

#### `tests/Feature/Modules/Catalog/SaleImportApiTest.php`

Test groups: `catalog`, `api`, `sale`

| Test | Code | Asserts |
|---|---|---|
| Authenticated vendor, valid file, matching store_id → success | 200 | `data.status=completed` |
| Valid file, store_id belongs to different vendor | 403 | `errors[0].code=store_not_owned` |
| Non-xlsx file upload | 422 | validation error for `file` |
| Missing `store_id` | 422 | validation error |
| Unauthenticated request | 401 | standard |
| Invalid row in file | 422 | `data.status=failed`, errors array present |
| Submitted `vendor_id` is ignored (uses auth vendor) | 200 | services created for auth vendor, not for submitted vendor_id |

#### `tests/Feature/Modules/Catalog/DigitalImportApiTest.php`

Test groups: `catalog`, `api`, `digital`

Identical set as SaleImportApiTest, substituting digital endpoint and digital validations.

---

## Cut-list (inherited from Phase 6.1)

None — this is the deferred Phase 2.4 work. No cut-list items; this spec IS the minimal deliverable.

**Phase 2 deferrals (not in scope):**
- Image folder upload alongside Excel (filename references only for now)
- Async/queued background imports for very large files
- Admin import UI: per-row error table displayed inline in Filament (currently shown via notification only — detailed errors accessible on the `ExcelImport` model for programmatic access)

---

## Exit Criteria (from spec)

- [ ] Admin can import `sales.xlsx` from Filament with explicit vendor selection — services appear in `SaleServiceResource` list
- [ ] Admin can import `digital.xlsx` from Filament with explicit vendor selection — services appear in `DigitalServiceResource` list
- [ ] Vendor can POST `sales.xlsx` to `/api/v1/vendor/services/sale/import` and receive `200` success envelope
- [ ] Vendor can POST `digital.xlsx` to `/api/v1/vendor/services/digital/import` and receive `200` success envelope
- [ ] A file with any invalid row creates zero services and returns per-row bilingual errors (full rollback enforced)
