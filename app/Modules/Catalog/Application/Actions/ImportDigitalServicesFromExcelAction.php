<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Infrastructure\Importers\DigitalServicesImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportDigitalServicesFromExcelAction
{
    public function __construct(
        private readonly CreateDigitalServiceAction $createDigitalServiceAction,
    ) {}

    public function execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport
    {
        $storedPath = $file->store('excel-imports', 'local');

        $excelImport = ExcelImport::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'product_type' => ProductType::Digital,
            'status' => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'imported_rows' => 0,
            'error_rows' => 0,
        ]);

        $importer = new DigitalServicesImport;
        Excel::import($importer, $file);
        $rows = $importer->getRows();
        $totalRows = $rows->count();

        $validationErrors = $this->validateAllRows($rows);

        if ($validationErrors->isNotEmpty()) {
            DB::transaction(function () use ($excelImport, $totalRows, $validationErrors): void {
                $excelImport->update([
                    'status' => 'failed',
                    'total_rows' => $totalRows,
                    'error_rows' => $validationErrors->count(),
                ]);

                foreach ($validationErrors as $error) {
                    ExcelImportError::create([
                        'excel_import_id' => $excelImport->id,
                        'row_number' => $error['row'],
                        'field' => $error['field'],
                        'message' => $error['message'],
                    ]);
                }
            });

            return $excelImport->refresh();
        }

        DB::transaction(function () use ($excelImport, $rows, $totalRows, $vendorProfileId): void {
            foreach ($rows as $row) {
                $dto = new CreateDigitalServiceDTO(
                    vendorProfileId: $vendorProfileId,
                    categoryId: (int) $row['category_id'],
                    name: ['en' => (string) $row['name_en'], 'ar' => (string) $row['name_ar']],
                    shortDescription: ['en' => (string) $row['short_description_en'], 'ar' => (string) $row['short_description_ar']],
                    basePriceMinor: (int) $row['base_price_minor'],
                    deliveryMethod: (string) $row['delivery_method'],
                    hasExpiry: (bool) $row['has_expiry'],
                    expiryDaysAfterPurchase: isset($row['expiry_days_after_purchase']) && $row['expiry_days_after_purchase'] !== '' ? (int) $row['expiry_days_after_purchase'] : null,
                    isRefundableAfterDelivery: (bool) $row['is_refundable_after_delivery'],
                    redemptionUrlTemplate: isset($row['redemption_url_template']) && $row['redemption_url_template'] !== '' ? (string) $row['redemption_url_template'] : null,
                );

                $this->createDigitalServiceAction->execute($dto);
            }

            $excelImport->update([
                'status' => 'completed',
                'total_rows' => $totalRows,
                'imported_rows' => $totalRows,
                'error_rows' => 0,
            ]);
        });

        return $excelImport->refresh();
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, array{row: int, field: string, message: array{en: string, ar: string}}>
     */
    private function validateAllRows(Collection $rows): Collection
    {
        $errors = collect();
        $rules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'short_description_en' => ['required', 'string', 'max:1000'],
            'short_description_ar' => ['required', 'string', 'max:1000'],
            'base_price_minor' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'min:1'],
            'delivery_method' => ['required', 'string', 'in:email,sms,whatsapp,link'],
            'has_expiry' => ['required', 'boolean'],
            'expiry_days_after_purchase' => ['nullable', 'integer', 'min:1'],
            'is_refundable_after_delivery' => ['required', 'boolean'],
            'redemption_url_template' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rowArray = $row->toArray();

            $enErrors = Validator::make($rowArray, $rules)->errors()->toArray();

            // Conditional: expiry_days_after_purchase required when has_expiry is truthy
            if ((bool) ($rowArray['has_expiry'] ?? false) && empty($rowArray['expiry_days_after_purchase'])) {
                $enErrors['expiry_days_after_purchase'][] = 'The expiry days after purchase field is required when has expiry is true.';
            }

            if (! empty($enErrors)) {
                app()->setLocale('ar');
                $arErrors = Validator::make($rowArray, $rules)->errors()->toArray();

                // Add Arabic conditional error for expiry_days_after_purchase if needed
                if (isset($enErrors['expiry_days_after_purchase']) && ! isset($arErrors['expiry_days_after_purchase'])) {
                    $arErrors['expiry_days_after_purchase'][] = 'حقل أيام انتهاء الصلاحية مطلوب عندما يكون للمنتج تاريخ انتهاء.';
                }

                app()->setLocale('en');

                foreach ($enErrors as $field => $enMessages) {
                    $errors->push([
                        'row' => $rowNumber,
                        'field' => $field,
                        'message' => [
                            'en' => implode(' ', $enMessages),
                            'ar' => implode(' ', $arErrors[$field] ?? $enMessages),
                        ],
                    ]);
                }
            }
        }

        return $errors;
    }
}
