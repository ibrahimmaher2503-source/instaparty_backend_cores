<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Infrastructure\Importers\SaleServicesImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportSaleServicesFromExcelAction
{
    public function __construct(
        private readonly CreateSaleServiceAction $createSaleServiceAction,
    ) {}

    public function execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport
    {
        $storedPath = $file->store('excel-imports', 'local');

        $excelImport = ExcelImport::create([
            'public_id' => Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'product_type' => ProductType::Sale,
            'status' => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'imported_rows' => 0,
            'error_rows' => 0,
        ]);

        $importer = new SaleServicesImport;
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
                $dto = new CreateSaleServiceDTO(
                    vendorProfileId: $vendorProfileId,
                    categoryId: (int) $row['category_id'],
                    name: ['en' => (string) $row['name_en'], 'ar' => (string) $row['name_ar']],
                    shortDescription: ['en' => (string) $row['short_description_en'], 'ar' => (string) $row['short_description_ar']],
                    basePriceMinor: (int) $row['base_price_minor'],
                    isPerishable: (bool) $row['is_perishable'],
                    isMadeToOrder: (bool) $row['is_made_to_order'],
                    leadTimeHours: isset($row['lead_time_hours']) && $row['lead_time_hours'] !== '' ? (int) $row['lead_time_hours'] : null,
                    stockQuantity: isset($row['stock_quantity']) && $row['stock_quantity'] !== '' ? (int) $row['stock_quantity'] : null,
                    customizationFields: null,
                );

                $this->createSaleServiceAction->execute($dto);
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
            'is_perishable' => ['required', 'boolean'],
            'is_made_to_order' => ['required', 'boolean'],
            'lead_time_hours' => ['nullable', 'integer', 'min:1'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $rowArray = $row->toArray();

            $enErrors = Validator::make($rowArray, $rules)->errors()->toArray();

            // Conditional: lead_time_hours required when is_made_to_order is truthy
            if ((bool) ($rowArray['is_made_to_order'] ?? false) && empty($rowArray['lead_time_hours'])) {
                $enErrors['lead_time_hours'][] = 'The lead time hours field is required when is made to order is true.';
            }

            if (! empty($enErrors)) {
                app()->setLocale('ar');
                $arErrors = Validator::make($rowArray, $rules)->errors()->toArray();

                // Add Arabic conditional error for lead_time_hours if needed
                if (isset($enErrors['lead_time_hours']) && ! isset($arErrors['lead_time_hours'])) {
                    $arErrors['lead_time_hours'][] = 'حقل وقت التنفيذ مطلوب عندما يكون المنتج يُصنع حسب الطلب.';
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
