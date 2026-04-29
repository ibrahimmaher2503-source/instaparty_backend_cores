<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Infrastructure\Importers\RentalServicesImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportRentalServicesFromExcelAction
{
    public function __construct(
        private readonly CreateRentalServiceAction $createRentalServiceAction,
    ) {}

    public function execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport
    {
        // 1. Store the uploaded file
        $storedPath = $file->store('excel-imports', 'local');

        // 2. Create a pending import record
        $excelImport = ExcelImport::create([
            'public_id'         => \Illuminate\Support\Str::ulid()->toBase32(),
            'vendor_profile_id' => $vendorProfileId,
            'product_type'      => ProductType::Rental,
            'status'            => 'pending',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $storedPath,
            'total_rows'        => 0,
            'imported_rows'     => 0,
            'error_rows'        => 0,
        ]);

        // 3. Load all rows from the Excel file
        $importer = new RentalServicesImport();
        Excel::import($importer, $file);
        $rows = $importer->getRows();
        $totalRows = $rows->count();

        // 4. Validate every row and collect per-row errors
        $validationErrors = $this->validateAllRows($rows);

        // 5a. If any row has errors — fail the entire import (no partial commits)
        if ($validationErrors->isNotEmpty()) {
            DB::transaction(function () use ($excelImport, $totalRows, $validationErrors): void {
                $excelImport->update([
                    'status'     => 'failed',
                    'total_rows' => $totalRows,
                    'error_rows' => $validationErrors->count(),
                ]);

                foreach ($validationErrors as $error) {
                    ExcelImportError::create([
                        'excel_import_id' => $excelImport->id,
                        'row_number'      => $error['row'],
                        'field'           => $error['field'],
                        'message'         => $error['messages'],
                    ]);
                }
            });

            return $excelImport->refresh();
        }

        // 5b. All rows valid — create all services in a single transaction
        DB::transaction(function () use ($excelImport, $rows, $totalRows, $vendorProfileId): void {
            foreach ($rows as $row) {
                $dto = new CreateRentalServiceDTO(
                    vendorProfileId:            $vendorProfileId,
                    categoryId:                 (int) $row['category_id'],
                    name:                       ['en' => (string) $row['name_en'], 'ar' => (string) $row['name_ar']],
                    shortDescription:           ['en' => (string) $row['short_description_en'], 'ar' => (string) $row['short_description_ar']],
                    basePriceMinor:             (int) $row['base_price_minor'],
                    requiresElectricity:        (bool) $row['requires_electricity'],
                    requiresOutdoorSpace:       (bool) $row['requires_outdoor_space'],
                    defaultRentalDurationHours: (int) $row['default_rental_duration_hours'],
                    setupTimeMinutes:           isset($row['setup_time_minutes']) ? (int) $row['setup_time_minutes'] : null,
                    teardownTimeMinutes:        isset($row['teardown_time_minutes']) ? (int) $row['teardown_time_minutes'] : null,
                    securityDepositMinor:       isset($row['security_deposit_minor']) ? (int) $row['security_deposit_minor'] : null,
                    minimumSpaceSqm:            isset($row['minimum_space_sqm']) ? (int) $row['minimum_space_sqm'] : null,
                );

                $this->createRentalServiceAction->execute($dto);
            }

            $excelImport->update([
                'status'        => 'completed',
                'total_rows'    => $totalRows,
                'imported_rows' => $totalRows,
                'error_rows'    => 0,
            ]);
        });

        return $excelImport->refresh();
    }

    /**
     * Validate all rows and return a flat collection of error entries.
     *
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, array{row: int, field: string, messages: array<string>}>
     */
    private function validateAllRows(Collection $rows): Collection
    {
        $errors = collect();

        foreach ($rows as $index => $row) {
            $rowNumber  = $index + 2; // +2 because row 1 is the header
            $rowArray   = $row->toArray();

            $validator = Validator::make($rowArray, [
                'name_en'                       => ['required', 'string', 'max:255'],
                'name_ar'                       => ['required', 'string', 'max:255'],
                'short_description_en'          => ['required', 'string', 'max:1000'],
                'short_description_ar'          => ['required', 'string', 'max:1000'],
                'base_price_minor'              => ['required', 'integer', 'min:0'],
                'requires_electricity'          => ['required', 'boolean'],
                'requires_outdoor_space'        => ['required', 'boolean'],
                'default_rental_duration_hours' => ['required', 'integer', 'min:1'],
                'setup_time_minutes'            => ['nullable', 'integer', 'min:0'],
                'teardown_time_minutes'         => ['nullable', 'integer', 'min:0'],
                'security_deposit_minor'        => ['nullable', 'integer', 'min:0'],
                'minimum_space_sqm'             => ['nullable', 'integer', 'min:1'],
                'category_id'                   => ['required', 'integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    $errors->push([
                        'row'      => $rowNumber,
                        'field'    => $field,
                        'messages' => $messages,
                    ]);
                }
            }
        }

        return $errors;
    }
}
